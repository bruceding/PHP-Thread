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
# 注意事项

在子类方式中，子进程对信号进行了处理，对退出信号进行忽略。
父进程接受到子进程时，首先会对子进程进程关闭。若不需要默认行为，子进程可以覆写stop。
