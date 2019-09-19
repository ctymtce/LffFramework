<?php
if(!isset($GLOBALS['CSmarty.Smarty.class'])){
    $GLOBALS['CSmarty.Smarty.class'] = 1;
    require(dirname(__FILE__). '/Smarty/Smarty.class.php');
}

class CSmarty {
    
    private $smarty = null;
    private $tpldir = '.';
    private $cpldir = null;
    
    public function __construct($tpl_base_dir='.', $tpl_cpl_dir=null)
    {
        $this->tpldir = $tpl_base_dir;
        $this->cpldir = $tpl_cpl_dir;
    }

    public function getSmarty()
    {
        if(null === $this->smarty) {
            $this->smarty = new Smarty($this->tpldir);
            if($this->cpldir){
                $this->smarty->setCompileDir($this->cpldir);
            }
        }
        return $this->smarty;
    }

    public function assign($key, $value=null, $nocache=false)
    {
        $smarty = $this->getSmarty();
        return $smarty->assign($key, $value, $nocache);
    }
    
    public function display($template=null, $cache_id=null, $compile_id=null, $parent=null)
    {
        $smarty = $this->getSmarty();
        if($cache_id){
            $smarty->caching = true;
            // $smarty->setCacheLifetime(2);
            // echo $smarty->getCacheLifetime();
        }
        return $smarty->display($template, $cache_id, $compile_id, $parent);
    }

    public function fetch($template=null, $cache_id=null, $compile_id=null, $parent=null)
    {
        $smarty = $this->getSmarty();
        if($cache_id){
            $smarty->caching = true;
        }
        return $smarty->fetch($template, $cache_id, $compile_id, $parent);
    }
    
    public function setAtt($key, $val)
    {   
        $smarty = $this->getSmarty();
        $smarty->$key = $val;
        $smarty->$key = $val;
        $smarty->$key = $val;
    }
    public function isCached($template=null, $cache_id=null, $expire=3600, $compile_id=null, $parent=null)
    {   
        $smarty = $this->getSmarty();
        $smarty->caching = true;
        $smarty->setCacheLifetime($expire);
        return $smarty->isCached($template, $cache_id, $compile_id=null, $parent=null) ? true : false;
    }
};

