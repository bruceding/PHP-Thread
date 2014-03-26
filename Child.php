<?php
include('Main.php');
/**
 * Thread_Child 
 * 子线程
 * 
 * @extends Thread_Main
 * @abstract
 * @package 
 * @version $id$
 * @copyright 1997-2005 The PHP Group
 * @author Tobias Schlitt <toby@php.net> 
 * @license PHP Version 3.0 {@link http://www.php.net/license/3_0.txt}
 */
abstract class Thread_Child extends Thread_Main{

    public function __construct() {
        $this->isParent = false;
    }

    /**
     * process 
     * 任务处理函数 
     *
     * @abstract
     * @access public
     * @return void
     */
    abstract public function process();

    /**
     * doTask 
     * 子进程开始处理任务
     * 
     * @access public
     * @return void
     */
    public function doTask() {

        $this->isRunning = true;
        $this->_registerSigHandler();
        echo $this->pid . ' start to process ' . PHP_EOL;
        $this->process();
        echo $this->pid . ' process exited' . PHP_EOL;
        exit;
    }

    /**
     * _registerSigHandler 
     * 注册子进程信息号
     * 
     * @access protected
     * @return void
     */
    protected function _registerSigHandler() {
    
        // 退出信号忽略
        pcntl_signal(SIGINT, SIG_IGN);
        pcntl_signal(SIGHUP, SIG_IGN);
        pcntl_signal(SIGQUIT, SIG_IGN);
        pcntl_signal(SIGTERM, array($this, '_sigHandler'));
    }

    /**
     * _sigHandler 
     * 子进程信号处理
     * 
     * @param mixed $sig 
     * @access protected
     * @return void
     */
    protected function _sigHandler($sig) {
        switch(intval($sig)) {
            case SIGTERM:
                $this->stop();
                break;
            default:
                break;
        }
    }

    /**
     *  stop 
     *  接受到SIGTERM信号时，结束子进程
     *
     * 
     * @access public
     * @return void
     */
    public  function stop() {
        $this->isRunning = false;
    }
}

