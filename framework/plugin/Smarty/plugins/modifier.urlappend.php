<?php
/**
 * Smarty plugin
 * 
 */
function smarty_modifier_urlappend($url, $k, $v=null)
{
    /*
    Array
    (
        [scheme] => http
        [host] => www.domain.me
        [path] => /project/material/
        [query] => partionid=19&cateid=30
        [fragment] => aaa
    )*/
    // $url = 'http://www.domain.me/project/material/?partionid=19&cateid=30#aaa';
    if(!$url){
        $url = isset($_SERVER['REQUEST_URI'])?$_SERVER['REQUEST_URI']:'';
    }
    $uriArr = parse_url($url);
    if('#' == $k){
        if(is_null($v))
            unset($uriArr['fragment']);
        else
            $uriArr['fragment'] = $v;
    }else{
        $paraArr = array();
        if(isset($uriArr['query'])){
            parse_str($uriArr['query'], $paraArr);
        }
        if(is_null($v))
            unset($paraArr[$k]);
        else
            $paraArr[$k] = $v;
        $uriArr['query'] = http_build_query($paraArr);
    }
    $new_url  = '';
    $new_url .= isset($uriArr['scheme'])?$uriArr['scheme'].'://':'';
    $new_url .= isset($uriArr['host'])?$uriArr['host']:'';
    $new_url .= isset($uriArr['port'])?':'.$uriArr['port']:'';
    $new_url .= isset($uriArr['path'])?$uriArr['path']:'';
    $new_url .= !empty($uriArr['query'])?'?'.$uriArr['query']:'';
    $new_url .= !empty($uriArr['fragment'])?'#'.$uriArr['fragment']:'';

    return $new_url;
} 
