<?php
/**
 * Smarty plugin
 * 
 */

function smarty_modifier_decode($str, $tocode='utf-8', $fromcode='gbk')
{
    return iconv($fromcode, "{$tocode}//TRANSLIT//IGNORE", $str);
} 
