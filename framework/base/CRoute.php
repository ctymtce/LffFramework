<?php
/**
 * athor: cty@20120322
 *  func: requestion handler,eg. url,system variables and so on
 *  desc: loc---address(dir) of location
 *        url---address of web
 * 
*/
abstract class CRoute extends CEle {
    
    protected $configArr = array();

    /*****************************URL中path的命名规范*********************/
    /*
    *                |-------------path-------|
    * 如果url: ...com/dir123/dir234/controller/action
    *                |--directory--|----router------|
    *                |------------route-------------|
    *                
    */
    protected $route      = null; /*
                                    route = directory + controller + action
                                                      or
                                    route = path                   + action
                                  */
    protected $appRoute   = null;
    protected $path       = null;
    protected $directory  = null;
    protected $router     = null;
    protected $controller = null;
    protected $action     = null;
    /**************************end URL中path的命名规范*********************/

    protected $restArr   = array();
    protected $restful   = array(); //用于暂存

    protected $URLMODE   = 2; //url style[1-compility, 2-REST]

    /*****************************环境变量参数规划************************/
    public $primaryLoc   = '';

    public $boot         = ''; //项目根目录(../../myproject)
    public $home         = ''; //当前根URL(http://www.demo.com)
    public $subApp       = ''; //当前子项目名称(二级域名名称,如admin)
    public $ui           = ''; //当前子项目的ui(如:fluid)

    public $subappLoc    = ''; //当前subApp目录
    public $configLoc    = ''; //当前subApp下配置目录
    public $viewLoc      = ''; //当前subApp下视图目录
    public $ctrlLoc      = ''; //当前subApp下控制器目录
    public $modelLoc     = ''; //当前subApp下模型目录
    public $daoLoc       = ''; //dao目录
    public $cacheLoc     = ''; //缓存目录
    public $dataLoc      = ''; //数据目录
    public $uiLoc        = '';

    public $TPL_APP      = ''; //smarty模板目录
    public $TPL_SUB      = ''; //smarty模板目录
    public $TPL_LOC      = ''; //smarty模板目录
    public $TPL_INC      = ''; //smarty模板目录
    public $TPL_LAY      = ''; //smarty模板目录

    public $subappUrl    = ''; //指向当前subApp目录
    public $assetsUrl    = ''; //指向当前subApp资源目录
    public $uiUrl        = ''; //指向当前subApp下ui目录
    public $jsUrl        = ''; //指向当前subApp下当前ui下的js目录
    public $cssUrl       = ''; //指向当前subApp下当前ui下的css目录
    public $imageUrl     = ''; //指向当前subApp下当前ui下的image目录
    /*************************end 环境变量参数规划************************/

    public $routeKey     = 'path';    
    public $params       = array();    
    
    protected $routeAlias   = array(); //控制器方法别名
    protected $routeMissing = null;    //当路不存在时的默认路由
    
    /*
    * desc: construct function
    *@configs --- array
    */
    public function __construct(&$configs)
    {
        $this->trimConfig($configs);
    }
    private function trimConfig(&$configs)
    {
        $cfgArr = $this->_loadConfig($configs);
        if(!isset($cfgArr['home']) || !isset($cfgArr['boot'])) {
            exit('The baseUrl or baseLoc doesn\'t set!');
        }
        $this->subApp       = isset($cfgArr['subApp'])?$cfgArr['subApp']:'primary';
        $this->ui           = isset($cfgArr['ui'])?$cfgArr['ui']:'default'; //ui name
        $this->URLMODE      = isset($cfgArr['URLMODE'])?$cfgArr['URLMODE']:2;
        
        $this->boot         = rtrim($cfgArr['boot'], '/');
        $this->home         = rtrim($cfgArr['home'], '/');

        $this->dataLoc      = $this->boot.'/assets';
        $this->assetsLoc    = $this->dataLoc;
        $this->subappLoc    = $this->boot . '/'. $this->subApp;
        
        $this->daoLoc       = $this->boot.'/dao';
        $this->primaryLoc   = $this->boot.'/primary'; //item = project
        $this->ctrlLoc      = $this->_get_controller_location($this->subApp);//$this->subappLoc.'/controller';
        $this->viewLoc      = $this->subappLoc.'/view';
        $this->configLoc    = $this->subappLoc.'/config';
        $this->modelLoc     = $this->subappLoc.'/model';
        $this->cacheLoc     = $this->dataLoc.'/_cache';

        $this->TPL_LOC      = ($this->getConfig('smarty', $this->boot)).'/smarty';
        
        // $this->assetsUrl    = $this->itemUrl.'/assets';
        $this->assetsUrl    = '/assets'; //不用绝对路径是为了便于使用不同的域名
        $this->jsUrl        = $this->assetsUrl .'/'. $this->subApp .'/js';
        $this->cssUrl       = $this->assetsUrl .'/'. $this->subApp .'/css';
        $this->imageUrl     = $this->assetsUrl .'/'. $this->subApp .'/images';
        
        isset($cfgArr['params']) && $this->params = $cfgArr['params'];
        isset($cfgArr['routeAlias']) && $this->routeAlias = $cfgArr['routeAlias'];
        isset($cfgArr['routeMissing']) && $this->routeMissing = $cfgArr['routeMissing'];

        $this->setUi($this->ui);
    }
    public function setUi($ui='default')
    {
        if(empty($ui))return;
        $this->ui = $ui;

        $_ui_base_url  = $this->assetsUrl.'/ui';
        $this->uiUrl   = $_ui_base_url.'/'.$ui;

        $this->uiLoc   = $this->assetsLoc.'/ui/'.$ui;

        $this->TPL_COM = $this->TPL_LOC .'/common';
        // $this->TPL_SUB = $this->TPL_LOC .'/'. $this->subApp;
        $this->TPL_UI  = $this->TPL_LOC .'/'. $ui;
        $this->TPL_INC = $this->TPL_UI .'/templates/include';
        $this->TPL_LAY = $this->TPL_UI .'/templates/layout';
    }

    private function _get_controller_location($subApp=null)
    {
        return $this->boot . '/'. ($subApp?$subApp:$this->subApp) . '/controller';
    }

    private function &_loadConfig(&$configs)
    {
        $this->configArr = &$configs;
        return $configs;
    }
    /*
    * desc: 加载公共自定义配置文件
    *
    */
    public function LoadConfig($file, $dir=null)
    {
        $cfgLoc = $this->boot . '/config';
        $file = basename($file);
        if(null === $dir){
            $cfgLoc = $cfgLoc.'/'.$file.'.php';
        }else{
            $dir = trim($dir, '/');
            $cfgLoc = $cfgLoc.'/'.$dir.'/'.$file.'.php';
        }
        return $this->requireOnce($cfgLoc);
        // return require($cfgLoc);
    }
    /*
    * desc: 加载子应用自定义配置文件
    *
    */
    public function LoadSubConfig($file, $dir=null)
    {
        $cfgLoc = $this->configLoc;
        $file = basename($file);
        if(null === $dir){
            $cfgLoc = $cfgLoc.'/'.$file.'.php';
        }else{
            $dir = trim($dir, '/');
            $cfgLoc = $cfgLoc.'/'.$dir.'/'.$file.'.php';
        }
        return $this->requireOnce($cfgLoc);
        // return require($cfgLoc);
    }
    
    public function getConfig($key=null, $default=null)
    {
        if(null === $key){
            return $this->configArr;
        }
        if(strpos($key, '.')){
            $vals = $this->configArr; //不要设置成引用，否则会改变configArr的值
            foreach(explode('.',$key) as $sub){
                if(!isset($vals[$sub])) return $default;
                $vals = $vals[$sub];
            }
            return $vals;
        }else{
            return isset($this->configArr[$key])?$this->configArr[$key]:$default;
        }
    }
    public function getUserConfig($key=null, $default=null)
    {
        $cfgArr = &$this->configArr;
        $userConfig = isset($cfgArr['user'])?$cfgArr['user']:null;
        if(null === $key){
            return $userConfig;
        }
        if($userConfig){
            return isset($userConfig[$key])?$userConfig[$key]:$default;
        }
        return $default;
    }
    /*
    * 获取primary下某目录的url地址，如：_uploads ...com/_uploads
    *@dirName --- string(dir1/dir2/...)
    *return: ...com/dir1/dir2/...
    */
    public function getUrl($dir)
    {
        return rtrim($this->home.'/'.ltrim($dir,'/'), '/');
    }
    /*
    * 获取primary下某目录的本地地址，如：_uploads /../primary/_uploads
    *@dirName --- string(dir1/dir2/...)
    *return: /.../primary/dir1/dir2/...
    */
    public function getLoc($dir=null)
    {
        return $this->boot.'/'.$dir;
    }
    public function getDataLocation($dir=null)
    {
        return $this->getLoc('assets/'.$dir);
    }
    public function getAssetsLocation($dir=null)
    {
        return $this->getLoc('assets/'.$dir);
    }
    public function getStaticLocation($dir=null)
    {
        return $this->getLoc('assets/static/'.$dir);
    }
    /*
    * func: get default routor
    * desc: 1, if subApp is 'primary' then default routor is 'site'
    *       2, if subApp isn't 'primary' then default routor is subApp
    */
    private function _get_default_controller_name($directory='')
    {
        if($directory = trim($directory,'/')){
            if(false===strpos($directory,'/') && ($topController = $this->getConfig('default_controller'))){
                return $topController; //只针对第一级目录有效
            }else{
                //如果是一个目录,它下面的默认控制器为该目录名
                if(strpos($directory, '/')){
                    return substr($directory, strrpos($directory,'/')+1);
                }else{
                    return $directory;
                }
            }
        }else{
            $subApp = $this->subApp;
            return 'primary'==$subApp?'site':$subApp;
        }
    }
    // format request url
    public function trimReq()
    {
        $sArr = $_SERVER;
        $baseUrl  = $this->baseUrl;
        $fullUrl  = 'http://'.$sArr['HTTP_HOST'].$sArr['REQUEST_URI'];
        $assetUrl = $baseUrl .'/'.'assets';
        $imgUrl   = $assetUrl.'/'.'imgs';
        $jsUrl    = $assetUrl.'/'.'js';
        $cssUrl   = $assetUrl.'/'.'cs';
        
        $this->fullUrl  = $fullUrl;
        $this->assetUrl = $assetUrl;
        $this->imgUrl   = $imgUrl;
        $this->jsUrl    = $jsUrl;
        $this->cssUrl   = $cssUrl;
    }
    /**
    * author: cty@20120326
    *   func: create url
    *@route   --- string(controller/action)
    *@paraArr --- string url parameters
    * reutrn: url;
    */
    public function makeUrl($route=null, $paraArr=array(), $prex=null, $defaction='entry')
    {
        $port = $this->port();
        if(strpos($route, '?')){
            $url_paras = ltrim(strstr($route, '?'),'?');
            parse_str($url_paras, $url_paras);
            if($url_paras){
                $paraArr = array_merge($paraArr, $url_paras);
            }
            $route = strstr($route, '?', true);
        }
        if(0 === strpos($route,'http://') || 0 === strpos($route,'https://')){
            $uArr = parse_url($route);
            $prex  = $uArr['scheme'].'://'.$uArr['host'];
            if(isset($uArr['port'])){
                $port = $uArr['port'];
            }
            $route = isset($uArr['path'])?$uArr['path']:'';
        }
        $baseUrl = $prex?$prex:$this->home;
        if(80 != $port){
            $baseUrl  = str_replace(':'.$port, '', $baseUrl);
            $baseUrl .= ':'.$port;
        }
        $dft_ctrl_name = $this->_get_default_controller_name();
        if(null === $route){
            $route = $dft_ctrl_name.'/'.$defaction;
        }else{
            if(false === strpos($route,'/')){
                if(0 == strlen($route)) {
                    $route = $dft_ctrl_name.'/'.$defaction;
                }else {
                    $route .= '/'.$defaction;
                }
            }
        }
        unset($paraArr[$this->routeKey]);
        foreach($paraArr as $k=>&$v) {
            if(!is_string($k)) unset($paraArr[$k]);
        }
        $anchor='';
        if(isset($paraArr['#'])) {
            $anchor='#'.$paraArr['#'];
            unset($paraArr['#']);
        }
        $route = trim($route,'/');
        $query = http_build_query($paraArr);
        $query = (strlen($query)>0)?'&'.$query:'';
        if(2 == $this->URLMODE) {
            $query    = trim($query, '&');
            $routeUrl = $baseUrl.'/'.$route.'?'.$query.$anchor;
        }else {
            $routeUrl = $baseUrl.'/?path='.$route.$query.$anchor;
        }
        $routeUrl = trim($routeUrl, '?');
        $routeUrl = trim($routeUrl, '/');
        // $routeUrl = str_replace('/entry', '', $routeUrl);
        return $routeUrl;
    }
    public function mkUrl($route=null, $paraArr=array(), $strict=true)
    {
        if($strict){
            $paraArr = array_filter($paraArr);
        }
        if(!$route){
            return rtrim($this->makeUrl('\\', $paraArr, null, null), '\\/');
        }
        return $this->makeUrl($route, $paraArr, null, null);
    }
    /**
    * author: cty@20120326
    *   func: create url 
    *@route --- format:dir1/dir2/.../ctrlId/viewId
    *           getVCLoc('d1/d2/d3/table/struct')
    *           Array
    *            (
    *                [0] => D:\btweb\lffdemo/controller/d1/d2/d3/ctrlTable.php
    *                [1] => D:\btweb\lffdemo/view/d1/d2/d3/table/struct.php
    *            )
    *@getid --- int 1:controller file, 2:view file, null both
    * reutrn: array(ctrlLoc,viewLoc);
    */
    function getVCFile($route, $getid=null)
    {
        $appLoc  = $this->appLoc;
        $dft_ctrl_name = $this->_get_default_controller_name();
        $route   = trim($route, '/');
        if(false === strpos($route,'/')){
            if(0 == strlen($route)) {
                $route = $dft_ctrl_name.'/entry';
            }else {
                $route .= '/entry';
            }
        }
        $segArr = explode('/', $route);
        $len    = count($segArr);
        $viewId = $segArr[$len-1];
        $ctrlId = $segArr[$len-2];
        unset($segArr[$len-1], $segArr[$len-2]);
        $dirList = implode('/', $segArr);
        $dirList = ''==$dirList?'':$dirList.'/';
        $ctrlLoc = $appLoc.'/controller/'.$dirList.'K'.ucfirst($ctrlId).'.php';
        $viewLoc = $appLoc.'/view/'.$dirList.$ctrlId.'/'.$viewId.'.php';
        $locArr  = array($ctrlLoc, $viewLoc);
        if(null === $getid){
            return $locArr;
        }elseif(1 == $getid){
            return $ctrlLoc;
        }elseif(2 == $getid){
            return $viewLoc;
        }
        return $locArr;
    }
    function getRequest($prefix='/')
    {
        $uri = isset($_SERVER['REQUEST_URI'])?$_SERVER['REQUEST_URI']:'';
        if($uri){
            while(strpos($uri, '//')){
                $uri = str_replace('//', '/', $uri);
            }
        }
        return $prefix.trim(trim($uri), '/');
    }
    function getRequestUri($prefix='/')
    {
        return $this->getRequest($prefix);
    }
    /*
    * desc: 获取路由
    *
    *return     http://www.test.me/search/20/b/3?a=t
    *       --->/search/20/b/3
    */
    function getRoute($route=null)
    {
        //获取url中的路由==============================
        if(!$route){
            if($this->route){
                return $this->route; //url中的路由可只获取一次即可
            }
            $URI   = $this->getRequestUri();
            $route = $this->get('path');
            $aliasArr = $this->getConfig('routeAlias');
            if($aliasArr){
                foreach($aliasArr as $fakeRoute => $realRoute){
                    if(false !== strpos($URI, $fakeRoute)){
                        $route = $realRoute;
                        // $this->restparams = $URI; //当前uri作为参数
                        // $this->restArr[]  = $URI;
                        break;
                    }
                }
            }
            if(0 == strlen($route) && $URI) {
                //process REST style urls
                //REST style's urls append to rewrite of APACHE
                //http://www.smartyhub.com/aaa/bbb/?t=139123456 -- route=/aaa/bbb/?t=139123456
                //so, need remove '?' and '?' after characters.
                if(strpos($URI, '?')){
                    // $route = substr($URI, 0, $pos);
                    $route = strstr($URI, '?', true);
                }else{
                    $route = $URI;
                }
            }
            // exit($route);
            $this->route = $route;
        }
        //获取url中的路由===========================end
        return $route;
    }
    /*
    * desc: 获取并整理路由
    *
    *@route       --- str  可显示地设置一个路由(eg.'user/profile')
    *@manulrouted --- bool 标识是否人为显示地运行一个路由(也就是@route显手工传的参数)
    * eg.:
    *       controller
    *       │  KClub.php
    *       └─consultion
    *               KConsultion.php
    *       /123 --> KClub->actionEntry(123作为rest参数)
    *       /consultion/123 KConsultion->actionEntry(123作为rest参数)
    *       /consultion/consultion/ask --
    *                                    | --- KConsultion->actionAsk(如果actionAsk存在)
            /consultion/ask            --                 ->actionEntry(如果actionAsk不存在,ask作为参数)
    */
    function runRoute($route=null, $parameters=null, $manulrouted=false, $subApp=null)
    {
        $route = trim($this->getRoute($route), '/');
        $ctrlLoc = $this->_get_controller_location($subApp);
        if(!isset($route[0])) {//route is a string
            return $this->runDCA($ctrlLoc,null,null,null,null,$parameters);
        }
        $pieceArr = explode('/', $route); //把路由中的路径分成一块一块的
        $FOLDERs  = null;
        foreach ($pieceArr as $k=>$piece) {
            if(is_dir($ctrlLoc. '/'. $FOLDERs. $piece)){
                unset($pieceArr[$k]);
                $this->directory = $FOLDERs .= $piece.'/';
                if(!isset($pieceArr[$k+1])){ //这意味着路由只显示地设置到目录名(controller和action没设置,也没有rest参数)
                    //如果是一个目录,它下面的默认控制器为该目录名
                    return $this->runDCA($ctrlLoc, $route, $FOLDERs, null, null, $parameters);
                }
                continue; //继续寻找目录
            }
            
            // $file = str_replace($piece.'/', replace, subject)
            $controllerClass = 'K'. ucfirst($piece);
            $controller_file = $ctrlLoc. '/'. $FOLDERs. 'K'. ucfirst($piece).'.php';
  
            if(is_file($controller_file)){
                $controller = $piece;
                $action     = isset($pieceArr[$k+1])?$pieceArr[$k+1]:null;
                unset($pieceArr[$k], $pieceArr[$k+1]);
                // $this->restArr[]   = implode('/', $pieceArr);
                return $this->runDCA($ctrlLoc, $route, $FOLDERs, $controller, $action, $parameters);
            }else{
                //如果最后一个为数字
                if(strlen(floatval($piece)) == strlen($piece)){
                    // $this->restArr[]   = implode('/', $pieceArr);
                    return $this->runDCA($ctrlLoc, $route, $FOLDERs, null, null, $parameters);
                }else{
                    $def_controller  = $this->_get_default_controller_name($FOLDERs);
                    $controller_file = $ctrlLoc. '/'. $FOLDERs. 'K'. ucfirst($def_controller).'.php';
                    if(is_file($controller_file)){
                        $action = $piece;
                        // $this->restArr[]   = implode('/', $pieceArr);
                        return $this->runDCA($ctrlLoc, $route, $FOLDERs, $def_controller, $action, $parameters);
                    }
                }
            }
            break; //第一个都不是目录就跳出 print_r
        }
        //至此说明在路由中没找到controller和action========
        if($manulrouted){
            $this->fatalError(); //如果人为显示地传的路由则不加载默认值
        }
        $def_controller  = $this->_get_default_controller_name($FOLDERs);
        return $this->runDCA($ctrlLoc, $route, $FOLDERs, $def_controller, null, $parameters);

        //至此说明在路由中没找到controller和action=====end
        // $this->fatalError(); //没有找到相应action
    }
    function runRouteEx($route=null, $subApp=null)
    {
        $this->runRoute($route, null, true, $subApp);
    }

    function runRouteApi($route=null, $parameters=null)
    {
        $this->runRoute($route, $parameters, true, 'api');
    }
    /*
    * desc: 运行某目录下[默认]控制器[默认]方法
    *@dirs       --- str 目录(a/b/c)
    *@controller --- str 默认控制器(如果没指定则为dirs的最后一个目录名)
    *@action     --- str 默认方法(如果没指定则为entry)
    */
    public function runDCA($ctrlLoc, $route=null, $dirs=null, $controller=null, $action=null, $parameters=null)
    {
        if(empty($controller)){
            $controller  = $this->_get_default_controller_name($dirs); //实际为最一个目录名
        }
        if(empty($action)){
            $action      = 'entry';
        }
        $controllerClass = 'K'. ucfirst($controller);
        $controller_file = $ctrlLoc. '/'. $dirs. 'K'. ucfirst($controller).'.php';
        if(is_file($controller_file)){
            // require $controller_file;
            $this->requireOnce($controller_file);
            if(class_exists($controllerClass,false)){
                $realAction  = 'action'.ucfirst($action);
                if(!method_exists($controllerClass, $realAction)){
                    $action       = 'entry';
                    $realAction   = 'action'.ucfirst($action);
                }
                $this->action     = $action;
                $this->controller = $controller; //要先赋值,不然在下面的iController的构造函数里取不到controller值
                $this->router     = $controller. '/'. $action;
                $this->path       = $dirs . $controller;    //路径
                $this->appRoute   = $dirs . $this->router;  //实际路由
                
                $iController = new $controllerClass;
                $this->_append_rest_params($route, $dirs, $controller, $action);
                $iController->$realAction($parameters); //执行action方法
                return true;
            }
        }
        $this->Exception('The route:'.$route.' does not exists');
        return false;
    }
    private function _append_rest_params($route,$dirs,$controler,$action)
    {
        // $dirs = str_replace('\\', '/', trim($dirs, '/'));
        $_s   = trim($route, '/');
        if($dirs && 0===strpos($_s, $dirs)){
            // $_s = trim(preg_replace("/^$dirs/", '', $_s), '/');
            $_s = trim(substr($_s, strlen($dirs)), '/');
        }
        if($controler && 0===strpos($_s, $controler)) {
            // $_s = trim(preg_replace("/^{$controler}(?:$|\/)/", '', $_s), '/');
            $_s = trim(substr($_s, strlen($controler)), '/');
        }
        if($action && 0===strpos($_s, $action)){
            // $_s = preg_replace("/^{$action}(?:$|\/)/", '', $_s);
            $_s = trim(substr($_s, strlen($action)), '/');
        }
        // $_s = trim($_s, '/');
        if($_s){
            $this->restful[] = explode('/', $_s);
        }
        // print_r($this->restful);
        // $this->dump($this->restful);
    }
    /*
    * desc: route id '/'=>'_'
    *
    */
    function getRouted()
    {
        return trim($this->route, '/');
    }
    function getRouter()
    {
        return trim($this->router, '/');
    }
    function getDirectory($level=0)
    {
        $a = trim($this->directory, '/');
        if($level > 0){
            return implode('/', array_slice(explode('/',$a),0,$level));
        }
        return $a;
    }
    
    /* 
    * 如果url: ...com/d1/d2/ctrl/action
    * return: d1/d2/ctrl
    */
    function getPath()
    {
        return trim($this->path, '/');
    }
    /* 
    * 如果url: ...com/d1/d2/ctrl/action
    * return: ctrl
    */
    function getController()
    {
        return $this->controller;
    }
    /* 
    * 如果url: ...com/d1/d2/ctrl/action
    * return: action
    */
    function getAction()
    {
        return $this->action;
    }
    public function getAppName()
    {
        return $this->subApp;
    }
    /*
    * desc: 以下几个函数是和CRoute中的4个函进行对比
    *       含app是当前路由信息;
    *       不含app是原始路由信息(http请求级的);
    *
    */
    public function getAppRoute($prex='/')
    {
        return $prex . $this->appRoute;
    }
    public function getAppPath()
    {
        $arr  = explode('/', trim($this->appRoute, '/'));
        $arr  = array_slice($arr, 0, -1);
        return implode('/', $arr);
    }
    public function getAppDirectory()
    {
        $arr  = explode('/', trim($this->appRoute, '/'));
        $arr  = array_slice($arr, 0, -2);
        return implode('/', $arr);
    }
    public function getAppRouter()
    {
        $arr  = explode('/', trim($this->appRoute, '/'));
        $arr  = array_slice($arr, -2);
        return implode('/', $arr);
    }
    //end 以上几个函数是和CRoute中的4个函进行对比

    //根据提供的默认值格式化变量
    private function _val_safize($v, $filter=null)
    {
        if(is_null($v) || is_array($v) || !$filter){
            return $v;
        }
        if('trim' == $filter){
            return trim($v);
        }elseif('num' == $filter){
            return floatval($v);
        }elseif('int' == $filter){
            return intval($v);
        }elseif('text' == $filter){
            return preg_replace("/<.*?>/si", '', trim($v));
        }
        return $v;
    }
    function get($key, $default=null, $strict=true, $filter=null)
    {
        $v = isset($_GET[$key])?rawurldecode($_GET[$key]):$default;
        $v = $this->_val_safize($v, $filter);
        if($strict){
            $v = $v?$v:$default;
        }
        return $v;
    }
    function getI($key, $default=null, $strict=true, $filter=null)
    {
        return intval($this->get($key, $default, $strict, $filter));
    }
    function getf($key, $default=null, $strict=true, $filter=null)
    {
        $v = $this->get($key, $default, $strict, $filter);
        return is_null($v)?null:floatval($v);
    }
    function gets($keys, $filter=null, $default=null)
    {
        $keys = trim($keys, ',');
        $keyArr  = explode(',', $keys);
        $dataArr = array();
        foreach($keyArr as $key){
            $dataArr[$key] = $this->get($key, $default, $filter=null);
        }
        return $dataArr;
    }
    function post($key, $default=null, $strict=false, $filter=null)
    {
        $key = trim($key);
        $_v  = isset($_POST[$key])?$_POST[$key]:$default;
        if(is_array($_v))return $_v;
        $_v  = empty($_v)?$_v:rawurldecode($_v);
        $_v  = $this->_val_safize($_v, $filter);
        if($strict){
            $_v = $_v?$_v:$default;
        }
        return $_v;
    }
    function postI($key, $default=null, $strict=true)
    {
        return intval($this->post($key, $default, $strict));
    }
    function postF($key, $default=null, $strict=true)
    {
        $v = $this->post($key, $default, $strict);
        return is_null($v)?null:floatval($v);
    }
    /*
    *keys --- str eg:'*,f1,f2,^f3'
    *
    */
    function posts($keys, $filter=null, $strict=false, $default=null)
    {
        $keys    = trim($keys, ',');
        $keyArr  = explode(',', $keys);
        $dataArr = array();
        $nonkArr = array();
        foreach($keyArr as $key){
            $key = trim($key);
            if('*' == $key){
                $dataArr = array_merge($dataArr, $_POST);
            }else{
                if('^' == $key[0]){
                    $nonkArr[] = trim($key, '^');
                }else{
                    $dataArr[$key] = $this->post($key, $default, $strict, $filter);
                }
            }
        }
        if($nonkArr){
            foreach($nonkArr as $nonk){
                unset($dataArr[$nonk]);
            }
        }
        return $dataArr;
    }
    function req($key, $default=null, $filter=true, $strict=null)
    {
        $v = $this->post($key, null, $strict, $filter);
        if(is_null($v)){
            $v = $this->get($key, $default, $strict, $filter);
        }
        return $v;
    }
    function reqs($keys, $filter=null, $strict=false, $default=null)
    {
        $keys = trim($keys, ',');
        $keyArr  = explode(',', $keys);
        $dataArr = array();
        foreach($keyArr as $key){
            $key = trim($key);
            $dataArr[$key] = $this->req(trim($key), $default, $strict, $filter);
        }
        return $dataArr;
    }
    /*
    * desc: 获取uri中的参数
    *
    *@key     --- str 键为空时反回所有参数
    *@default --- mix 默认值
    *
    */
    public function para($key=null, $default=null, $currented=false)
    {
        $paramsArr  = $this->restful;
        
        $_rest_str_arr = $_rest_int_arr = array();
        $_rest_params  = '';
        if($currented)$paramsArr = array_slice($paramsArr, -1);

        // if(!empty($this->restArr)){
        if(1){
            $_para_number_arr = array(); //数字
            foreach($paramsArr as $paraArr){
                $_rest_params = trim($_rest_params,'/').'/'.ltrim(implode('/', $paraArr));
                $_k_arr = $_v_arr = array(); //字符型变量
                while(list($k,$v) = each($paraArr)){
                    if(ord($v)>ord(9) || strlen($v) != strlen(floatval($v))){//means the '$k' is a string
                        $_k_arr[] = $v;
                        $_v_arr[] = current($paraArr);
                        unset($paraArr[$k], $paraArr[key($paraArr)]);
                    }
                }
                $_rest_str_arr = array_merge($_rest_str_arr, array_combine($_k_arr, $_v_arr));
                if($paraArr){ //剩余的是数字
                    $_para_number_arr = array_merge($_para_number_arr, $paraArr);
                }
            }
            // $this->dump($_rest_str_arr);
            // $this->dump($_para_number_arr);
            if(is_array($_para_number_arr) && count($_para_number_arr) > 0){
                $_para_number_arr = array_values($_para_number_arr);
                for($i=0,$len=count($_para_number_arr); $i<$len; $i++){
                    $k = $i>0 ? 'id'.$i : 'id';
                    $v = $_para_number_arr[$i];
                    $_rest_int_arr[$k] = $v;
                }
            }
        }
        $this->restArr = array_merge($_rest_int_arr, $_rest_str_arr);
        // $this->dump($this->restArr);
        $this->restArr['restparams'] = trim($_rest_params, '/');
        if($key){
            return isset($this->restArr[$key])?$this->restArr[$key]:$default;
        }
        return $this->restArr;
    }
    public function rest($key=null, $default=null, $currented=true, $pos=1)
    {
        if(is_numeric($key)){
            $pos = $key;
            $key = 'restparams';
        }
        $params = $this->para($key, $default, $currented);
        if($pos && is_string($params)){
            if($params){
                $_parr = explode('/', $params);
                $_iiix = $pos - 1;
                return isset($_parr[$_iiix])?$_parr[$_iiix]:$default;
            }else{
                return $default;
            }
        }else{
            return $params;
        }
    }
    public function restp($pos=1, $default=null)
    {
        return $this->rest('restparams', $default, true, $pos);
    }
    public function restful($key=null, $default=null, $currented=false)
    {
        return $this->para($key, $default, $currented);
    }
    public function getHeader($keys, $prex='HTTP_')
    {
        if(is_array($keys)){
            $headerArr = array();
            foreach($keys as $key){
                $key = $prex . strtoupper($key);
                $headerArr[] = isset($_SERVER[$key])?$_SERVER[$key]:null;
            }
            return $headerArr;
        }else{
            $key = $prex . strtoupper($keys);
            return isset($_SERVER[$key])?$_SERVER[$key]:null;
        }
    }
    public function parseUrl($url)
    {
        $locArr = parse_url($url);
        // print_r($locArr);
        return $locArr;
    }
    public function urlAddPara($key, $val, $url=null)
    {
        if(!is_string($url)) $url = $_SERVER['REQUEST_URI'];
    
        $locArr = parse_url($url);
        // print_r($locArr);
        $anchor = isset($locArr['fragment'])?'#'.$locArr['fragment']:'';
        $bname  = basename($locArr['path']);
        if(isset($locArr['query'])) {
            parse_str($locArr['query'], $pArr);
            $pArr[$key] = $val;
            $paras  = http_build_query($pArr);
            $newurl = "$bname?$paras"; //ÉÏ´Îurl
        }else {
            $newurl = "$bname?$key=$val";
        }
        return $newurl.$anchor;
    }
    function renderFile($file, $paraArr=array())
    {
        if(!is_file($file)) return '';
        ob_clean();
        ob_start();
        ob_implicit_flush(false); //don't flush
        if(is_array($paraArr) && count($paraArr)>0) {
            extract($paraArr, EXTR_PREFIX_SAME, 'rend');
        }
        // require($file);
        $this->requireOnce($file);
        return ob_get_clean();
    }
    /*
    *desc: get client ip
    *
    */
    function ip($def=null, $key=null)
    {
        if($key && isset($_SERVER[$key])){
            return $_SERVER[$key];
        }elseif(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
            if(strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',')){//多级反向代理
                return strstr($_SERVER['HTTP_X_FORWARDED_FOR'], ',', true);
            }
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }elseif(isset($_SERVER['HTTP_X_REAL_IP'])){
            return $_SERVER['HTTP_X_REAL_IP'];
        }elseif(isset($_SERVER['REMOTE_ADDR'])){
            return $_SERVER['REMOTE_ADDR'];
        }elseif(isset($_SERVER['SERVER_ADDR'])){
            return $_SERVER['SERVER_ADDR'];
        }
        return $def?$def:'127.0.0.1';
    }
    /*
    *desc: get client port
    *
    */
    function port($key=null)
    {
        if($port = $this->getConfig('port')){
            return $port;
        }
        if($key && isset($_SERVER[$key])){
            return intval($_SERVER[$key]);
        }elseif(isset($_SERVER['HTTP_X_REAL_PORT'])){
            return intval($_SERVER['HTTP_X_REAL_PORT']);
        }elseif(isset($_SERVER['SERVER_PORT'])){
            return intval($_SERVER['SERVER_PORT']);
        }
        return 80;
    }
};