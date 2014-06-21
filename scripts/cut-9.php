<?php

// 這一隻 script 是將上一層的 list.csv 的圖檔抓下來
// 並且找出所有線條，將資訊另外儲存一個 json
//
ini_set('memory_limit', '1024m');

class Searcher
{
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

                    if ($this->isColor($gd, $x, $y, 'red')) {
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

    public function isColor($gd, $x, $y, $color)
    {
        $rgb = imagecolorat($gd, $x, $y);
        $colors = imagecolorsforindex($gd, $rgb);

        switch ($color) {
        case 'blue':
            return $colors['red'] == 0 and $colors['green'] == 0 and $colors['blue'] == 255;
        case 'green':
            return $colors['red'] == 0 and $colors['green'] == 255 and $colors['blue'] == 0;
        case 'red':
            return $colors['red'] == 255 and $colors['green'] == 0 and $colors['blue'] == 0;
        case 'black':
            return $colors['red'] == 0 and $colors['green'] == 0 and $colors['blue'] == 0;
        case 'white':
            return $colors['red'] == 255 and $colors['green'] == 255 and $colors['blue'] == 255;
        case 'white2':
            return ($colors['red'] + $colors['green'] + $colors['blue']) / 3 > 230;
        case 'black2':
            return ($colors['red'] + $colors['green'] + $colors['blue']) / 3 < 23;
        }
    }

    /**
     * searchLineFromPoint 找經過 ($x, $y) 的最長線條
     * 
     * @param mixed $x 
     * @param mixed $y 
     * @param mixed $min_angle 最小角度
     * @param mixed $max_angle 最小角度
     * @param mixed $angle_chunk 找多細
     * @access public
     * @return void
     */
    public function searchLineFromPoint($gd, $top_x, $top_y, $min_angle = 0, $max_angle = 90, $angle_chunk = 1000, $skip = 5)
    {
        error_log("searchLineFromPoint({$top_x}, {$top_y})");
        $max_count = 0;

        $angle_mid = ($min_angle + $max_angle) / 2;
        $angle_delta = ($max_angle - $min_angle) / $angle_chunk;
        $width = imagesx($gd);
        $height = imagesy($gd);

        for ($i = 1; $i < $angle_chunk; $i ++) {
            $angle = $angle_mid + $angle_delta * floor($i / 2) * ($i % 2 ? 1 : -1);
            $theta = deg2rad($angle + 90);
            $r = ($top_y * sin($theta) + $top_x * cos($theta));
            $count = 0;
            $max_point[0] = $max_point[1] = array($top_x, $top_y);
            $no_point_counter = array(0 => 0, 1 => 0);

            for ($pos = 0; true; $pos ++) {
                if ($angle_mid < 45) {
                    $x = $top_x + floor($pos / 2) * (($pos % 2) ? -1 : 1);
                    $y = floor(($r - $x * cos($theta)) / sin($theta));
                } else {
                    $y = $top_y + floor($pos / 2) * (($pos % 2) ? -1: 1);
                    $x = floor(($r - $y * sin($theta)) / (cos($theta)));
                }

                if ($no_point_counter[0] > $skip and $no_point_counter[1] > $skip) {
                    break;
                }

                if ($y < 0 or $y > $height or $x < 0 or $x > $width) {
                    $no_point_counter[$pos % 2] ++;
                    continue;
                }

                // 如果有一個方向已經連續 $skip px 找不到任何東西，視為已經沒有了
                if ($no_point_counter[$pos % 2] > $skip) {
                    continue;
                }

                foreach (range(0, 5) as $range) {
                    if ($angle_mid < 45) {
                        list($sx, $sy) = array($x, $y + $range);
                    } else {
                        list($sx, $sy) = array($x + $range, $y);
                    }
                    if ($sx > $width or $sy > $height) {
                        continue;
                    }
                    if (!$this->isColor($gd, $sx, $sy, 'white')) {
                        $count ++;
                        $no_point_counter[$pos % 2] = 0;
                        $max_point[$pos % 2] = array(floor($x), floor($y));
                    }
                }
                $no_point_counter[$pos % 2] ++;
            }

            if ($count > $max_count) {
                $max_count = $count;
                $answer = array(
                    'theta' => $theta,
                    'score' => $max_count,
                    'points' => $max_point,
                );
            }
        }
        return $answer;
    }

    public function main($input, $output, $output_dir)
    {
        if (!file_exists($input)) {
            throw new Exception("沒有輸入檔");
        }
        if (!file_exists($output)) {
            $fp = fopen($output, 'w');
            fputcsv($fp, array(
                '檔名', '頁數', '位置', '網址', '切割圖檔',
            ));
            fclose($fp);
        }
        $fp = fopen($output, 'r');
        $columns = fgetcsv($fp);
        $showed = array();
        while ($rows = fgetcsv($fp)) {
            $showed[$rows[0]] = true;
        }
        fclose($fp);

        $foutput = fopen($output, 'a');
        $finput = fopen($input, 'r');
        $columns = fgetcsv($finput);
        while ($rows = fgetcsv($finput)) {
            list($file, $page, $url) = $rows;
            if ($showed[$file]) {
                continue;
            }

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
            $gd = $func($tmpfile);
            $gd_ori = $func($tmpfile);
            error_log('open done');

            if (false) {
                $gd = imagecreatefrompng('blackwhite.png');
            } else {
                // 轉成只有黑跟白
                for ($x = imagesx($gd); $x--;) {
                    for ($y = imagesy($gd); $y--;) {
                        $rgb = imagecolorat($gd, $x, $y);
                        $colors = imagecolorsforindex($gd, $rgb);
                        $gray = ($colors['red'] + $colors['green'] + $colors['blue']) / 3;
                        if ($colors['alpha'] == 127 or $gray < 180) {
                            imagesetpixel($gd, $x, $y, 0x000000);
                        } else {
                            imagesetpixel($gd, $x, $y, 0xFFFFFF);
                        }
                    }
                }
            }

            error_log('filter done');

            $red = imagecolorallocate($gd, 255, 0, 0);
            $green = imagecolorallocate($gd, 0, 255, 0);
            $black = imagecolorallocate($gd, 0, 0, 0);
            $white = imagecolorallocate($gd, 255, 255, 255);

            $width = imagesx($gd);
            $height = imagesy($gd);

            $prev_x = array();
            foreach (array(0, 1, 2) as $y_pos) {
                foreach (array(0, 1, 2) as $x_pos) {
                    error_log("[$x_pos, $y_pos]");
                    $max_point = null;
                    $max_count = 0;
                    $y = floor(($y_pos * 2 + 1) * $height / 6);
                    if ($prev_x[$y_pos] == -1) {
                        continue;
                    }
                    // 只需要掃到高度 1/6 的部份就好了
                    for ($i = 0; $i < $width / 6; $i ++) {
                        $found_point = false;
                        // 檢查 -10, 0, 10 三個點，避免正好 $y 的部份遇到斷掉的地方就穿過去了
                        foreach (array(-10, 0, 10) as $y_delta) {
                            $x = $i + intval($prev_x[$y_pos]);
                            $y = floor(($y_pos * 2 + 1) * $height / 6) + $y_delta;
                            if ($this->isColor($gd, $x, $y, 'white')) {
                                continue;
                            }
                            if ($this->isColor($gd, $x, $y, 'green')) {
                                continue;
                            }
                            $found_point = array($x, $y);
                        }
                        if (false === $found_point) {
                            continue;
                        }
                        $y = $found_point[1];

                        // 遇到了把他填滿成紅色
                        imagefill($gd, $x, $y, $red);

                        // 掃一遍看看總共有多少 pixel 變成紅色
                        $count = $this->countRed($gd, $width, $height, $x, $y);
                        error_log("fill ({$x}, {$y}) $count");

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
                        if ($count > 100) {
                            break;
                        }
                    }
                    if ($i >= $width / 6) {
                        $prev_x[$y_pos] = -1;
                        continue;
                    }

                    // 已知外框線條有經過 ($x, $y)，利用霍夫轉換找到線條具體位置
                    $answer = $this->searchLineFromPoint($gd, $x, $y, 88, 92, 400);
                    var_dump($answer);
                    $max_point = $answer['points'];
                    if ($max_point[1][1] < $max_point[0][1]) {
                        list($left_bottom, $left_top) = $max_point;
                    } else {
                        list($left_top, $left_bottom) = $max_point;
                    }
                    if (!$this->isColor($gd, $left_bottom[0] + 3, $left_bottom[1] - 3, 'white')) {
                        $left_bottom[1] -= 3;
                        $left_bottom[0] += 3;
                    }

                    // 找垂直線
                    $answer = $this->searchLineFromPoint($gd, $left_bottom[0], $left_bottom[1], -2, 2, 300);
                    var_dump($answer);
                    $max_point = $answer['points'];
                    if ($max_point[0][0] > $max_point[1][0]) {
                        $right_bottom = $max_point[0];
                    } else {
                        $right_bottom = $max_point[1];
                    }
                    $right_top = array(
                        $right_bottom[0] - $left_bottom[0] + $left_top[0],
                        $right_bottom[1] - $left_bottom[1] + $left_top[1],
                    );

                    $prev_x[$y_pos] = max($right_bottom[0], $right_top[0]) + 10;
                    
                    $rect = array();
                    $rect['x'] = intval(max($left_top[0], $left_bottom[0]) + 10);
                    $rect['y'] = intval(max($left_top[1], $right_top[1]) + 10);
                    $rect['width'] = intval(min($right_top[0], $right_bottom[0]) - 10 - $rect['x']);
                    $rect['height'] = intval(min($right_bottom[1], $left_bottom[1]) - 10 - $rect['y']);
                    print_r(array('left_top' => $left_top, 'right_top' => $right_top, 'left_bottom' => $left_bottom, 'right_bottom' => $right_bottom));
                    var_dump($rect);

                    imagepng($gd, 'tmp.png');
                    $croped = imagecrop($gd_ori, $rect);
                    $pos = $y_pos * 3 + $x_pos;
                    $output_file = $output_dir . '/' . crc32($url) . '-' . $pos . '.png';
                    imagepng($croped, $output_file);
                    imagedestroy($croped);

                    imagerectangle($gd, $rect['x'], $rect['y'], $rect['x'] + $rect['width'], $rect['y'] + $rect['height'], $blue);
                    foreach (array($left_top, $left_bottom, $right_top, $right_bottom) as $point) {
                        imagearc($gd, $point[0], $point[1], 10, 10, 0, 360, $blue);
                    }

                    fputcsv($foutput, array(
                        $file, $page, $pos, $url, crc32($url) . '-' . $pos . '.png'
                    ));
                }
            }

            imagepng($gd, $output_dir . '/' . crc32($url) . '.png');
            imagedestroy($gd);
            imagedestroy($gd_ori);
        }
    }
}

$s = new Searcher;
$s->main(__DIR__ . '/../list0612.csv', __DIR__ . '/../list0612-9.csv', __DIR__ . '/../output0612-9/');
