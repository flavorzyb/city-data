<?php

class Parser
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
    public function load($url)
    {
        $md5Str = md5($url);
        $path =  $this->getOutDir() . '/' . substr($md5Str, 0, 2) . '/' . substr($md5Str, 2, 2);
        $fullPath = $path . '/' . $md5Str;
        if (!is_file($fullPath)) {
            die("url({$url}) file($fullPath) is not exist.");
        }

        $content = trim(file_get_contents($fullPath));

    }
}
