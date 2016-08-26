<?php
class Spider
{
    private $outDir = '';
    /**
     * @param string $outDir
     */
    public function setOutDir($outDir)
    {
        $this->outDir = $outDir;
    }

    /**
     * @return string
     */
    public function getOutDir()
    {
        return $this->outDir;
    }

    private function download($url) {
        $times = 0;
        do {
            $ch   = curl_init();
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

            // ssl opt
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $result = curl_exec($ch);
            if (!curl_errno($ch)) {
                curl_close($ch);
                return trim($result);
            }
            curl_close($ch);
            $times ++;
        } while($times < 5);

        return '';
    }

    public function load($url)
    {
        $md5Str = md5($url);
        $path =  $this->getOutDir() . '/' . substr($md5Str, 0, 2) . '/' . substr($md5Str, 2, 2);
        $fullPath = $path . '/' . $md5Str;
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        if (!is_file($fullPath)) {
            $content = $this->getContent($url);
            if (strlen($content) > 100) {
                file_put_contents($fullPath, $content);
            }
        } else {
            $content = trim(file_get_contents($fullPath));
        }

        $content = str_ireplace(' ', '', $content);
        $content = str_ireplace(['<br>', '<br/>', '<td>', '</td>', '</tr>', '<tr>', "\r\n", "\r", "\n"], '', $content);
        $content = str_ireplace(['<tr>'], '', $content);
        $content = str_ireplace(['"'], "'", $content);

        $dataArray = explode('</a>', $content);

        $baseUrl = substr($url, 0, strripos($url, '/') + 1);

        foreach ($dataArray as $v) {
            $data = [];
            $index = strripos($v, '<ahref=');
            $v = substr($v, $index);
            preg_match_all("<ahref='(.*)'>", $v, $data);
            if (isset($data[1]) && isset($data[1][0])) {
                $href = $data[1][0];
                if ((substr($href, 0, 7) != 'http://') && (substr($href, 0, 8) != 'https://')) {
                    $newUrl = $baseUrl . $href;
                    $this->load($newUrl);
                }
            }
        }
    }

    private function getContent($url) {
        echo "download url:{$url}\n";
        $result = $this->download($url);
        $result = iconv('GBK', 'UTF-8//ignore', $result);
        $result = strtolower($result);
        return  $result;
    }
}

set_time_limit(-1);
$outDir = __DIR__ . '/../out';
$spider = new Spider();
$spider->setOutDir($outDir);
$spider->load('http://www.stats.gov.cn/tjsj/tjbz/tjyqhdmhcxhfdm/2015/');
