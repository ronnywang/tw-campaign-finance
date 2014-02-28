<?php

include(__DIR__ . '/PixAPI.php');
include(__DIR__ . '/config.php');

class Uploader
{
    public function main()
    {
        $api = new PixAPI(getenv('PIXNET_CONSUMER_KEY'), getenv('PIXNET_CONSUMER_SECRET'));
        $api->setToken(getenv('PIXNET_ACCESS_KEY'), getenv('PIXNET_ACCESS_SECRET'));

        $fp = fopen('list.csv', 'r');
        $pdf_infos = array();
        while ($rows = fgetcsv($fp)) {
            $pdf_infos[$rows[0]] = $rows;
        }
        fclose($fp);
        $fp = fopen('list.csv', 'a');

        $files = explode("\n", trim(`find . | grep pdf`));
        foreach ($files as $file) {
            if (array_key_exists($file, $pdf_infos)) {
                continue;
            }

            //$cmd = "convert -density 300 " . escapeshellarg($file) . " tmp-%d.png";
            $cmd = "pdfimages " . escapeshellarg($file) . " tmp";
            exec($cmd);

            foreach (glob('tmp-*.pbm') as $png_file) {
                error_log($png_file);
                preg_match('/tmp-(.*)\.pbm/', $png_file, $matches);
                $page = $matches[1];
                $cmd = "convert -rotate 180 tmp-{$page}.pbm tmp.jpg";
                exec($cmd);
                // 將照片上傳到 ronnywang.pixnet.net/album/set/14951790
                $ret = $api->album_add_element(14951790, 'tmp.jpg', $file . '-' . $page, '');
                fputcsv($fp, array(
                    $file,
                    $page,
                    $ret->element->original,
                ));
            }
            system("rm tmp-*");
        }
        return;

        foreach (LicenseFileDownload::search(1)->volumemode(10000) as $file_download) {
            if ($file_download->pic_url) {
                continue;
            }
            $source = __DIR__ . '/../files/' . $file_download->file_name;
            try {
                if (!$ret->element->original) {
                    throw new Exception();
                }
            } catch (Exception $e) {
                $message = "upload {$file_download->file_sn} failed: " . $file_download->file_name;
                error_log($message);
                continue;
            }
            $file_download->update(array('pic_url' => $ret->element->original));
            unlink($source);
        }
    }
}

$d = new Uploader;
$d->main();
