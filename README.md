PHP-Thread
==========
PHP运行多线程工具

# 简介

基于pcntl，可以对多个子类线程进行管理。

# 用法

本工具提供两个方式用来实现子线程：继承子类或者实现接口。

继承子类

```
class ThreadTest extends Thread_Child {

    public function process() {
        $i = 0;
        while ($this->isRunning && $i < 10) {
            pcntl_signal_dispatch();
            echo $this->pid . "\t" . $i . PHP_EOL;
            sleep(rand(1,3));
            $i++;
        }

    }

}
```
实现接口

```
class IChildTest implements Thread_IChild {
    
    public $pid;
    public function doTask() {
        
        for($i = 0; $i < 10; $i++) {
            echo $this->pid . "\t" . $i . PHP_EOL;
        }
        exit;
    }
}
```

程序运行

```
$threads[]  = new ThreadTest();
$threads[]  = new IChildTest();

$main = new Thread_Main();

$main->run($threads);
```

Thread_Main提供两个接口运行子进程:run和runWithConfig。
run只能运行指定数量的子进程。一旦运行run，子进程数量无法改变。
runWithConfig通过ini类型的配置文件，来运行子进程。可以通过此配置文件来动态的变更子进程的数量。

配置文件样式如下:

```
[class]
class = ThreadTest
count = 2 
```

程序运行示例

```
$configFile = dirname(__FILE__) . '/testThread.ini';

$main = new Thread_Main();

$main->runWithConfig($configFile);
```
# 注意事项

在子类方式中，子进程对信号进行了处理，对退出信号进行忽略。
父进程接受到子进程时，首先会对子进程进程关闭。若不需要默认行为，子进程可以覆写stop。
