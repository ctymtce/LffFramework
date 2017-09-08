<?php
/**
 * author: cty@20161103
 *   func: static file cache class
 *
*/
class CCaches {

    static $level = 10;
    static $chdir = '/tmp';
  
    static function Save($id, $val, $expire=1800)
    {
        $group = (is_array($id)&&isset($id['group']))?$id['group']:null;
        $id = self::mkId($id);
        $cacheFile = self::getFile($id, $group);
        $jVal = json_encode(array($val));
        if(class_exists('Cgi',0) && 2==Cgi::Mode){
            return Swoole_Async_writeFile($cacheFile, $jVal, function($file)use($expire){
                chmod($file, 0755);
                touch($file, time()+$expire);
            });
        }else{
            if(false !== file_put_contents($cacheFile, $jVal, LOCK_EX)) {
                chmod($cacheFile, 0755);
                touch($cacheFile, time()+$expire);
                return true;
            }
        }
        return false;
    }
    /*
    * desc: 加载缓存
    *
    *@id        --- 缓存id
    *@hited     --- 标识是否命中
    *@oldData   --- 过期前的数据
    *@rmExpired --- 是否删除过期数据
    *
    *return 缓存数据 Or false
    */
    static function Load($id, &$hited=null, &$oldData=null, $rmExpired=true)
    {
        $group = (is_array($id)&&isset($id['group']))?$id['group']:null;
        $id = self::mkId($id);
        $cacheFile = self::getFile($id, $group);
        if(is_file($cacheFile)) {
            $jArr = json_decode(file_get_contents($cacheFile), true);
            if(filemtime($cacheFile) < time()) { //expired
                if($rmExpired) unlink($cacheFile);
                $oldData = $jArr[0]; //已过期
                return $hited = false;
            }else {
                $hited = true;
                return $jArr[0];//stripcslashes();
            }
        }
        return $hited = false;
    }
    /*
    *desc: 创建文件
    *
    *@group --- str 分组目录
    */
    static function getFile($id, $group=null)
    {
        $sn = self::hashLevel($id);
        if(!is_dir(self::$chdir)) {
            mkdir(self::$chdir, 0755, true);
        }
        $cacheDir  = self::$chdir;
        if($group) $cacheDir .= '/'.$group;
        if(!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        $cacheDir  .= '/'.$sn;
        if(!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        $cacheFile = $cacheDir.'/'.$id.'.che';
        return $cacheFile;
    }
    static function hashLevel($id)
    {
        $c = crc32($id);
        if($c < 0) $c += 4294967296;
        return $c %= self::$level;
    }
    
    static function Remove($id)
    {
        $group = (is_array($id)&&isset($id['group']))?$id['group']:null;
        $id = self::mkId($id);
        $cacheFile = self::getFile($id, $group);
        if(is_file($cacheFile)) {
            return unlink($cacheFile);
        }
        return true;
    }
    /**
     * author: cty@20120406
     *   func: clean cache files
     *@all --- bool
     *         clean all cache files if true or clean expired's file only if false.
    */
    static function Clean($all=true)
    {
        for($i=0; $i<self::$level; $i++) {
            $cacheDir  = self::$chdir .'/'.$i;
            if(!is_dir($cacheDir)) continue;
            $handler = opendir($cacheDir);
            while(false !== ($filename = readdir($handler)))
            {
                if($filename != '.' && $filename != '..') {
                    $fullname = $cacheDir .'/'. $filename;
                    if($all || (filemtime($cacheFile)<time())) {
                        unlink($fullname);
                    }
                }
            }
            closedir($handler);
        }
    }
    static function mkId($id) 
    {
        if(is_array($id)){
            if(isset($id['id'])){
                return $id['id'];
            }
            if(isset($id['prefix'])){
                return $id['prefix'].'.'.md5(json_encode($id));
            }
            return md5(json_encode($id));
        }
        return md5($id);
    }
};
