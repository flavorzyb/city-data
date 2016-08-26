<?php
define('INDEX_URL', 'http://www.stats.gov.cn/tjsj/tjbz/tjyqhdmhcxhfdm/2015/index.html');

class Parser {
    private $out = [];

    private function getContent($url) {
        echo "url:" . $url . "\n";
        $result = file_get_contents($url);
        usleep(10000);
        $result = iconv('GBK', 'UTF-8//ignore', $result);
        $result = strtolower($result);
        return  $result;
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

        var_dump($result, $content, $dataArray);

        return $result;
    }

    private function getProvince($url)
    {
        $content = $this->getContent($url);
        $index = stripos($content, "<tr class='provincetr'><td>");
        $end = stripos($content, "</table>", $index);
        $content = trim(substr($content, $index, $end - $index));
        $content = str_ireplace(' ', '', $content);
        $content = str_ireplace(['<br>', '<br/>', '<td>', '</td>', '</tr>', '<tr>'], '', $content);
        $content = str_ireplace(['<tr>'], '', $content);
        return $this->parseStr($content);
    }

    private function getCity($url, array $result)
    {
        $content = $this->getContent($url);
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
        $provinceArray = $this->getProvince($url);
        $baseUrl = substr($url, 0, strripos($url, '/') + 1);
        foreach ($provinceArray as $provinceUrl => $province) {
            $url = $baseUrl . $provinceUrl;
            if (!isset($this->out[$province])) {
                $this->out[$province] = [];
            }
            $url = 'http://www.stats.gov.cn/tjsj/tjbz/tjyqhdmhcxhfdm/2015/11/01/07/110107001.html';
            $this->out[$province] = $this->getCity($url, $this->out[$province]);
            break;
        }
    }

    public function out()
    {
//        print_r($this->out);
    }
}


$parser = new Parser();
$parser->load(INDEX_URL);
$parser->out();
