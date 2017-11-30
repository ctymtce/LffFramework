<?php
/**
 * author: cty@20120324
 *
 * 
 * 
 * 
*/

class CApp extends CRoute {

    protected $cgimode  = 1; //1:FPM,2:SWOOLE
    protected $request  = null;
    protected $response = null;


    private $session    = null;

    private $charset    = 'UTF-8';
    
    private $redis      = null;
    
    private $mail       = null;
    private $rsa        = null;
    private $pdf        = null;

    public function __construct(&$configs)
    {
        parent::__construct($configs);
    
        $this->PreInit(); //预初始化
    }
    /*
    * desc: 一些常用初始化工作(待完善)
    *
    *
    */
    public function PreInit()
    {
        date_default_timezone_set(
            $this->getConfig('timezone','Asia/shanghai')
        );
    }
    public function Run($route=null, $parameters=null, $configs=null)
    {
        if(is_array($configs) && count($configs)>0){
            $this->setConfig($configs);
        }
        if($this->getConfig('session_start', true)){
            $this->StartupSession();
        }
        return $this->appLanuch($route, $parameters);
    }
    public function Event($request, $response, $extras=array(), $params=null)
    {
        $this->cgimode  = 2;
        $this->request  = $request;
        $this->response = $response;
        return $this->Run(null, $params, $extras);
    }
    public function CleanUp()
    {
        parent::CleanUp();
    }
    private function appLanuch($route=null, $parameters=null)
    {
        $route = $this->getRoute($route, true);
        if($ROUTE_PREFIX = $this->getConfig('ROUTE_PREFIX')){
            $route = '/'.$ROUTE_PREFIX.'/'.ltrim($route,'/');
        }
        return $this->runRoute($route, $parameters);
    }
    public function getTimeZone()
    {
        return date_default_timezone_get();
    }
    public function setTimeZone($value='Asia/Shanghai')
    {
        return date_default_timezone_set($value);
    }

    public function StartupSession()
    {
        $session   = $this->getSession();
        $this->cleanBuffer();
        $options = $this->getConfig('session_options');
        if(!is_array($options)) $options = array();
        $options['cgimode'] = $this->cgimode;
        $session->options($options);
        $session->start();
    }
    /*
    * desc: just to use swoole's Swoole_Async_writeFile
    *
    */
    public function writeLogEx($logs, $basename, $prex='', $mod="a")
    {
        return $this->writeLog($logs, $basename, $prex, $mod);
    }
    
    public function getSession()
    {
        if(is_null($this->session)){
            $this->session = new CSession();
        }
        return $this->session;
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
        $subApp = null===$subApp?$this->sub:$subApp;
        $id = $subdirs.'_'.$class. '_'. $subApp;
        if(isset($this->Arr726128772794766[$id]) && is_object($this->Arr726128772794766[$id])){
            //防止重复加载以提高效率
            return $this->Arr726128772794766[$id];
        }
        $modelId = trim($modelId, '/');
        
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
    /*
    * desc: 加载库
    *
    */
    public function LoadLib($lib)
    {
        if(!isset($this->Arr872616794272776[$lib])){
            $clazz = 'C'.ucfirst($lib);
            if(class_exists($clazz,false)){
                return $this->Arr872616794272776[$lib] = new $clazz;
            }
            $file  = __DIR__.'/../libs/'.$clazz.'.php';
            if(!is_file($file)) return false;
            $ok = $this->requireOnce($file);
            if($ok){
                return $this->Arr872616794272776[$lib] = new $clazz;
            }
            return false;
        }
        return $this->Arr872616794272776[$lib];
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
