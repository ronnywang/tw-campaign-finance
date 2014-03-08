<?php

// 這一隻 script 是將上一層的 list.csv 的圖檔抓下來
// 並且找出所有線條，將資訊另外儲存一個 json
//
class Searcher
{
    protected $line_groups = array();

    public function addLine($line)
    {
        list($x1, $y1, $x2, $y2) = $line;

        // 先統一讓線條一定是左上到右下
        if ($x1 < $x2) {
        } elseif ($x1 == $x2) {
            list($y1, $y2) = array(min($y1, $y2), max($y1, $y2));
        } else {
            list($x1, $x2, $y1, $y2) = array($x2, $x1, $y2, $y1);
        }
        $line = array($x1, $y1, $x2, $y2);

        // 透過 Hough 轉換得到  θ 和 r
        // r = x * cos θ + y * sin θ
        // r = x1 * cos θ + y1 * sin θ
        // r = x2 * cos θ + y2 * sin θ
        // (x2 - x1) * cos θ= (y1 - y2) * sin θ
        // (x2 - x1) / (y1 - y2) = tan  θ

        $theta = -1 * atan2($y2 - $y1, $x2 - $x1);
        $r = $x1 * sin($theta) + $y1 * cos($theta);
        $line[] = $theta;
        $line[] = $r;

        // 把 theta 和 r 接近的 group 在一起，允許誤差值如下
        $theta_threshold = 0.03;
        $r_threshold = 0.01;
        // 找到了，這個 function 就結束了直接 return
        foreach ($this->line_groups as $id => $line_group) {
            if (floatval(abs($line_group->theta - $theta)) / pi() > $theta_threshold or (floatval(abs($line_group->r - $r)) / max($this->size[0], $this->size[1])) > $r_threshold) {
                continue;
            }
            $this->line_groups[$id]->lines[] = $line;
            $this->line_groups[$id]->theta_sum += $theta;
            $this->line_groups[$id]->r_sum += $r;
            $this->line_groups[$id]->theta = $this->line_groups[$id]->theta_sum / count($this->line_groups[$id]->lines);
            $this->line_groups[$id]->r = $this->line_groups[$id]->r_sum / count($this->line_groups[$id]->lines);
            return;
        }

        // 找不到的話就在 line_group 新增一條線
        $obj = new StdClass;
        $obj->lines = array($line);
        $obj->theta_sum = $obj->theta = $theta;
        $obj->r_sum = $obj->r = $r;
        $this->line_groups[] = $obj;
    }

    /**
     * 將水平線和垂直線畫在 line-sample.png 檔案中
     * 
     * @param array $verticles 
     * @param array $horizons 
     * @param int $width 
     * @param int $height 
     * @access public
     * @return void
     */
    public function drawLines($verticles, $horizons, $width, $height)
    {
        $gd = imagecreate($width, $height);
        $white = imagecolorallocate($gd, 255, 255, 255);
        $black = imagecolorallocate($gd, 0, 0, 0);

        // 把線條畫在圖上
        foreach ($verticles as $line_group) {
            $y1 = 0;
            $x1 = sin($line_group->theta) ? floor($line_group->r / sin($line_group->theta)) : 0;

            $y2 = $height;
            $x2 = sin($line_group->theta) ? floor(($line_group->r - $x2 * cos($line_group->theta)) / sin($line_group->theta)) : 0;
            imageline($gd, $x1, $y1, $x2, $y2, $black);
        }

        foreach ($horizons as $line_group) {
            $x1 = 0;
            $y1 = floor($line_group->r / cos($line_group->theta));

            $x2 = $width;
            $y2 = floor(($line_group->r - $y2 * sin($line_group->theta)) / cos($line_group->theta));
            error_log("$x1 $x2 $y1 $y2");
            imageline($gd, $x1, $y1, $x2, $y2, $black);
        }

        imagepng($gd, 'line-sample.png');
    }

    /**
     * 找出所有垂直線和水平線的交點
     * 
     * @param array $verticles
     * @param array $horizons 
     * @access public
     * @return array 所有交點的二維陣列
     */
    public function getCrossPoints($verticles, $horizons)
    {
        $points = array();

        foreach ($verticles as $i => $vertice_line) {
            $points[$i] = array();

            foreach ($horizons as $j => $horizon_line) {
                // x + a1 * y = b1
                // x + a2 * y = b2
                // => y = (b1 - b2) / (a1 - a2)
                // => x = b1 - a1 * y = b1 - a1 * (b1 - b2) / (a1 - a2) 
                // r = x * cosθ+ y * sinθ
                // => x + (sinθ/ cosθ) * y = r / cosθ

                $a1 = sin($vertice_line->theta) / cos($vertice_line->theta);
                $b1 = $vertice_line->r / cos($vertice_line->theta);

                $a2 = sin($horizon_line->theta) / cos($horizon_line->theta);
                $b2 = $horizon_line->r / cos($horizon_line->theta);

                $y = floor(($b1 - $b2) / ($a1 - $a2));
                $x = floor($b2 - $a2 * $y);

                $points[$i][$j] = array($x, $y);
            }
        }

        return $points;
    }

    public function getLinesFromPNG($file)
    {
        $cmd = "./pic2linesjson " . escapeshellarg($file);
        // 可以得到很多線段，但是要把線段組合在一起
        $json = json_decode(exec($cmd));
        $lines = $json->lines;
        $this->size = $json->size;
        list($width, $height) = $this->size;

        $this->line_groups = array();

        // 將所有線條丟入 line_groups 並且分類
        foreach ($lines as $line) {
            $this->addLine($line);
        }

        // 分出接近垂直和水平線
        $horizons = $verticles = array();
        foreach ($this->line_groups as $line_group) {
            if (-0.02 < $line_group->theta and $line_group->theta < 0.02) {
                $horizons[] = $line_group;
            } elseif (0.98 * pi() / 2 < abs($line_group->theta) and abs($line_group->theta) < 1.02 * pi() / 2) {
                $verticles[] = $line_group;
            } else {
                print_r($line_group);
                throw new Exception("有一條非垂直和水平的線");
            }
        }

        $filter = function($a){
            $obj = new StdClass;
            $obj->theta = $a->theta;
            $obj->r = $a->r;
            return $obj;
        };

        $ret = new stdClass;
        $ret->width = $width;
        $ret->height = $height;
        $ret->horizon_lines = array_map($filter, $horizons);
        $ret->verticles_lines = array_map($filter, $verticles);
        $ret->cross_points = $this->getCrossPoints($verticles, $horizons);
        return $ret;
    }

    public function main($input, $output)
    {
        if (!file_exists($input)) {
            throw new Exception("沒有輸入檔");
        }

        $showed = array();
        $id = 1;
        if (file_exists($output)) {
            if (!is_file($output)) {
                throw new Exception("輸出必需要是檔案");
            }
            $fp = fopen($output, 'r');
            $columns = fgetcsv($fp);
            while ($rows = fgetcsv($fp)) {
                list($id, $path, $page, $url, $width, $height) = $rows;
                $showed[$path . '-' . $page] = true;
            }
            $id ++;
            $fp = fopen($output, 'a');
        } else {
            if (!file_exists(dirname($output)) or !is_dir(dirname($output))) {
                throw new Exception("找不到所在資料夾");
            }
            $fp = fopen($output, 'w');
            fputcsv($fp, array(
                'id',
                '檔名',
                '頁數',
                '網址',
                '圖寬',
                '圖高',
            ));
        }

        $finput = fopen($input, 'r');
        $columns = fgetcsv($finput);
        while ($rows = fgetcsv($finput)) {
            list($file, $page, $url) = $rows;
            if ($showed[$file . '-' . $page]) {
                continue;
            }
            error_log($url);
            
            $curl = curl_init($url);

            $output = fopen('tmp.png', 'w');
            curl_setopt($curl, CURLOPT_FILE, $output);
            curl_exec($curl);
            curl_close($curl);

            $ret = $this->getLinesFromPNG('tmp.png');
            fputcsv($fp, array(
                $id,
                $file,
                $page,
                $url,
                $ret->width,
                $ret->height,
            ));
            file_put_contents(__DIR__ . '/../outputs/' . $id . '.json', json_encode($ret));
            $id ++;
            unlink('tmp.png');
        }
    }
}

$s = new Searcher;
$s->main(__DIR__ . '/../list.csv', __DIR__ . '/../output.csv');
