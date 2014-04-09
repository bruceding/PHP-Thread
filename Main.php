<?php
 /**
  * Thread_Main 
  * 主进程类
  * 
  * @package 
  * @version $id$
  * @copyright 1997-2005 The PHP Group
  * @author bruce ding <dingjingdjdj@gmail.com> 
  * @license 
  */
 class Thread_Main {

    public $isRunning ;

    public $pid ;

    public $isParent = true;

    private $_child = array();

    private $_threads = array();

    /**
     * 配置类的引用 
     * @see Thread_Config
     */
    private $_config ;

    public function __construct() {
        $this->init();
    }

    public function init() {
        
        $this->isParent = true;
        $this->pid = getmypid();
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
     * @access protected
     * @return void
     */
    protected function _waitChild() {
          while( ($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0 ) {
               unset($this->_child[$pid]);
          }
    }

    /**
     * _fork 
     * 
     * @param mixed $thread 
     * @access protected
     * @return bool
     */
    protected  function _fork($thread) {

        if (!($thread instanceof Thread_Child || $thread instanceof Thread_IChild)) {
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

            try {
                $this->_quitExcessThreads();
                $this->_setUpThreads();
            } catch (Exception $e) {
                // 可以记录log，此处先忽略
            }
            pcntl_signal_dispatch();
            if (!$this->_child) {
                $this->stop();
            } 
            sleep(1);
        }
        if (defined('DEBUG')) {
            echo $this->pid . ' parent process exited ' . PHP_EOL;
        }
        return true;
    }

    /**
     * runWithConfig 
     * 通过配置文件开启多进程
     * 可以控制多进程的数量
     * 
     * @param Thread_Config $config 
     * @access public
     */
    public function runWithConfig(Thread_Config $config) {
        
        if (!$this->isParent) {
            return false;
        }

        if (!$config) {
            exit('config error');
        }

        $this->_config = $config;

        for ($i = 0; $i < $this->_config->classCount; $i++) {
            $threads[] = new $this->_config->className ();
        }

        return $this->run($threads);

    }

    /**
     * _setUpThreads 
     * 解析配置文件
     * 如果配置文件中的进程数量多于实际运行的进程，增加进程数量
     * 
     * @access private
     */
    private function _setUpThreads() {

        if ($this->_config) {
            $this->_config->parseConfigFile();
            if (count($this->_child) < $this->_config->classCount) {

                for ($i = count($this->_child); $i < $this->_config->classCount; $i++) {
                    $this->_threads[] = new $this->_config->className ();
                }
            } 
        }
    } 

    /**
     * _quitExcessThreads 
     * 解析配置文件
     * 如果配置文件中的数量小于运行的进程的数量，则关闭多余的进程
     * 
     * @access private
     */
    private function _quitExcessThreads() {
 
        if ($this->_config) {
            $this->_config->parseConfigFile();
            if (count($this->_child) > $this->_config->classCount) {
                $i = 0;
                foreach ($this->_child as $pid => $thread) {
                    if ($i ==  count($this->_child) - $this->_config->classCount) { 
                        break;
                    }

                    posix_kill($pid, SIGTERM);    
                    $i++;
                }
            }
            
        }
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
        if (defined('DEBUG')) {
            echo $this->pid . ' parent process exiting... ' . PHP_EOL;
        }
//        unlink($this->_pidFile);
        $this->isRunning = false;
    }

}
