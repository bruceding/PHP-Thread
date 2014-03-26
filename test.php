<?php
//declare(ticks = 1);
include("ThreadTest.php");

$threads[]  = new ThreadTest();
$threads[]  = new ThreadTest();

$main = new Thread_Main();

$main->run($threads);
