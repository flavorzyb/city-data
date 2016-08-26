<?php
class Spider
{
    private $outDir = '';
    private $urlArray = [];
    private $urlMapPath = '';
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

    /**
     * @return string
     */
    public function getUrlMapPath()
    {
        return $this->urlMapPath;
    }

    /**
     * @param string $urlMapPath
     */
    public function setUrlMapPath($urlMapPath)
    {
        $this->urlMapPath = $urlMapPath;
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
            curl_close($ch);
            if (!curl_errno($ch)) {
                break;
            }
            $times ++;
        } while($times < 5);

        return trim($result);
    }

    public function load($url)
    {
        $this->loadMapData();
        if (!isset($this->urlArray[$url])) {
            $content = $this->getContent($url);
            if (strlen($content) > 50) {
                $this->writeContent($url, $content);
            }
        } else {
            $content = $this->loadContent($url);
        }

        $content = str_ireplace(' ', '', $content);
        $content = str_ireplace(['<br>', '<br/>', '<td>', '</td>', '</tr>', '<tr>', "\r\n", "\r", "\n"], '', $content);
        $content = str_ireplace(['<tr>'], '', $content);
        $content = str_ireplace(['"'], "'", $content);

        $dataArray = explode('</a>', $content);

        $baseUrl = substr($url, 0, strripos($url, '/') + 1);

        foreach ($dataArray as $v) {
            $data = [];
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

    private function writeContent($url, $content)
    {
        $md5Str = md5($url);
        $this->urlArray[$url] = $md5Str;
        file_put_contents($this->getUrlMapPath(), json_encode($this->urlArray));
        $path =  $this->getOutDir() . '/' . substr($md5Str, 0, 2) . '/' . substr($md5Str, 2, 2);

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        file_put_contents($path . '/' . $md5Str, $content);
    }

    private function loadContent($url)
    {
        $md5Str = $this->urlArray[$url];
        $path =  $this->getOutDir() . '/' . substr($md5Str, 0, 2) . '/' . substr($md5Str, 2, 2) . '/' . $md5Str;
        return trim(file_get_contents($path));
    }

    private function loadMapData()
    {
        clearstatcache();
        if (is_file($this->getUrlMapPath())) {
            $content = trim(file_get_contents($this->getUrlMapPath()));
            $this->urlArray = json_decode($content, true);
        }
    }
}

