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

//    protected $_pidFile = '/var/tmp/';

    private $_iniFile ;
    private $_configOptions = array();

    public function __construct() {
        $this->init();
    }

    public function init() {
        
        $this->isParent = true;
        $this->pid = getmypid();
    }

    public function setConfigFile($configFile) {
        
        if (!is_file($configFile)) {
            exit('not find config file');
        }
        
        if (trim(pathinfo($configFile, PATHINFO_EXTENSION)) != 'ini') {
            exit('config file must be ini file');
        }

        $this->_iniFile = $configFile;

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
        echo $this->pid . ' parent process exited ' . PHP_EOL;
        return true;
    }

    /**
     * runWithConfig 
     * 通过配置文件开启多进程
     * 可以控制多进程的数量
     * 
     * @param mixed $configFile 
     * @access public
     */
    public function runWithConfig($configFile) {
        
        if (!$this->isParent) {
            return false;
        }

        $this->setConfigFile($configFile);
        list($childName, $childCount) = $this->_parseConfigFile();

        for ($i = 0; $i < $childCount; $i++) {
            $threads[] = new $childName();
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

        if ($this->_iniFile) {
            list($childName, $childCount) = $this->_parseConfigFile();
            if (count($this->_child) < $childCount) {

                for ($i = count($this->_child); $i < $childCount; $i++) {
                    $this->_threads[] = new $childName();
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
 
        if ($this->_iniFile) {
            list($childName, $childCount) = $this->_parseConfigFile();
            if (count($this->_child) > $childCount) {
                $i = 0;
                foreach ($this->_child as $pid => $thread) {
                    if ($i ==  count($this->_child) - $childCount) { 
                        break;
                    }

                    posix_kill($pid, SIGTERM);    
                    $i++;
                }
            }
            
        }
    }
    /**
     * _parseConfigFile 
     * 解析配置文件
     *  + class : 运行的子进程类名
     *  + count : 进程的数量
     *
     * @access private
     */
    private function _parseConfigFile() {
    
        $this->_configOptions = parse_ini_file($this->_iniFile);
        
        if (!$this->_configOptions['class']) {
            throw new Exception('not find class config');
        }

        $childName = $this->_configOptions['class'];

        if (!class_exists($childName)) {
            throw new Exception ("$childName not exists");
        }
        $childClass = new $childName();

        if (!$childClass) {
            throw new Exception('create class faile ' . $childName);
        }

        $childCount = intval($this->_configOptions['count']) ? intval($this->_configOptions['count'])  : 1;

        return array($childName, $childCount);
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
//        unlink($this->_pidFile);
        $this->isRunning = false;
    }

}
