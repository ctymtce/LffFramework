<?php
/**
 * Smarty plugin
 * 
 */

function smarty_modifier_humanizetime($long)
{
    $secs = abs($long);

    $days = floor($secs/86400);
    if($days > 0) $secs -= $days*86400;

    $hours = floor($secs/3600);
    if($hours > 0) $secs -= $hours*3600;

    $mins = floor($secs/60);
    if($mins > 0) $secs -= $mins*60;

    $htime = $secs . "秒";
    if($mins  >  0) $htime = $mins  . "分"  . $htime;
    if($hours >  0) $htime = $hours . "时"  . $htime;
    if($days  >  0) $htime = $days  . "天"  . $htime;

    if($long < 0){
        $htime = '已过'.$htime;
    }
    return $htime;
} 
