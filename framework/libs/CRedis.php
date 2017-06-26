<?php
/**
 * desc: php redis class
 *
 * call: $redis = new CRedis();
 *       $redis->set('k1', 'v', 20);
 *
 *
 *
*/
class CRedis extends Redis{

    private $ip   = null;   //[127.0.0.1]
    private $port = null;   //[6379]
    private $auth = null;   //null
    private $db   = 1;      //1

    function __construct($ip='127.0.0.1', $port=6379, $auth=null, $db=1)
    {
        $this->ip   = $ip;
        $this->port = $port;
        $this->auth = $auth;
        $this->db   = $db;
        $this->connect();
    }
    function __destruct()
    {
        try{
            parent::__destruct();
            parent::close();
        }catch(Exception $e){}
    }

    public function connect()
    {
        if(parent::pconnect($this->ip, $this->port)){
            if($this->auth){
                parent::auth($auth);
            }
            if($this->db){
                $this->select($this->db);
            }
        }
    }

    public function alive()
    {
        while(($loops=isset($loops)?++$loops:0) < 5){
            if('cli' != PHP_SAPI) ob_start();
            try{
                if('+PONG' == parent::ping()) return true;
            }catch(Exception $e){
                $e->getMessage();
            }
            if('cli' != PHP_SAPI) ob_get_clean();//Redis::ping(): send of 14 bytes failed with errno=10054
            usleep(1000*mt_rand(2,20));
            $this->connect();
        }
        return false;
    }

    public function select($db=0)
    {
        if(!$this->alive()) return false;
        parent::select(intval($db));
        return $this;
    }

    /**
     * 设置值  构建一个字符串
     * @param string $key KEY名称
     * @param string $val  设置值
     * @param int $expired 时间  0,-1表示无过期时间
    */
    public function set($key, $val, $expired=0)
    {
        if(!$this->alive()) return false;
        $val = is_array($val)?json_encode($val):$val;
        $result = parent::set($key, $val);
        if ($expired > 0){
            $this->ttl($key, $expired);
        }
        return $result;
    }
    public function Save($key, $val, $expired=0)
    {
        return $this->set($key, $val, $expired);
    }

    public function get($key)
    {
        if(!$this->alive()) return false;
        $v = parent::get($key);
        $vj = json_decode($v, true);
        return $vj?$vj:$v;
    }
    public function Load($key)
    {
        return $this->get($key);
    }
    public function del($key)
    {
        if(!$this->alive()) return false;
        return parent::delete($key);
    }
    public function rm($key)
    {
        return $this->del($key);
    }
    /*
    * desc: hash
    *@key     --- string KEY名称
    *@field   --- string 设置值
    *@val     --- mix  设置值
    *@expired --- int 0表示无过期时间
    *@isnx    --- bool 是否用hsetnx
    */
    public function hset($key, $field, $val, $expired=0, $isnx=false)
    {
        if(!$this->alive()) return false;
        $val = is_array($val)?json_encode($val):$val;
        if($isnx){
            $result = parent::hSetNx($key, $field, $val);
        }else{
            $result = parent::hSet($key, $field, $val);
        }
        $this->ttl($key, $expired>0?$expired:-1);
        return $result;
    }
    public function hget($key, $field, $jsoned=true)
    {
        if(!$this->alive()) return false;
        $val = parent::hGet($key, $field);
        if($jsoned){
            $val = json_decode($val, true);
        }
        return $val;
    }

    public function hsetnx($key, $field, $val, $expired=0)
    {
        if(!$this->alive()) return false;
        return $this->hset($key, $field, $val, $expired, true);
    }

    public function ttl($key, $expired = -1)
    {
        if(!$this->alive()) return false;
        return $this->expire($key, $expired);
    }
};
