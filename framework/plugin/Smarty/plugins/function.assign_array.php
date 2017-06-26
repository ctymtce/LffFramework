<?php

/*
* Smarty plugin
* -------------------------------------------------------------
* Type: function
* Name: assign_array
* Version: 1.1
* Author: Jens Lehmann <jenslehmann@goldmail.de>
*   Gary Loescher <garyl@redrim.com>
* Credits: Monte Ohrt <monte@ispi.net>
* Purpose: assign an array to a template variable
* Input: var = name of the array
* values = list of values (seperated by delimiter)
* delimiter = value delimiter, default is ","
*   associative = yes or no, default "no"
* Examples: {assign_array var="foo" values="bar1,bar2"}
* {assign_array var="foo" values="bar1;bar2;bar3"
delimiter=";"}
*   {assign_array var="foo"
values="bar1key,bar1val" associative="yes"}
* -------------------------------------------------------------
*/
function smarty_function_assign_array($params, &$smarty)
{
    extract($params);
    if(!isset($value) || !isset($var))return;
    eval('$val = '.$value.';');
    // print_r($val);

    $smarty->assign($var, $val);
    /*
    return;
    // print_r($params);
    // var_dump($value);

    if (empty($var)) {
    $smarty->trigger_error("assign_array: missing 'var'
    parameter");
    return;
    }
    if (!in_array('value', array_keys($params))) {
    $smarty->trigger_error("assign_array: missing 'values'
    parameter");
    return;
    }

    if(empty($delimiter)) {
    $delimiter = ',';
    }

    if(empty($associative)) {
    $associative = 'no';
    }

    $value_array = explode($delimiter,$value);


    switch($associative) {
    case 'no':
    $smarty->assign($var, $value_array);
    break;

    case 'yes':
    $hash = array();
    for( $i=0,$j=count($value_array); $i < $j; $i +=
    2 ) {
    $key = $value_array[$i];
    $value = array_key_exists($i+1,
    $value_array) ? $value_array[$i+1] : NULL;
    $hash[$key] = $value;
    }

    $smarty->assign($var, $hash);
    break;

    default:
    $smarty->trigger_error("assign_array: parameter
    'associative' must be 'yes', 'no', or absent");
    }*/
}
