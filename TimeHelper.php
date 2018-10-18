<?php
/*define('DEBUG',1);
$timeHelper = new TimeHelper();
var_dump(TimeHelper::getTimeFormat('Y-m-d H:i:s'));*/

/** 用于获取当前时间戳 显示脚本运行时间等功能 后加毫秒级定时器
 * Class TimeHelper
 */
class TimeHelper{

    //定时器相关
    /*public static $list = [];
    public static $timerTimeStampMicro = 0;*/

    /** 类实例化的时间(秒级)
     * @var
     */
    private $startTimeStamp;

    /** 类实例化的时间(毫秒级)
     * @var
     */
    private $startTimeStampMicro;

    /**
     * TimeHelper constructor.
     */
    public function __construct(){
        list($t1,$t2) = explode(' ',microtime());
        $this->startTimeStamp =(int)$t2;
        $this->startTimeStampMicro = (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
    }

    /** 脚本运行完以后 显示脚本运行时间
     *
     */
    public function __destruct(){
        if(defined('DEBUG')&&DEBUG){
            echo '这段代码运行了'.$this->getRunTime().'毫秒';
        }
    }

    /** 获取脚本开始时间戳
     * @return int
     */
    public function getStartTimeStamp(){
        return $this->startTimeStamp;
    }

    /** 获取脚本开始毫秒级时间戳
     * @return float
     */
    public function getStartTimeStampMicro(){
        return $this->startTimeStampMicro;
    }

    /** 获取当前时间戳
     * @return int
     */
    public static function getTimeStampNow(){
        return time();
    }

    /** 获取当前毫秒级时间戳
     * @return float
     */
    public static function getTimeStampMicroNow(){
        list($t1,$t2) = explode(' ',microtime());
        return (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
    }

    /** 获取脚本运行时间
     * @return int
     */
    public function getRunTime(){
        return (int)($this->getTimeStampMicroNow()-$this->startTimeStampMicro);
    }

    /** 获取格式化的时间
     * @param $format
     * @return false|string
     */
    public static function getTimeFormat($format){
        return date($format);
    }

    //定时器相关
    /*public static function add($interval,$fuc,$param=[]){ //增加定时器
        $nextTime = self::getTimeStampMicroNow() + $interval;
        $nextTimer = [];
        $nextTimer['next'] = $nextTime;
        $nextTimer['interval'] = $interval;
        $nextTimer['fuc'] = $fuc;
        $nextTimer['param'] = $param;
        self::$list[] = $nextTimer;
        return true;
    }

    public static function isNextTimer(){ //判断是不是在同一毫秒内
        $saveTimeStampMicroNow = self::getTimeStampMicroNow();
        if($saveTimeStampMicroNow>self::$timerTimeStampMicro){
            self::$timerTimeStampMicro = $saveTimeStampMicroNow;
            return true;
        }
        return false;
    }

    public static function run(){ //执行定时器
        $runTime = self::getTimeStampMicroNow();
        foreach(self::$list as $k=>$v){
            if($runTime > $v['next']){
                call_user_func_array($v['fuc'],$v['param']);
                self::$list[$k]['next']=$v['next']+$v['interval'];
            }
        }
        return true;
    }*/


}

// 使用实例
/*TimeHelper::add(1000,function (){
    echo '这是1秒定时器,毫秒时间戳是'.TimeHelper::getTimeStampMicroNow().PHP_EOL;
});
TimeHelper::add(500,function (){
    echo '这是0.5秒定时器,毫秒时间戳是'.TimeHelper::getTimeStampMicroNow().PHP_EOL;
});
while(true){
    if(TimeHelper::isNextTimer()){
        TimeHelper::run();
    }
}*/

