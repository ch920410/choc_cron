<?php
namespace Crond;

final class Crontab
{
    protected $pidFile     = '/tmp/crond_master.pid';
    private static $config = [];
    private $process       = null;
    private $parser        = null;
    // 记录有效的task
    private $taskPool      = [];
    // 记录进程pid运行的task
    private $pidPool       = [];
    private $command       = '';
    private $instance      = '';
    Private $timeout       = 3600;

    public function __construct($application = null)
    {
        if (empty(self::$config)) {
            self::$config   = Config::get_crond_conf();
            $this->command  = self::$config['crontab']['command'];
            $this->instance = self::$config['crontab']['instance'];
            $this->timeout  = self::$config['crontab']['timeout'];
        }
        $this->process = new \Crond\Process();
        $this->parser  = new \Crond\Parse();
    }

    /**
     *
     * 启动Crontab
     */
    public function run()
    {
        if (file_exists($this->pidFile)) {
            $this->log("Crontab Service \033[31;40m [Already Exists] \033[0m");
            return false;
        }
        $this->process->deamon();
        $this->process->rename('CrondMaster');
        $pid = getmypid();
        file_put_contents($this->pidFile, $pid);
        $this->log("Crontab Service \033[31;40m [Start Success] \033[0m");
        swoole_timer_tick(1000, function() {
            $this->signal();
        });
    }

    /**
     * 关闭
     * @return [type] [description]
     */
    public function stop()
    {
        if (!file_exists($this->pidFile)) {
            $this->log("Crontab Service Pid File Can \033[31;40m [Not Find] \033[0m");
            return false;
        }
        $pid = file_get_contents($this->pidFile);
        if (!posix_kill($pid, 15)) {
            $this->log("Crontab Service \033[31;40m [Stop Failed] \033[0m");
            return false;
        }
        unlink($this->pidFile);
        usleep(50000);
        $this->log("Crontab Service \033[31;40m [Stop Success] \033[0m");
        return true;
    }

    /**
     * 重新加载
     * @return [type] [description]
     */
    public function reload()
    {
        if (!file_exists($this->pidFile)) {
            $this->log("Crontab Service Pid File Can \033[31;40m [Not Find] \033[0m");
            return false;
        }
        $pid = file_get_contents($this->pidFile);
        if (!posix_kill($pid, 10)) {
            $this->log("Crontab Service \033[31;40m [Reload Failed] \033[0m");
            return false;
        }
        $this->log("Crontab Service \033[31;40m [Reload Success] \033[0m");
        return true;
    }

    /**
     * 处理任务
     * @return [type] [description]
     */
    private function signal()
    {
        $this->taskPool = $this->tasks();
        foreach ($this->taskPool as $task) {
            if (in_array($task, array_column($this->pidPool, 'task_name'))) {
                continue;
            }
            $this->deal($task);
        }
        $this->wait();
    }

    /**
     * 获取可处理的任务
     * @return [type] [description]
     */
    private function tasks()
    {
        $result = [];
        foreach (self::$config['jobs'] as $job) {
            if ($this->parser->init($job['crond'])->exec()) {
                $result[] = $job['router'];
            }
        }
        return $result;
    }

    /**
     * 处理一个任务
     * @param  [type] $task [description]
     * @return [type]       [description]
     */
    private function deal($task)
    {
        try {
            list($router, $number) = explode(' ', trim($task));
            $number    = empty(intval($number)) ? 1 : intval($number);
            for ($i = 0; $i < $number; $i++) {
                $task = $router . '_' . $i;
                $pid  = $this->process->create(function (\swoole_process $process) use ($task, $router , $i) {
                    $process->exec($this->command, [$this->instance, "request_uri={$router}", "process_num={$i}"]);
                });
                $this->pidPool[$pid] = [
                    'begin_time' => time(),
                    'task_name'  => $task,
                ];
                unset($task);
            }
        } catch (\Exception $e) {
            throw new \Exception("Task Processing Failed:" . $e->getMessage());
        }
    }

    /**
     * 等待任务结束并回收
     * @return [type] [description]
     */
    private function wait()
    {
        while(true) {
            $result = $this->process->wait();
            if (!isset($result['pid'])) {
                $nowTime = time();
                foreach ($this->pidPool as $pid => $item) {
                    if ($nowTime - $item['begin_time'] > $this->timeout) {
                        $this->process->destroy($pid);
                    } else {
                        continue;
                    }
                }
                break;
            }
            unset($this->pidPool[$result['pid']]);
        }
    }


    /**
     * 记录日志等
     * @param  [type] $msg [description]
     * @return [type]      [description]
     */
    public function log($msg)
    {
        echo $msg . PHP_EOL;
    }
}
