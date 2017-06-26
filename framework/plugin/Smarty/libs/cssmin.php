<?php
/**
 *author: cty@20140829
 *  desc: 1, css压缩,主要替换注释、换行、多余空格及tab等;
 *        2, 支持@import命令而递归压缩;
 *        3, 资源路径重写(如原文件的 background:url(../images/a.jpg) --> background:url(根目录/images/a.jpg));
 *        4, 因为有了第3点,所以你可以保存到你网站目录下的任意目录下
 *
*/
class CSSMin{

    /*
    * desc: 压缩并读取它导入的外部css
    *
    *@cssfile --- str(css文件路径,它是一个本地路径而非代码片段)
    *@savedir --- str(你欲保存压缩后的文件目录,用于资源重写)
    *
    *return: 被压缩后的代码
    */
    static function minify($cssfile, $savedir=null){
        $cssfull = '';
        if(false === strpos(strtolower($cssfile), 'http://')){
            if(!is_file($cssfile))return null;
            $directory = dirname($cssfile);

            $css = file_get_contents($cssfile);
            $css = self::_remove_bom($css);

            $importedArr = self::_get_imported_files($css);
            // print_r($importedArr);
            if($importedArr){
                foreach($importedArr as $importedfile){
                    if(false === strpos($importedfile, 'http://'))
                        $importedfile = $directory .'/'. $importedfile;
                    $cssfull .= self::minify($importedfile);
                }
            }
            $css = self::_rewrite($css, $directory, $savedir);
        }else{
            $css = file_get_contents($cssfile);
        }
        /* remove comments */  
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);  
        /* remove tabs, spaces, newlines, etc. */  
        // $css = str_replace(array("\r\n", "\r", "\n"), '', $css);
        $css = preg_replace("/\s*([\{\};\:])\s*/si", '$1', $css);

        $cssfull .= $css;

        return $cssfull;
    }
    /*
    *author: cty@20140829
    *  desc: 将css中的url（如背景）地址改写
    *
    *
    */
    static private function _rewrite($css, $dir, $savedir=null)
    {
        $patt    = "/url\s*\(\s*[\"\']{0,1}(.*?)[\"\']{0,1}\s*\)/si";
        $cssdata = '';
        while($ok = preg_match($patt, $css, $pArr, PREG_OFFSET_CAPTURE)){
            if(isset($pArr[1][0]) && isset($pArr[1][1])){
                $img  = $pArr[1][0]; //一般都为image
                $pos  = $pArr[1][1];
                $cssdata .= substr($css, 0, $pos);
                if(false !== stripos($img, 'http://')){
                    $cssdata .= $img;
                }else{
                    $realpath = realpath($dir .'/'. $img);//这是一个本地路径
                    $cssdata .= self::_remove_prefix($realpath, $savedir);
                }
                $css = substr($css, $pos + strlen($img));
            }
        }
        $cssdata .= $css; //剩余部分
        return $cssdata;
    }

    /*
    *author: cty@20140829
    *  desc: 提取css中用import命令导入的外部文件,并把import命令清除
    *
    *
    */
    static private function _get_imported_files(&$css)
    {
        $patt = "/@import\s+url\s*\(\s*[\"\']{0,1}(.*?)[\"\']{0,1}\s*\)\s*?[;\n\r]/si";
        $ok   = preg_match_all($patt, $css, $pArr);
        // print_r($pArr);
        if(isset($pArr[1][0])){
            $css = preg_replace($patt, '', $css);
            return $pArr[1];
        }
        return null;
    }

    /*
    *author: cty@20140829
    *  desc: 两个文件路径去掉前相同部分
    *
    *
    */
    static private function _remove_prefix($path, $savedir)
    {
        if(!$path || !$savedir)return null;
        $path = str_replace(array('\\'), '/', $path);
        $savedir  = str_replace(array('\\'), '/', $savedir);
        $secArr1 = explode('/', $path);
        $secArr2 = explode('/', $savedir);
        for($i=0, $len=count($secArr1); $i<$len; $i++){
            if(!isset($secArr2[$i]) || $secArr1[$i] != $secArr2[$i])break;
            //保留最后一个相同的
            if($secArr1[$i] == $secArr2[$i] && $secArr1[$i+1] != $secArr2[$i+1])break;
            if($secArr1[$i] == $secArr2[$i])unset($secArr1[$i]);
        }
        return '/'.implode('/', $secArr1);
    }

    static private function _remove_bom(&$content)
    {
        $char1   = substr($content, 0, 1);  
        $char2   = substr($content, 1, 1);  
        $char3   = substr($content, 2, 1);  
        if(ord($char1) == 239 && ord($char2) == 187 && ord($char3) == 191) {  
            $content = substr($content, 3);
        }  
        return $content;
    }
}
