<?php
/**
 * author: cty
 * Smarty plugin to assets block
 *
 * using
 *  {assets compress=js boot=$DATA_LOC savedir=$STATIC_LOC urlprex=$STATIC_URL enable=true id="goods-detail"}
 *      .......
 *  {/assets}
 * 
 *@compress  --- [选]压缩文件类型[默认:js and css]
 *@boot      --- [必]资源根目录，如：D:\sites\hqwebs\vipmro\_data
 *@savedir   --- [选]压缩后的js存放目录(强烈推荐显示指定该值)
 *@urlprex   --- [必]它应该是指向savedir
 *@enable    --- [选]压缩开关(默认:true)
 *@id        --- [选]压缩后的文件名(建议设置，以提高性能)
 *@embedded  --- [选]压缩后代码是否嵌入到html中
*/
function smarty_block_assets($params, $content, $template, &$repeat)
{
    if (empty($content) || empty($params)) {
        return $content;
    }
    $enable     = isset($params['enable'])?$params['enable']:true;
    if(!$enable)return $content;
    $root = isset($params['boot'])?$params['boot']:null;
    if(!$root)return $content;
    $savedir    = !empty($params['savedir'])?$params['savedir']:$boot;
    $subdir     = !empty($params['subdir'])?$params['subdir']:null;
    $savedir    = $subdir ? $savedir.'/'.$subdir: $savedir;

    $urlprex    = $params['urlprex']?$params['urlprex'].'/':'/';
    $urlprex    = $subdir?$urlprex.$subdir.'/':$urlprex;
    $id         = isset($params['id'])?$params['id']:null;

    $compress   = isset($params['compress'])?$params['compress']:'all';
    $compressid = md5($content . $id);

    if(!is_dir($savedir))mkdir($savedir);
    if('all' == $compress || 'js'==$compress){
        $packed_js_url = null;
        
        $packedname = $compressid.'.min.js';
        if(is_file($savedir.'/'.$packedname)){
            $packed_js_url = $urlprex.$packedname;
        }

        if(!$packed_js_url){
            $jfileArr = _fetch_javascript_files($content);
            // print_r($jfileArr);
            if($jfileArr){
                if(is_file($savedir.'/'.$packedname)){
                    $packed_js_url = $urlprex.$packedname;
                }else{
                    $string_js = $js_packed = '';
                    foreach($jfileArr as $jfile){
                        if(strpos($jfile, '.min.js')){
                            // $packed_js_url = $jfile;
                            $file = $root . preg_replace("/http\:\/\/.+?\.(?:com|org|me|cn|net)/i", '', $jfile);
                            if(is_file($file)) {
                                $js_packed .= ';'.file_get_contents($file);
                            }
                        }else{
                            $jfile = preg_replace("/\?.*/", '', $jfile);
                            $file  = $root . $jfile;
                            if(is_file($file)) $string_js .= ';'.file_get_contents($file);
                        }
                    }
                    if($string_js){
                        requireOnce(__DIR__ .'/../libs/jsmin.php');
                        $js_packed .= JSMin::minify($string_js);
                    }
                    if($js_packed && is_dir($savedir)){
                        $ok = file_put_contents($savedir.'/'.$packedname, $js_packed);
                        if($ok){
                            $packed_js_url = $params['urlprex'].'/'.$packedname;
                        }
                    }
                }
            }
        }
        // exit($subdir);
        if($packed_js_url){
            $content  = trim(preg_replace("/<script.*?src.*?>.*?<\/script>/si", '', $content));
            $js_tag   = "<script type='text/javascript' src='{$packed_js_url}'></script>";
            $content .= $js_tag;
        }
    }
    
    if('all' == $compress || 'css'==$compress){
        $packed_css_url = null;
     
        $packedname = $compressid.'.min.css';
        if(is_file($savedir.'/'.$packedname)){
            $packed_css_url = $urlprex.$packedname;
        }

        if(!$packed_css_url){
            $cfileArr = _fetch_css_files($content);
            // print_r($cfileArr);
            if($cfileArr){
                if(is_file($savedir.'/'.$packedname)){
                    $packed_css_url = $urlprex.$packedname;
                }else{
                    requireOnce(__DIR__ .'/../libs/cssmin.php');
                    $css_packed = '';
                    foreach($cfileArr as $cfile){
                        if(strpos($cfile, '.min.css')){
                            // $packed_css_url = $cfile;
                            $file = $root . preg_replace("/http\:\/\/.+?\.(?:com|org|me|cn|net)/i", '', $cfile);
                            if(is_file($file)) {
                                $css_packed .= file_get_contents($file);
                            }
                        }else{
                            $cfile = preg_replace("/\?.*/", '', $cfile);
                            $file  = $root . $cfile;
                            $css_packed .= CSSMin::minify($file, $savedir);
                        }
                    }
                    if($css_packed && is_dir($savedir)){
                        $ok = file_put_contents($savedir.'/'.$packedname, $css_packed);
                        if($ok){
                            $packed_css_url = $params['urlprex'].'/'.$packedname;
                        }
                    }
                }
            }
        }
        if($packed_css_url){
            $content  = _fetch_css_files($content, 1);
            $css_tag  = "<link rel='stylesheet' href='{$packed_css_url}' type='text/css' media='all' />";
            $content .= $css_tag;
        }
    }
    $content = trim(preg_replace('/<style>\s*<\/style>/si', '', $content));
    return $content;
}


function _fetch_javascript_files(&$html, $type=0)
{
    $html = preg_replace("/<\!--.*?-->/", '', $html);
    $patt = "/<script.+?src\=.*?[\"\'](.+?)[\"\'].*?>/si";
    if(1 == $type){
        return preg_replace($patt, '', $html);
    }else{
        $ok   = preg_match_all($patt, $html, $pArr);
        // print_r($pArr);
        if(isset($pArr[1][0])){
            return array_unique($pArr[1]);
        }
        return null;
    }
}

function _fetch_css_files(&$html, $type=0)
{
    $html = preg_replace("/<\!--.*?-->/", '', $html);
    $patt = "/<link.+?href\=.*?[\"\'](.+?)[\"\'].*?>/si";
    if(1 == $type){
        return preg_replace($patt, '', $html);
    }else{
        $ok = preg_match_all($patt, $html, $pArr);
        // print_r($pArr);
        if(isset($pArr[1][0])){
            $html = preg_replace($patt, '', $html);
            return array_unique($pArr[1]);
        }
        return null;
    }
}
