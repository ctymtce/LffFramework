<?php
/**
 * author: cty@20120324
 *
 * 
 * 
 * 
*/

class CApp extends CRoute {

    public $name        = '';
    public $charset     = 'UTF-8';
    
    private $html       = null;
    private $redis      = null;
    
    private $mail       = null;
    private $rsa        = null;
    private $pdf        = null;

    public function __construct(&$configs)
    {
        parent::__construct($configs);
        // Lff::setApp($this);
    
        /*
        $this->preinit();
    
        $this->initSystemHandlers();
        $this->registerCoreComponents();
    
        $this->configure($config);
        $this->attachBehaviors($this->behaviors);
        $this->preloadComponents();
    
        $this->init(); */
    }
    
    public function run($route=null, $parameters=null)
    { 
        $this->init();
        $this->appLanuch($route, $parameters);
    }
    private function appLanuch($route=null, $parameters=null)
    {
        $route = $this->getRoute($route);
        if(isset($this->routeAlias[$route]) && $this->routeAlias[$route]){
            $route = $this->routeAlias[$route];
        }
        if($ROUTE_PREFIX = $this->getConfig('ROUTE_PREFIX')){
            $route = '/'.$ROUTE_PREFIX.'/'.ltrim($route,'/');
        }
        $this->runRoute($route, $parameters);
    }

    /*
    * desc: 一些常用初始化工作(待完善)
    *
    *
    */
    public function init()
    {
        date_default_timezone_set('Asia/shanghai');
        if($this->getConfig('session_start',true)){
            $this->startSession();
        }
        
    }   
    public function getTimeZone()
    {
        return date_default_timezone_get();
    }
    public function setTimeZone($value='Asia/Shanghai')
    {
        date_default_timezone_set($value);
    }

    public function startSession()
    {
        $sessionid = $this->getConfig('session_id');
        $sessiondm = $this->getConfig('session_domain');
        $sessionep = $this->getConfig('session_expire',0);
        $session   = $this->getSession();
        $session->start($sessionid, $sessiondm, $sessionep);
    }
    
    public function getSession()
    {
        return Lff::Sao();
    }

    /***********************plugin**************************/
    /*
    * desc: get mail instance
    *       phpmailer must exists
    */
    public function getMail()
    {
        /*
        if(null === $this->mail){
            $this->mail = new CMail();
        }
        return $this->mail;
        */
        return new CMail();
    }
    
    public function getPdf()
    {
        if(null === $this->pdf){
            $this->pdf = new CPdf();
        }
        return $this->pdf;
    }
    /********************end plugin**************************/

    /**
    * author: cty@20120328
    *   func: load model by modelId
    *@modelId --- string 如果class为MUser那么modelId为user
    *@dirs    --- string dir1/dir2/.../
    *@subApp  --- app name(sub item name)
    */
    public function LoadModel($modelId, $subdirs='', $subApp=null)
    {
        if(!isset($this->Arr726128772794766)){
            //暂存 dbmodel(原:dbmodelArr)
            $this->Arr726128772794766 = null;
        }
        
        $class = 'M'.ucfirst($modelId);
        $subApp = null===$subApp?$this->subApp:$subApp;
        $id = $subdirs.'_'.$class. '_'. $subApp;
        if(isset($this->Arr726128772794766[$id]) && is_object($this->Arr726128772794766[$id])){
            //防止重复加载以提高效率
            return $this->Arr726128772794766[$id];
        }
        $modelId   = trim($modelId, '/');
        // $modelLoc  = Lff::app()->modelLoc;
        
        if($subApp){
            $modelLoc  = $this->boot . '/'.$subApp .'/model';
        }else{
            $modelLoc  = $this->modelLoc;
        }
        if($subdirs){
            $subdirs = trim($subdirs,'/').'/';
            $modelFile = $modelLoc.'/'.$subdirs.$class . '.php';
        }else{
            $modelFile = $modelLoc.'/'.$class . '.php';
        }
        if(!is_file($modelFile)) {
            // $this->httpError(500, 'The model class file does not exists('.basename($modelFile).')!');
            $this->Exception('The model class file does not exists('.basename($modelFile).')');
        }
        // require($modelFile);
        $this->requireOnce($modelFile);
        if(!class_exists($class,false)) {
            $this->httpError(500, 'The model class does not exists('.$class.')!');
        }
        return $this->Arr726128772794766[$id] = new $class;
    }
    public function LoadApiModel($modelId, $dirs='', $subApp='api')
    {
        return $this->LoadModel($modelId, $dirs, $subApp);
    }
    /*
    * desc: 直接使用
    *
    */
    public function LoadDaoModel()
    {
        return Lff::Dao();
    }
    //end database model
    /*
    *desc: get cache instance
    *@cid --- str['C','D','F','M','P']
    *retrun cache instance
    */
    function getCache($cid='F', $dir='/tmp')
    {
        if(!isset($this->Arr690310646802722)){
            $this->Arr690310646802722 = null;
        }
        $id = md5('cache_'.$cid.'_'.$dir);
        if(isset($this->Arr690310646802722[$id]) && is_resource($this->Arr690310646802722[$id])){
            return $this->Arr690310646802722[$id];
        }
        $class = 'C'.$cid.'Cache';
        return $this->Arr690310646802722[$id] = new $class($dir);
    }
    /*
    *desc: get redis instance
    *retrun redis instance
    */
    function getRedis($tonew=false)
    {
        $redisArr = $this->getConfig('redis');
        if(isset($redisArr['enable']) && 1==intval($redisArr['enable'])){
            $server = array_shift($redisArr['servers']);
            $ip   = isset($server['ip'])?$server['ip']:'127.0.0.1';
            $port = isset($server['port'])?$server['port']:6379;
            $auth = isset($server['auth'])?$server['auth']:null;
            $db   = isset($server['db'])?$server['db']:0;
            if(is_resource($this->redis) && !$tonew){
                return $this->redis;
            }
            return $this->redis = new CRedis($ip, $port, $auth, $db);
        }
        return null;
    }
};
