<?php
namespace Crond;

final class Parse
{
    private $sec;
    private $min;
    private $hour;
    private $month;
    private $day;
    private $week;
    private $crontab;

    public function __construct()
    {

    }

    /**
     * 初始化
     * @param  string $crontab [description]
     * @return [type]          [description]
     */
    public function init($crontab = '')
    {
        $this->crontab = $crontab;
        if (empty($crontab)) {
            throw new \Exception('Crontab Time Formate Invalid');
        }
        $crontabArr = preg_split('/\s+/', $this->crontab);
        list($this->sec, $this->min, $this->hour, $this->day, $this->month, $this->week) = $crontabArr;
        return $this;
    }

    /**
     * 确定是否执行
     * @return [type] [description]
     */
    public function exec()
    {
        $jobsTime = [
            'sec'   => $this->sec(),
            'min'   => $this->min(),
            'hour'  => $this->hour(),
            'day'   => $this->day(),
            'month' => $this->month(),
            'week'  => $this->week(),
        ];

        $sec   = intval(date('s'));
        $min   = intval(date('i'));
        $hour  = intval(date('G'));
        $day   = intval(date('j'));
        $month = intval(date('n'));
        $week  = intval(date('w'));

        if ($this->conform($sec,   $jobsTime['sec'])   &&
            $this->conform($min,   $jobsTime['min'])   &&
            $this->conform($hour,  $jobsTime['hour'])  &&
            $this->conform($day,   $jobsTime['day'])   &&
            $this->conform($month, $jobsTime['month']) &&
            $this->conform($week,  $jobsTime['week'])
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 当前时间是否在计划任务时间点
     * @param  [type] $item [description]
     * @param  [type] $list [description]
     * @return [type]       [description]
     */
    private function conform($item, $list)
    {
        if (!$list) {
            return true;
        }
        return in_array($item, $list) ? true : false;
    }

    /**
     * 解析crontab字段
     * @param  [type] $str   [description]
     * @param  [type] $start [description]
     * @param  [type] $end   [description]
     * @return [type]        [description]
     */
    private function parse($str, $start, $end)
    {
        if ('*' == $str) {
            return range($start, $end);
        }

        //解析英文逗号
        if (strpos($str, ',')) {
            $numbers = $this->comma($str);
            $times   = [];
            foreach ($numbers as $key => $number) {
                if (!is_numeric($number)){
                    if (strpos($str, '/')) {
                        $result = $this->slash($number);
                        $times  = $this->bars($result[0], $start, $end, $result[1]);
                    }
                    unset($numbers[$key]);
                }
            }
            return array_merge($numbers, $times);
        }

        //解析反斜线
        if (strpos($str, '/')) {
            $result = $this->slash($str);
            if ('*' == $result[0]) {
                return range($start, $end, $result[1]);
            }
            if (strpos($result[0], '-')) {
                return $this->bars($result[0], $start, $end, $result[1]);
            }
        }

        //解析横杠
        if (strpos($str, '-')) {
            return $this->bars($str, $start, $end);
        }

        //如果是数字 直接返回
        if (is_numeric($str)){
            return [$str];
        }

        return false;
    }

    /**
     * 解析逗号
     * @param  [type] $str [description]
     * @return [type]      [description]
     */
    private function comma($str)
    {
        return explode(',', $str);
    }

    /**
     * 解析反斜线
     * @param  [type] $str [description]
     * @return [type]      [description]
     */
    private function slash($str)
    {
        return explode('/', $str);
    }

    /**
     * 解析横杠
     * @param  [type]  $str   [description]
     * @param  [type]  $start [description]
     * @param  [type]  $end   [description]
     * @param  integer $step  [description]
     * @return [type]         [description]
     */
    private function bars($str, $start, $end, $step = 1)
    {
        list($_start, $_end) = explode('-', $str);
        if (!is_numeric($_start) || !is_numeric($_end)) {
            return [];
        }
        if ($_start < $_end){
            return range($_start, $_end, $step);
        }
        $result = [];
        for ($i = $_start; $i <= $end; $i += $step) {
            $result[] = $i;
        }
        for ($j = ($end - $_start + $step -1); $j <= $_end; $j += $step) {
            $result[] = $j;
        }
        return $result;
    }

    /**
     * 解析秒
     * @return [type] [description]
     */
    private function sec()
    {
        $result = $this->parse($this->sec, 0, 59);
        return empty($result) ? false : $result;
    }

    /**
     * 解析分钟
     * @return [type] [description]
     */
    private function min()
    {
        $result = $this->parse($this->min, 0, 59);
        return empty($result) ? false : $result;
    }

    /**
     * 解析小时
     * @return [type] [description]
     */
    private function hour()
    {
        $result = $this->parse($this->hour, 0, 23);
        return empty($result) ? false : $result;
    }

    /**
     * 解析日期
     * @return [type] [description]
     */
    private function day()
    {
        $result = $this->parse($this->day, 1, date('t'));
        return empty($result) ? false : $result;
    }

    /**
     * 解析月份
     * @return [type] [description]
     */
    private function month()
    {
        $result = $this->parse($this->month, 1, 12);
        return empty($result) ? false : $result;
    }

    /**
     * 解析星期
     * @return [type] [description]
     */
    private function week()
    {
        $result = $this->parse($this->week, 0, 6);
        return empty($result) ? false : $result;
    }
}
