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
            $text = trim(substr($v, strripos($v, ">") + 1));
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
                $begin = stripos($k, '/') + 1;
                $end = strripos($k, '.');
                $code = substr($k, $begin,  $end - $begin);
                $result[$v] = [$code => $this->getCity($baseUrl . $k, $result[$v])];
            }
        }

        return $result;
    }

    public function load($url)
    {
        $result = $this->getProvince($this->loadContent($url));
        $baseUrl = substr($url, 0, strripos($url, '/') + 1);

        $this->outProvince($result);

        foreach ($result as $k => $v) {
            $index = stripos($k, '.');
            $key = substr($k, 0, $index);

            $this->out[$v] = [$key => $this->getCity($baseUrl . $k, [])];
            $cityArray = [];
            $cityArray = $this->formatCity($this->out[$v][$key], $cityArray, $key);
            $cityArray = $this->rebuildCityChild($cityArray);
            $cityArray = $this->fillTopChild($cityArray);
            $this->outCity($cityArray, $key);
        }
    }

    private function rebuildCityChild(array $dataArray)
    {
        $childArray = [];
        foreach ($dataArray as $k => $v) {
            if (intval($k) > 0) {
                if (isset($dataArray[$k]['child'])) {
                    $childArray[$k] = $dataArray[$k]['child'];
                }
            }
        }


        foreach ($childArray as $k => $data) {
            $result = [];
            foreach ($data as $child) {
                $result[$child] = $child;
            }

            foreach ($data as $child) {
                if (isset($childArray[$child])) {
                    $childList = $childArray[$child];
                    foreach ($childList as $v) {
                        if (isset($result[$v])) {
                            unset($result[$v]);
                        }
                    }
                }
            }

            $dataArray[$k]['child'] = array_keys($result);
        }

        return $dataArray;
    }

    private function fillTopChild(array $dataArray)
    {
        $result = $dataArray;

        $childMap = [];
        $needCheckMap = [];
        foreach ($dataArray as $k => $v) {
            if (isset($v['child'])) {
                foreach ($v['child'] as $child) {
                    $childMap[$child] = $child;
                }

                $needCheckMap[$k] = $k;
            }
        }

        foreach ($needCheckMap as $k) {
            if (!isset($childMap[$k])) {
                $result['child'][] = $k;
            }
        }

        return $result;
    }

    private function outProvince(array $data)
    {
        $fp = fopen($this->getOutDir() . '/province.php', 'wb');
        fwrite($fp, "<?php\n");
        fwrite($fp, "//DO NOT EDIT THIS FILE.\n\n");
        fwrite($fp, "return  [\n");
        foreach ($data as $k => $v) {
            $index = stripos($k, '.');
            $key = substr($k, 0, $index);
            fwrite($fp, sprintf("        '%s'  => '%s',\n", $key, $v));
        }

        fwrite($fp, "        ];\n\n");
        fclose($fp);
    }

    private function formatText($str)
    {
        if (mb_substr($str, -3) == '办事处') {
            $str = mb_substr($str, 0, -3);
        } elseif (mb_substr($str, -3) == '街道办') {
            $str = mb_substr($str, 0, -1);
        }

        return $str;
    }

    private function formatCity(array $dataArray, array $result, $key)
    {
        foreach ($dataArray as $k => $v)
        {
            if (is_array($v)) {
                if (empty($v)) {
                    $result[$k] = ['name' => $this->formatText($key)];
                } else {
                    if ((intval($k) != 0) && ('市辖区' != $key) && ('县' != $key)) {
                        $result[$k] = ['name' => $this->formatText($key)];
                    }

                    $child = $this->formatCity($v, [], $k);
                    if (isset($result[$k])) {
                        $result[$k]['child'] = array_keys($child);
                    }

                    foreach ($child as $ck => $cv) {
                        $result[$ck] = $cv;
                    }
                }
            }
        }

        return $result;
    }

    private function getChildString(array $dataArray)
    {
        $result = '';
        foreach ($dataArray as $v) {
            $result .= "'{$v}',";
        }

        return substr($result, 0, -1);
    }

    private function outCity(array $dataArray, $key)
    {
        $fp = fopen($this->getOutDir() . "/city_{$key}.php", 'wb');
        fwrite($fp, "<?php\n");
        fwrite($fp, "//DO NOT EDIT THIS FILE.\n\n");
        fwrite($fp, "return  [\n");
        foreach ($dataArray as $k => $v) {
            $str = sprintf("        '%s'  => [\n", $k);
            if ($k == 'child') {
                $str .= "                " . $this->getChildString($v) . "\n";
            } else {
                foreach ($v as $key => $vv) {
                    if (is_array($vv)) {
                        $vv = '[' . $this->getChildString($vv) . '],';
                    } else {
                        $vv = "'{$vv}',";
                    }
                    $str .= sprintf("                '%s' => %s\n", $key, $vv);
                }
            }

            $str .= sprintf("                  ],\n", $k);
            fwrite($fp, $str);
        }

        fwrite($fp, "        ];\n\n");
        fclose($fp);
    }
}

set_time_limit(-1);
$dataDir = __DIR__ . '/../out';
$outDir = __DIR__ . '/../configs';
$parser = new Parser();
$parser->setDataDir($dataDir);
$parser->setOutDir($outDir);
$parser->load('http://www.stats.gov.cn/tjsj/tjbz/tjyqhdmhcxhfdm/2015/');
