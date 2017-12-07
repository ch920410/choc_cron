<?php
namespace Crond;

final class Process
{
    /**
     * 守护进程模式
     */
    public function deamon()
    {
        return \swoole_process::daemon();
    }

    /**
     * 信号注册
     */
    public function signal($signo, callable $closure)
    {
        if (!$closure instanceof \Closure){
            return false;
        }
        return \swoole_process::signal($signo, $closure);
    }

    /**
     * 回收任务
     */
    public function destroy($pid, $signo = SIGTERM)
    {
        return \swoole_process::kill($pid, $signo = SIGTERM);
    }

    /**
     * 创建新进程
     */
    public function create(callable $closure)
    {

       if (!$closure instanceof \Closure) {
            throw new \Exception('Not Closure');
       }

       $process = new \swoole_process($closure);
       $pid     = $process->start();
       return $pid;
    }

    /**
     * 设置任务进程名称
     */
    public function rename($processName)
    {
        return swoole_set_process_name($processName);
    }

    /**
     * 设置定时器
     */
    public function alarm($time = 1000000)
    {
        return \swoole_process::alarm($time);
    }

    /**
     * 非阻塞模式模式回收进程
     */
    public function wait($mode = false)
    {
        return \swoole_process::wait($mode);
    }
}
