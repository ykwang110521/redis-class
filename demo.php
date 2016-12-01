<?php
// 引用redis操作类
require_once('redis.class.php');
$config=array(
    'host' => '192.168.10.10',
    'port' => '6379',
    'prefix' => '',
    'pwd'=>'',
    'auto_close' => false,
    'ignore_get' => isset($_GET['nocache'])
);
C_Redis::register($config);
$key = 'string.test';
//$ret = C_Redis::set($key,'testabc');
$ret = C_Redis::get($key);
var_dump($ret);exit;