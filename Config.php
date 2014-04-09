<?php

/**
 * Thread_Config 
 * 配置类
 * 
 * @package 
 * @version $id$
 * @copyright 1997-2005 The PHP Group
 * @author bruce ding <dingjingdjdj@gmail.com> 
 * @license 
 */
class Thread_Config {

    private $_iniFile ;
    private $_configOptions = array();
    public $className;
    public $classCount;

    public function __construct($configFile) {
        
        $this->setConfigFile($configFile);
        $this->parseConfigFile();
    }

    /**
     * setConfigFile 
     * 
     * @param mixed $configFile 
     * @access public
     * @return void
     */
    public function setConfigFile($configFile) {
        
        if (!is_file($configFile)) {
            exit('not find config file');
        }
        
        if (strtolower(trim(pathinfo($configFile, PATHINFO_EXTENSION))) != 'ini') {
            exit('config file must be ini file');
        }

        $this->_iniFile = $configFile;

    } 

    /**
     * _parseConfigFile 
     * 解析配置文件
     *  + class : 运行的子进程类名
     *  + count : 进程的数量
     *
     * @access public
     */
    public function parseConfigFile() {
    
        $this->_configOptions = parse_ini_file($this->_iniFile);
        
        if (!$this->_configOptions['class']) {
            throw new Exception('not find class config');
        }

        $className = $this->_configOptions['class'];

        if (!class_exists($className)) {
            throw new Exception ("$className not exists");
        }
        $childClass = new $className();

        if (!$childClass) {
            throw new Exception('create class faile ' . $className);
        }

        $classCount = intval($this->_configOptions['count']) ? intval($this->_configOptions['count'])  : 1;

        $this->className = $className;
        $this->classCount = $classCount;
    }
}
