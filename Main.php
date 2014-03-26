<?php
 /**
  * Thread_Main 
  * 主线程
  * 
  * @package 
  * @version $id$
  * @copyright 1997-2005 The PHP Group
  * @author Tobias Schlitt <toby@php.net> 
  * @license PHP Version 3.0 {@link http://www.php.net/license/3_0.txt}
  */
 class Thread_Main {

    public $isRunning ;

    public $pid ;

    public $isParent = true;

//    public $parent = null;

    private $_child = array();

    private $_threads = array();

    protected $_pidFile = '/var/tmp/';

    public function __construct() {
        $this->init();
    }

    public function init() {
        
        $this->isParent = true;
        $pid = getmypid();
        $this->pid = $pid;
        $this->_pidFile = $this->_pidFile . "main_{$pid}.pid";
    }

    /**
     * _registerSigHandler 
     * 信号注册
     * 
     * @access private
     * @return void
     */
    protected function _registerSigHandler() {
        pcntl_signal(SIGTERM, array($this, '_sigHandler'));
        pcntl_signal(SIGHUP, array($this, '_sigHandler'));
        pcntl_signal(SIGCHLD, array($this, '_sigHandler'));
        pcntl_signal(SIGINT, array($this, '_sigHandler'));
        pcntl_signal(SIGQUIT, array($this, '_sigHandler'));
    }

    /**
     * _sigHandler 
     * 信号处理
     * 
     * @param mixed $sig 
     * @access private
     * @return void
     */
    protected function _sigHandler($sig) {
        switch(intval($sig)) {
            case SIGCHLD:
                $this->_waitChild();
                break;
            case SIGINT:
            case SIGQUIT:
            case SIGHUP:
            case SIGTERM:
                $this->_cleanup();
                break;
            default:
                break;

        }
    }

    /**
     * _waitChild 
     * 处理退出的子进程
     * 
     * @access private
     * @return void
     */
    private function _waitChild() {
          while( ($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0 ) {
               unset($this->_child[$pid]);
          }
    }

    /**
     * _fork 
     * 
     * @param mixed $thread 
     * @access private
     * @return bool
     */
    private  function _fork($thread) {

        if (!($thread instanceof Thread_Child)) {
            return false;
        }
        $pid = pcntl_fork();
        if ($pid == -1) {
            return false;
        } else if ($pid) { //父进程
            $this->_child[$pid] = $thread;
        } else {
            $pid= getmypid();
            $thread->pid = $pid;
            $thread->doTask();
        }
            return true;
    }

    /**
    public function addThread(Thread_Child $thread) {
        
        if ($this->isParent) {
            echo 'add thread';
            $this->_threads[] = $thread;
        }
    }
    **/

    /**
     * run 
     * 主进程运行，并fork子进程 
     *
     * @param array $threads 
     * @access public
     * @return void
     */
    public function run($threads = array()) {
    
        if (!$this->isParent) {
            return false;
        }

        $this->_registerSigHandler();
        $fp = fopen($this->_pidFile, 'w+');
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            exit('already running');
        }
        $this->isRunning = true;
        if ($threads) {
            $this->_threads = array_merge($this->_threads, $threads); 
        }
        while($this->isRunning) {
            if ($this->_threads) {
                foreach ($this->_threads as $key =>  $thread) {
                    $this->_fork($thread); 
                    unset($this->_threads[$key]);
                }
            }

            pcntl_signal_dispatch();
            if (!$this->_child) {
                $this->stop();
            } 
            sleep(1);
        }
        echo $this->pid . ' parent process exited ' . PHP_EOL;
        return true;
    }

    /**
     * _cleanup 
     * 父进程接受到退出信号时，给子进程发送SIGTERM信号
     * 
     * @access protected
     * @return void
     */
    protected function _cleanup() {
        if (!$this->isParent) {
            return false;
        }
        if (count($this->_child)) {
            foreach ($this->_child as $pid => $thread) {
                posix_kill($pid, SIGTERM);
            }
        }
    }

    /**
     * stop 
     * 结束主进程 
     *
     * @access public
     * @return void
     */
    public function stop() {
        echo $this->pid . ' parent process exiting... ' . PHP_EOL;
        unlink($this->_pidFile);
        $this->isRunning = false;
    }

}
