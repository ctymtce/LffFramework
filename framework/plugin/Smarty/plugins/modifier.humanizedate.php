<?php
/**
 * Smarty plugin
 * 
 */

function smarty_modifier_humanizedate($time, $showzero=false)
{
    $now  = time();
    $secs = $now-$time;
    if($secs < 0) {
        $secs = abs($secs);
        $days = floor($secs/86400);
        if($days > 0) $secs -= $days*86400;

        $hours = floor($secs/3600);
        if($hours > 0) $secs -= $hours*3600;

        $mins = floor($secs/60);
        if($mins > 0) $secs -= $mins*60;

        $htime = "{$secs}秒";
        if($mins  > 0) $htime = "{$mins}分".$htime;
        if($hours > 0) $htime = "{$hours}小时".$htime;
        if($days  > 0) $htime = "{$days}天".$htime;

        return $htime;
    }
    if(!$showzero && $time < 10)return '';
    $days = (strtotime(date("Y-m-d 23:59:59")) - $time)/86400; //计算天只能按当前的最后一刻来计算
    // var_dump($days);
    if(date('Y',$now) != date('Y',$time)){//跨年
        return date('Y年m月d号',$time);
    }else{
        // var_dump($days);
        if($days<1){
            if($secs<60)return '刚刚';
            elseif($secs<3600)return floor($secs/60)."分钟前";
            else return floor($secs/3600)."小时前";
        }else if($days<2){
            $hour=date('G',$time);
            return "昨天".$hour.'点';
        }elseif($days<3){
            $hour=date('G',$time);
            return "前天".$hour.'点';
        }else{
            return date('Y年m月d号',$time);
        }
    }
} 
