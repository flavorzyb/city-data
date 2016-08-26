<?php

class Parser
{
    private $dataDir = '';

    private $outDir = '';

    private $out = [];
    /**
     * @return string
     */
    public function getDataDir()
    {
        return $this->dataDir;
    }

    /**
     * @param string $dataDir
     */
    public function setDataDir($dataDir)
    {
        $this->dataDir = $dataDir;
    }

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

    private function parseStr($content)
    {
        $result = [];
        $dataArray = explode('</a>', $content);
        foreach ($dataArray as $v) {
            $data = [];
            preg_match_all("<ahref='(.*)'>", $v, $data);
            $text = trim(substr($v, strripos($v, ">")));
            if (isset($data[1]) && isset($data[1][0])) {
                $result[$data[1][0]] = $text;
            }
        }

        return $result;
    }

    private function loadContent($url)
    {
        $md5Str = md5($url);
        $path =  $this->getDataDir() . '/' . substr($md5Str, 0, 2) . '/' . substr($md5Str, 2, 2);
        $fullPath = $path . '/' . $md5Str;
        if (!is_file($fullPath)) {
            die("url({$url}) file($fullPath) is not exist.");
        }

        return trim(file_get_contents($fullPath));
    }

    private function getProvince($content)
    {
        $index = stripos($content, "<tr class='provincetr'><td>");
        $end = stripos($content, "</table>", $index);
        $content = trim(substr($content, $index, $end - $index));
        $content = str_ireplace(' ', '', $content);
        $content = str_ireplace(['<br>', '<br/>', '<td>', '</td>', '</tr>', '<tr>', "\r\n", "\r", "\n"], '', $content);
        $content = str_ireplace(['<tr>'], '', $content);
        return $this->parseStr($content);
    }

    private function getCity($url, array $result)
    {
        var_dump($url);
        $content = $this->loadContent($url);
        $index = stripos($content, "名称");
        $end = stripos($content, "</table>", $index);
        $content = trim(substr($content, $index, $end - $index));
        $content = str_ireplace(' ', '', $content);
        $content = str_ireplace(['<br>', '<br/>', '<td>', '</td>', '</tr>', '<tr>', "\r\n", "\r", "\n"], '', $content);
        $content = str_ireplace(['<tr>'], '', $content);

        $dataArray = $this->parseStr($content);
        $baseUrl = substr($url, 0, strripos($url, '/') + 1);

        foreach ($dataArray as $k => $v) {
            if (!isset($result[$v])) {
                $result[$v] = [];
            }
            if (stripos($k, '.') !== false) {
                $result[$v] = $this->getCity($baseUrl . $k, $result[$v]);
            }
        }

        return $result;
    }

    public function load($url)
    {
        $result = $this->getProvince($this->loadContent($url));
        $baseUrl = substr($url, 0, strripos($url, '/') + 1);

        foreach ($result as $k => $v) {
            $this->out[$v] = $this->getCity($baseUrl . $k, []);
            print_r($this->out[$v]);
            break;
        }
    }
}

set_time_limit(-1);
$dataDir = __DIR__ . '/../out';
$outDir = __DIR__ . '/../configs';
$parser = new Parser();
$parser->setDataDir($dataDir);
$parser->setOutDir($outDir);
$parser->load('http://www.stats.gov.cn/tjsj/tjbz/tjyqhdmhcxhfdm/2015/');
