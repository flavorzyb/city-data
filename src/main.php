<?php
require_once 'Spider.php';

set_time_limit(-1);
$outDir = __DIR__ . '/../out';
$spider = new Spider();
$spider->setOutDir($outDir);
$spider->setUrlMapPath($outDir.'/url.map');
$spider->load('http://www.stats.gov.cn/tjsj/tjbz/tjyqhdmhcxhfdm/2015/');
