<?php

// 這一隻 script 是將上一層的 list.csv 的圖檔抓下來
// 並且找出所有線條，將資訊另外儲存一個 json
//
class Searcher
{
    public function fixHorizons()
    {
        $horizons= $this->line_groups['horizons'];
        usort($horizons, function($a, $b) { return $a->r > $b->r ? 1 : -1; });

        $last_horizon = null;
        $min_delta = null;
        $count = 1;
        foreach ($horizons as $horizon) {
            if (is_null($last_horizon)) {
                $last_horizon = $horizon;
                continue;
            }

            $delta = $horizon->r - $last_horizon->r;
            if (is_null($min_delta)) {
                $min_delta = $delta;
                $c = 1;
                $last_horizon = $horizon;
                continue;
            }

            if (round(max($min_delta, $delta) / min($min_delta, $delta)) > 1) {
                if ($min_delta > $delta) {
                    $min_delta = $delta;
                    $c = 1;
                }
                $last_horizon = $horizon;
                continue;
            }
            $last_horizon = $horizon;
            $min_delta = ($min_delta * $c + $delta) / ($c + 1);
            $c ++;
        }

        $output_horizons = array();
        $last_horizon = null;
        foreach ($horizons as $horizon) {
            if (is_null($last_horizon)) {
                $last_horizon = $horizon;
                $output_horizons[] = $horizon;
                continue;
            }

            $delta = $horizon->r - $last_horizon->r;
            for ($i = 1; $i < round($delta / $min_delta); $i ++) {
                $last_horizon = clone $last_horizon;
                $last_horizon->r += $min_delta;
                $output_horizons[] = $last_horizon;
            }
            $output_horizons[] = $horizon;
            $last_horizon = $horizon;
        }

        $this->line_groups['horizons'] = $output_horizons;
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
                $x = floor($b1 - $a1 * $y);

                $points[$i][$j] = array($x, $y);
            }
        }

        return $points;
    }
    public function scaleCrossPoints($i_j_points, $scale)
    {
        $ret = array();
        foreach ($i_j_points as $i => $j_points) {
            $ret[$i] = array();
            foreach ($j_points as $j => $points) {
                $ret[$i][$j] = array(
                    floor($points[0] * $scale),
                    floor($points[1] * $scale),
                );
            }
        }
        return $ret;
    }

    protected $line_groups = array();

    public function addLine($group, $r, $theta, $width, $height)
    {
        if (!array_key_exists($group, $this->line_groups)) {
            $this->line_groups[$group] = array();
        }
        $line = array();
        $line[] = $theta;
        $line[] = $r;

        // 把 theta 和 r 接近的 group 在一起，允許誤差值如下
        $theta_threshold = 0.03;
        $r_threshold = 0.01;
        // 找到了，這個 function 就結束了直接 return
        foreach ($this->line_groups[$group] as $id => $line_group) {
            if (floatval(abs($line_group->theta - $theta)) / pi() > $theta_threshold or (floatval(abs($line_group->r - $r)) / max($width, $height)) > $r_threshold) {
                continue;
            }
            $line_group->count ++;
            $line_group->theta_sum += $theta;
            $line_group->r_sum += $r;
            $line_group->theta = $line_group->theta_sum / $line_group->count;
            $line_group->r = $line_group->r_sum / $line_group->count;
            $this->line_groups[$group][$id] = $line_group;
            return;
        }

        // 找不到的話就在 line_group 新增一條線
        $obj = new StdClass;
        $obj->count = 1;
        $obj->theta_sum = $obj->theta = $theta;
        $obj->r_sum = $obj->r = $r;
        $this->line_groups[$group][] = $obj;
    }

    public function countRed($gd, $width, $height, $center_x, $center_y)
    {
        for ($i = 1; true; $i ++) {
            $over = true;
            $x = $center_x - $i;
            $y = $center_y - $i;
            foreach (array(array(0,1),array(1,0),array(0,-1),array(-1,0)) as $way) {
                list($x_delta, $y_delta) = $way;
                for ($j = 0; $j < 2 * $i; $j ++) {
                    $x += $x_delta;
                    $y += $y_delta;

                    if ($x < 0 or $x >= $width) {
                        continue;
                    }
                    if ($y < 0 or $y >= $height) {
                        continue;
                    }

                    $rgb = imagecolorat($gd, $x, $y);
                    $colors = imagecolorsforindex($gd, $rgb);
                    if ($colors['red'] == 255 and $colors['green'] == 0 and $colors['blue'] == 0) {
                        $over = false;
                        break;
                    }
                }
            }

            if ($over) {
                break;
            }
        }
        return $i;
    }

    public function main($input, $output, $output_dir)
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
            $this->line_groups = array();
            error_log($url);
            if (strpos($url, 'png')) {
                $tmpfile = 'tmp.png';
                $func = 'imagecreatefrompng';
            } else {
                $tmpfile = 'tmp.jpg';
                $func = 'imagecreatefromjpeg';
            }
            
            $curl = curl_init($url);

            $output = fopen($tmpfile, 'w');
            curl_setopt($curl, CURLOPT_FILE, $output);
            curl_exec($curl);
            curl_close($curl);
            fclose($output);

            // 先把圖讀去 GD
            if ($tmpfile == 'tmp.jpg' and !function_exists('imagecreatefromjpeg')) {
                system('convert tmp.jpg tmp.png');
                $tmpfile = 'tmp.png';
                $func = 'imagecreatefrompng';
            }
            error_log('convert done');
            $gd_ori = $func($tmpfile);
            error_log('open done');

            // 先縮到最大邊 2000 ，加快速度
            $height = imagesy($gd_ori);
            $width = imagesx($gd_ori);
            $scale = 2000.0 / max($width, $height);
            $gd = imagecreatetruecolor(floor($width * $scale), floor($height * $scale));
            imagecopyresized($gd, $gd_ori, 0, 0, 0, 0, floor($width * $scale), floor($height * $scale), $width, $height);

            // 轉成只有黑跟白
            for ($x = imagesx($gd); $x--;) {
                for ($y = imagesy($gd); $y--;) {
                    $rgb = imagecolorat($gd, $x, $y);
                    $colors = imagecolorsforindex($gd, $rgb);
                    $gray = ($colors['red'] + $colors['green'] + $colors['blue']) / 3;
                    if ($colors['alpha'] == 127 or $gray < 127) {
                        imagesetpixel($gd, $x, $y, 0x000000);
                    } else {
                        imagesetpixel($gd, $x, $y, 0xFFFFFF);
                    }
                }
            }

            error_log('filter done');
            $width = imagesx($gd);
            $height = imagesy($gd);

            $red = imagecolorallocate($gd, 255, 0, 0);
            $green = imagecolorallocate($gd, 0, 255, 0);
            $black = imagecolorallocate($gd, 0, 0, 0);
            $white = imagecolorallocate($gd, 255, 255, 255);

            // 如果最上面或是最下面就是黑色，表示可能是影印造成的問題，就把他給濾掉
            $rgb = imagecolorat($gd, floor($width / 2), 0);
            $colors = imagecolorsforindex($gd, $rgb);
            if ($colors['red'] == 0 and $colors['green'] == 0 and $colors['blue'] == 0) {
                imagefill($gd, floor($width / 2), 0, $white);
            }
            $rgb = imagecolorat($gd, floor($width / 2), $heigth - 1);
            $colors = imagecolorsforindex($gd, $rgb);
            if ($colors['red'] == 0 and $colors['green'] == 0 and $colors['blue'] == 0) {
                imagefill($gd, floor($width / 2), $height - 1, $white);
            }

            // 從圖正中間往下畫一條垂直線

            $max_count = 0;
            $max_point = array();

            $x = floor($width / 2);
            for ($i = 0; $i < $height; $i ++) {
                $y = $i;
                $rgb = imagecolorat($gd, $x, $y);
                $colors = imagecolorsforindex($gd, $rgb);

                if ($colors['red'] == 255 and $colors['green'] == 255 and $colors['blue']== 255) {
                    continue;
                }
                if ($colors['red'] == 0 and $colors['green'] == 255 and $colors['blue'] == 0) {
                    continue;
                }

                // 遇到了把他填滿成紅色
                imagefill($gd, $x, $y, $red);

                // 掃一遍看看總共有多少 pixel 變成紅色
                $count = $this->countRed($gd, $width, $height, $x, $y);
                error_log($y . ' ' . $count);

                if ($count > $max_count) {
                    if ($max_point) {
                        imagefill($gd, $max_point[0], $max_point[1], $white);
                    }
                    $max_count = $count;
                    $max_point = array($x, $y);
                    imagefill($gd, $x, $y, $green);
                } else {
                    imagefill($gd, $x, $y, $white);
                }
            }

            list($top_x, $top_y) = $max_point;
            // 剛剛上面的 $max_point 是交點，來找跟交點連接的線條
            // 因為遇到的應該是接近水平線
            // 因此以 0度 => 1度 => -1度 => 2度 => -2度 的順序去比對，應該可以最快找到
            $angle_base = null;
            for ($check_y = $top_y; $check_y < $height; $check_y ++ ) {
                $rgb = imagecolorat($gd, $top_x, $check_y);
                $colors = imagecolorsforindex($gd, $rgb);
                if ($colors['red'] != 0 or $colors['green'] != 255 or $colors['blue'] != 0) {
                    continue;
                }
                $bottom_y = $check_y;
                $max_count = 0;
                $boost = 10;
                $i_limit = is_null($angle_base) ? (90 * $boost) : (5 * $boost);
                for ($i = 1; $i < $i_limit; $i ++) {
                    $angle = ($angle_base ?: 0) + floor($i / 2) / $boost * ($i % 2 ? 1 : -1);

                    // r = y * sinθ+ x * cosθ (為了讓 θ= 0是水平線，所以把 cos, sin 對調
                    $theta = pi() * ($angle + 90) / 180;
                    $r = $check_y * sin($theta) + $top_x * cos($theta);

                    $no_point_counter_a = $no_point_counter_b = 0;
                    for ($x_pos = 1; $x_pos < $width; $x_pos ++) {
                        $x = $top_x + floor($x_pos / 2) * (($x_pos % 2) ? -1 : 1);
                        $y = ($r - $x * cos($theta)) / sin($theta);
                        if ($y < 0 or $y > $height) {
                            break;
                        }
                        foreach (range(2, -2) as $range) {
                            $rgb = imagecolorat($gd, floor($x), floor($y + $range));
                            $colors = imagecolorsforindex($gd, $rgb);
                            if ($colors['red'] != 0 and $colors['green'] != 0 and $colors['blue'] != 0) {
                                if ($x_pos % 2) {
                                    $no_point_counter_a = 0;
                                    $max_xy = array($x, $y);
                                } else {
                                    $no_point_counter_b = 0;
                                    $min_xy = array($x, $y);
                                }
                            }
                        }
                        // 連續超過 20 點找不到東西就表示這角度不對
                        if ($no_point_counter_a ++ > 20 or $no_point_counter_b ++ > 20) {
                            break;
                        }
                    }
                    if ($x_pos > $max_count) {
                        $max_points = array($min_xy, $max_xy);
                        $max_count = $x_pos;
                        $max_angle = $angle;
                        $max_r = $r;
                        $max_theta = $theta;
                    }
                }
                $angle_base = $max_angle;
                if (pow($max_points[0][1] - $max_points[1][1], 2) + pow($max_points[0][0] - $max_points[1][0], 2) < 100 * 100) {
                    continue;
                }

                //imageline($gd, $max_points[0][0], $max_points[0][1], $max_points[1][0], $max_points[1][1], $red);
                $this->addLine('horizons', $max_r, $max_theta, $width, $height);
            }

            // 接下來處理垂直線，上面的 $top_y 是表格最上端， $bottom_y 是表格最下端
            // 所以從 ($top_y + $bottom_y) / 2 高度的地方從左射出一條水平射線
            // 理論上就可以對到所有的垂直線..那就跟上面做法一樣了
            $angle_base = null;
            $middle_y = floor(($top_y + $bottom_y) / 2);
            for ($check_x = 0; $check_x < $width; $check_x ++) {
                $rgb = imagecolorat($gd, $check_x, $middle_y);
                $colors = imagecolorsforindex($gd, $rgb);
                if ($colors['red'] != 0 or $colors['green'] != 255 or $colors['blue'] != 0) {
                    continue;
                }

                $max_count = 0;
                $boost = 10;
                $i_limit = is_null($angle_base) ? (90 * $boost) : (5 * $boost);
                for ($i = 1; $i < $i_limit; $i ++) {
                    $angle = ($angle_base ?: 0) + floor($i / 2) / $boost * ($i % 2 ? 1 : -1);

                    // r = x * cosθ+ y * sinθ (這邊要從垂直線出發)
                    $theta = pi() * $angle / 180;
                    $r = $check_x * cos($theta) + $middle_y * sin($theta);

                    $no_point_counter_a = $no_point_counter_b = 0;
                    for ($y_pos = 1; $y_pos < $height ; $y_pos ++) {
                        $y = floor($middle_y + floor($y_pos / 2) * (($y_pos % 2) ? -1 : 1));
                        $x = floor(($r - $y * sin($theta)) / cos($theta));
                        if ($x < 0 or $x > $width) {
                            break;
                        }
                        foreach (range(3, -3) as $range) {
                            $rgb = imagecolorat($gd, floor($x + $range), floor($y));
                            $colors = imagecolorsforindex($gd, $rgb);
                            if ($colors['red'] == 0 and $colors['green'] == 255 and $colors['blue'] == 0) {
                                if ($y_pos % 2) {
                                    $no_point_counter_a = 0;
                                    $max_xy = array($x, $y);
                                } else {
                                    $no_point_counter_b = 0;
                                    $min_xy = array($x, $y);
                                }
                            }
                        }
                        // 連續超過 20 點找不到東西就表示這角度不對
                        if ($no_point_counter_a ++ > 20 or $no_point_counter_b ++ > 20) {
                            break;
                        }
                    }
                    if ($y_pos > $max_count) {
                        $max_points = array($min_xy, $max_xy);
                        $max_count = $y_pos;
                        $max_angle = $angle;
                        $max_r = $r;
                        $max_theta = $theta;
                    }
                }
                $angle_base = $max_angle;
                if ($max_count < 0.5 * ($bottom_y - $top_y)) {
                    continue;
                }
                imageline($gd, $max_points[0][0], $max_points[0][1], $max_points[1][0], $max_points[1][1], $red);
                error_log("{$check_x} {$max_count}");
                $this->addLine('verticles', $max_r, $max_theta, $width, $height);
            }
            //imageline($gd, 0, $middle_y, $width, $middle_y, $red);

            if (!$this->line_groups['verticles']) {
                file_put_contents('failed', "Failed: 0 " . $url . "\n", FILE_APPEND);
                continue;
            }

            // 看看水平線是不是等距
            $this->fixHorizons();
            $cross_points = $this->getCrossPoints($this->line_groups['verticles'], $this->line_groups['horizons']);
            foreach ($cross_points as $line_cross_points) {
                foreach ($line_cross_points as $cross_point) {
                    imageellipse($gd, $cross_point[0], $cross_point[1], 20, 20, $red);
                }
            }
            $cross_points = $this->scaleCrossPoints($cross_points, 1.0 / $scale);

            imagepng($gd, 'output.png');
            if (count($cross_points) != 10) {
                file_put_contents('failed', "Failed: " . count($cross_points) . " " . $url . "\n", FILE_APPEND);
                error_log('failed');
            }
            $width = imagesx($gd_ori);
            $height = imagesy($gd_ori);
            imagedestroy($gd_ori);
            fputcsv($fp, array(
                $id,
                $file,
                $page,
                $url,
                $width,
                $height,
            ));
            $ret = new stdClass;
            $ret->width = $width;
            $ret->height = $height;
            $ret->horizons = $this->line_groups['horizons'];
            $ret->verticles = $this->line_groups['verticles'];
            $ret->cross_points = $cross_points;
            file_put_contents($output_dir . $id . '.json', json_encode($ret));
            $id ++;
        }
    }
}

$s = new Searcher;
$s->main(__DIR__ . '/../list0423.csv', __DIR__ . '/../output0423.csv', __DIR__ . '/../output0423/');
