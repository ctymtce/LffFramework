<?php
/**
 * author: cty@20200801
 *   func: static runtime memery cache class
 *
*/
class CRCache {

    static $Pools = array();
    static $Limit = 1000000; //最大个数
    static $Count = 0;       //当前个数
    /*
        array(
            cacheid => array(
                expire => 时间戳
                value => 原始数据
            )
        )
    */

    function __construct()
    {
        self::$Limit = 1000000;
        self::$Count = 0;
    }

    static function Save($id, $val, $expire=1800)
    {
        $cacheid = self::mkId($id);
        $expire  = time() + $expire;

        if(self::$Count >= self::$Limit){
            do{
                array_shift(self::$Pools);
                self::$Count--;
            }while(self::$Count < self::$Limit);
        }

        self::$Count++;
        return self::$Pools[$cacheid] = array(
            'expire' => $expire,
            'value' => $val,
        );
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
        $cacheid = self::mkId($id);
        if(isset(self::$Pools[$cacheid])) {
            $oldData = self::$Pools[$cacheid]['value'];
            if(self::$Pools[$cacheid]['expire'] < time()) { //expired
                if($rmExpired)unset(self::$Pools[$cacheid]);
                return $hited = false;
            }else {
                $hited = true;
                return $oldData;
            }
        }
        return $hited = false;
    }
    static function All()
    {
        return self::$Pools;
    }
    static function hashLevel($id)
    {
        $c = crc32($id);
        if($c < 0) $c += 4294967296;
        return $c %= self::$level;
    }
    
    static function Remove($id)
    {
        $cacheid = self::mkId($id);
        unset(self::$Pools[$cacheid]);
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
        return self::$Pools = array();
    }
    static function mkId($ids) 
    {
        if(is_array($ids)){
            if(isset($ids['ids'])){
                return $ids['ids'];
            }
            if(isset($ids['prefix'])){
                return $ids['prefix'].'.'.md5(json_encode($ids));
            }
            return md5(json_encode($ids));
        }
        return md5($ids);
    }
};
