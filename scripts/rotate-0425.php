<?php

// 因為 0425 有一堆蔣乃心的都印到九十度了
$input = fopen('../output0425.old.csv', 'r');
$columns = fgetcsv($input);

$output = fopen('php://output', 'w');
fputcsv($output, $columns);
while ($rows = fgetcsv($input)) {
    list($id, $file, $page, $pic, $width, $height) = $rows;

    if (preg_match('#蔣乃辛#', $file)) {
        $fp = fopen('rotate.jpg', 'w');
        $curl = curl_init($pic);
        curl_setopt($curl, CURLOPT_FILE, $fp);
        curl_exec($curl);
        curl_close($curl);
        fclose($fp);

        system("convert -rotate 90 rotate.jpg ../image0425/{$id}.jpg");
        $oldjson = json_decode(file_get_contents("../output0425.old/{$id}.json"));
        $newjson = new StdClass;
        $newjson->width = $oldjson->height;
        $newjson->height = $oldjson->width;

        $newjson->horizons = array();
        foreach ($oldjson->verticles as $verticle) {
            $horizon = new Stdclass;
            $horizon->theta = $verticle->theta - pi() / 2;
            $horizon->r = $verticle->r;
            $newjson->horizons[] = $horizon;
        }
        $newjson->verticles = array();
        foreach ($oldjson->horizons as $horizon) {
            if ($horizon->r_sum / $horizon->count != $horizon->r) {
                continue;
            }
            $verticle = new Stdclass;
            $verticle->theta = $horizon->theta + pi() / 2;
            $verticle->r = 2000 - $horizon->r;
            array_unshift($newjson->verticles, $verticle);
        }

        $newjson->cross_points = array();
        foreach ($oldjson->cross_points[0] as  $col_crosspoints) {
            $newjson->cross_points[] = array();
        }

        foreach ($oldjson->cross_points as $i => $row_crosspoints) {
            foreach ($row_crosspoints as $j => $crosspoints) {
                $newjson->cross_points[$j][$i] = array($crosspoints[1], $crosspoints[0]);
            }
        }

        file_put_contents("../output0425/{$id}.json", json_encode($newjson));
        unlink('rotate.jpg');
        fputcsv($output, array(
            $id, $file, $page, "image0425/{$id}.jpg", $height, $width
        ));
    } else {
        fputcsv($output, $rows);
        copy("../output0425.old/{$id}.json", "../output0425/{$id}.json");
    }
}
