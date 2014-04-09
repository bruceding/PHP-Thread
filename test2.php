<?php
//declare(ticks = 1);
include("ThreadTest.php");
include("Config.php");

define('DEBUG', 1);

$configFile = dirname(__FILE__) . '/testThread.ini';


$config = new Thread_Config($configFile);
$main = new Thread_Main();

$main->runWithConfig($config);
