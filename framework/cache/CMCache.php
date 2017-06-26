<?php
/**
 * author: cty@20120408
 *   func: memcache class
 *   desc: if the memcache class is not exists,then the caching will be unable.
 *
*/
class CMCache extends CCache{
    
    private $iMem = null;
    public function __construct()
    {
        $this->connect();
    }
    private function connect($host='127.0.0.1', $port=11211)
    {
        if(null===$this->iMem && class_exists('Memcache',false)) {
            $this->iMem = new Memcache;
            $this->iMem->connect($host, $port, 2);
        }
    }
    
    public function save($id, $val, $expire=1800)
    {
        if(!$this->isAbled()) return false;
        return $this->iMem->set($id, $val, 0, $expire);
    }
    public function load($id)
    {
        if(!$this->isAbled()) return false;
        return $this->iMem->get($id);
    }
    public function remove($id)
    {
        if(!$this->isAbled()) return false;
        return $this->iMem->delete($id, 0);
    }
    public function clean($all=true)
    {
        if(!$this->isAbled()) return false;
        return $this->iMem->flush();
    }
    private function mkId($id) 
    {
        return md5($id);
    }
    private function isAbled() 
    {
        return null===$this->iMem?false:true;
    }
};
