<?php
//declare(ticks = 1);
include("ThreadTest.php");

$configFile = dirname(__FILE__) . '/testThread.ini';


$main = new Thread_Main();

$main->runWithConfig($configFile);
