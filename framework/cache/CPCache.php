<?php
/**
 * author: cty@20120408
 *   func: apc class class
 *   desc: depends apc extension.
 *
*/
class CPCache extends CCache{
    
    private $apc = false;
    public function __construct()
    {
        $this->apc = extension_loaded('apc')?true:false;
    }

    public function save($id, $val, $expire=1800)
    {
        if(!$this->isAbled()) return false;
        $ok = apc_store($id,$val,$expire);
        if(!$ok) {
            return $this->addValue($id, $val, $expire);
        }
        return $ok;
    }
    public function add($id, $val, $expire=1800)
    {
        if(!$this->isAbled()) return false;
        return apc_add($id,$val,$expire);
    }
    public function load($id)
    {
        if(!$this->isAbled()) return false;
        return apc_fetch($id);
    }
    public function loads($ids)
    {
        if(!$this->isAbled()) return false;
        return apc_fetch($ids);
    }
    public function remove($id)
    {
        if(!$this->isAbled()) return false;
        return apc_delete($id);
    }
    public function clean($all=true)
    {
        if(!$this->isAbled()) return false;
        return apc_clear_cache() && apc_clear_cache('user');
    }
    private function isAbled() 
    {
        return $this->apc;
    }
};
