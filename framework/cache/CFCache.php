<?php
/**
 * author: cty@20120406
 *   func: file cache class
 *   desc: The value was encoded by json_encode
 *         Before save value push value to a array
 *
*/
class CFCache extends CCache{

    private $level    = 2;
    private $cacheLoc = '';
  
    public function __construct($cacheLoc='/tmp')
    {
        $this->cacheLoc = rtrim($cacheLoc, '/');
        if(!is_dir($cacheLoc)){
            mkdir($cacheLoc);
        }
    }
    public function save($id, $val, $expire=1800)
    {
        $id = $this->mkId($id);
        $cacheFile = $this->getFile($id);
        $jVal = json_encode(array($val));
        if(false !== file_put_contents($cacheFile, $jVal, LOCK_EX)) {
            chmod($cacheFile, 0755);
            touch($cacheFile, time()+$expire);
            return true;
        }
        return false;
    }
    public function load($id)
    {
        $id = $this->mkId($id);
        $cacheFile = $this->getFile($id);
        if(is_file($cacheFile)) {
            if(filemtime($cacheFile) < time()) { //expired
                unlink($cacheFile);
            }else {
                $jArr = json_decode(file_get_contents($cacheFile), true);
                return $jArr[0];//stripcslashes();
            }
        }
        return false;
    }
  
    private function getFile($id)
    {
        $sn = $this->hashLevel($id);
        $cacheDir  = $this->cacheLoc .'/'.$sn;
        if(!is_dir($this->cacheLoc)) {
            mkdir($this->cacheLoc, 0777, true);
        }
        if(!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        $cacheFile = $cacheDir.'/'.$id.'.che';
        return $cacheFile;
    }
    public function hashLevel($id)
    {
        $crc = sprintf('%u', crc32($id));
        return $crc %= $this->level;
    }
    
    public function remove($id)
    {
        $id = $this->mkId($id);
        $cacheFile = $this->getFile($id);
        if(is_file($cacheFile)) {
            return unlink($cacheFile);
        }
        return false;
    }
    /**
     * author: cty@20120406
     *   func: clean cache files
     *@all --- bool
     *         clean all cache files if true or clean expired's file only if false.
    */
    public function clean($all=true)
    {
        for($i=0; $i<$this->level; $i++) {
            $cacheDir  = $this->cacheLoc .'/'.$i;
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
    private function mkId($id) 
    {
        return md5($id);
    }
};
