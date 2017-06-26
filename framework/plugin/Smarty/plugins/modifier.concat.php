<?php
/**
 * Smarty plugin
 * Author: cty@20140604
 * str连接
 * @author Author: cty@20140604
 * @param  str|arr $str     输入
 *return: string
*/
function smarty_modifier_concat($str)
{
    $arcArr = func_get_args();
    unset($arcArr[0]);

    foreach($arcArr as $_s){
        $str .= $_s;
    }
    return $str;
} 

