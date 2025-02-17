<?php
/**
 * athor: cty@20120322
 *  func: requestion handler,eg. url,system variables and so on
 *  desc: loc---address(dir) of location
 *        url---address of web
 * 
*/
abstract class CRoute extends CEle {
    
    protected $configs    = array();

    protected $CgiMode    = 1; //1:FPM,2:SWOOLE
    protected $request    = null;
    protected $response   = null;

    /******************URL中path的命名规范***************/
    /*
    *                |-------------path-------|
    * 如果url: ...com/dir123/dir234/controller/action
    *                |--directory--|----router------|
    *                |------------route-------------|
    *                
    */
    protected $urlmode    = 2; //url style[1-compility, 2-REST]
    protected $route      = null; /*
                                    route = directory + controller + action
                                                      or
                                    route = path                   + action
                                  */
    protected $path       = null;
    protected $alias      = null; //alias of route
    protected $truth      = null; //alias opposite route
    protected $router     = null;
    protected $appRoute   = null;
    protected $directory  = null;
    protected $controller = null;
    protected $action     = null;

    protected $restArr    = array();
    protected $restful    = array(); //用于暂存

    /*************end URL中path的命名规范***************/


    /*****************************环境变量参数规划************************/

    public $boot         = ''; //项目根目录(../../myproject)
    public $home         = ''; //当前根URL(http://www.demo.com)
    public $sub          = ''; //当前子项目名称(二级域名名称,如admin)
    public $ui           = ''; //当前子项目的ui(如:fluid)

    public $PrimaryLoc   = '';
    public $ModelLoc     = ''; //当前sub下模型目录
    public $ViewLoc      = ''; //当前sub下视图目录
    public $CtrlLoc      = ''; //当前sub下控制器目录
    public $SubLoc       = ''; //当前sub目录
    public $DaoLoc       = ''; //dao目录
    public $UiLoc        = '';

    public $TPL_LOC      = ''; //smarty模板目录

    public $UiUrl        = ''; //指向当前sub下ui目录
    public $SubUrl       = ''; //指向当前sub目录
    public $AssetsUrl    = ''; //指向当前sub资源目录
    /*************************end 环境变量参数规划************************/

    public $routeKey     = 'path';    
    public $params       = array();    
        
    /*
    * desc: construct function
    *@configs --- array
    */
    public function __construct(&$configs)
    {
        $this->InitConfigs($this->configs=&$configs);
    }
    private function InitConfigs(&$configs)
    {
        if(!isset($configs['boot'])) {
            // exit('The base location does not seted');
            return $this->Exception('The base location does not seted');
        }
        $this->ui           = isset($configs['ui'])?$configs['ui']:'default'; //ui name
        $this->sub          = isset($configs['sub'])?$configs['sub']:'primary';
        $this->home         = isset($configs['home'])?$configs['home']:'/';
        $this->urlmode      = isset($configs['urlmode'])?$configs['urlmode']:2;
        
        $this->boot         = rtrim($configs['boot'], '/');

        $this->SubLoc       = $this->boot.'/'.$this->sub;
        $this->AssetsLoc    = $this->boot.'/assets';
        
        $this->DaoLoc       = $this->boot.'/dao';
        $this->CtrlLoc      = $this->_get_controller_location($this->sub);//$this->SubLoc.'/controller';
        $this->ViewLoc      = $this->SubLoc.'/view';
        $this->ModelLoc     = $this->SubLoc.'/model';

        $this->TPL_LOC      = $this->getConfig('smarty',$this->boot).'/smarty';
        
        $this->AssetsUrl    = '/assets'; //不用绝对路径是为了便于使用不同的域名

        $this->SetUI($this->ui);
        $this->PrimaryLoc   = $this->boot.'/primary'; //item = project
    }
    public function SetUI($ui='default')
    {
        if(empty($ui))return;
        $this->ui = $ui;

        $this->UiUrl   = $this->AssetsUrl.'/ui/'.$ui;

        $this->UiLoc   = $this->AssetsLoc.'/ui/'.$ui;

        $this->TPL_UI  = $this->TPL_LOC .'/'.$ui;
    }

    private function _get_controller_location($sub=null)
    {
        if(null == $sub) $sub = $this->sub;
        return $this->boot .'/'.$sub.'/controller';
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
            return $this->configs;
        }
        if(strpos($key, '.')){
            $vals = $this->configs; //不要设置成引用，否则会改变configArr的值
            foreach(explode('.',$key) as $sub){
                if(!isset($vals[$sub])) return $default;
                $vals = $vals[$sub];
            }
            return $vals;
        }else{
            return isset($this->configs[$key])?$this->configs[$key]:$default;
        }
    }
    /*
    * desc: 设置配置参数
    *
    */
    public function setConfig($kvsOr, $val=null)
    {
        if(is_scalar($kvsOr)){
            $kvsOr = array($kvsOr => $val);
        }
        foreach($kvsOr as $k=>$v){
            $this->configs[$k] = $v;
            if('ui' == $k){
                $this->SetUI($v);
            }
        }
        return $this->configs[$k];
    }
    public function getUserConfig($key=null, $default=null)
    {
        $configs = &$this->configs;
        $userConfig = isset($configs['user'])?$configs['user']:null;
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
    public function getUrl($dir=null)
    {
        if($dir){
            return rtrim($this->home.'/'.ltrim($dir,'/'), '/');
        }else{
            return $this->home . $this->getUri();
        }
    }
    /*
    * desc: url替换
    *   eg: http://guopeikaoshi.demo.com/a?r=1
    *   ->: http://www.demo.com/a?r=1
    *@url --- str 是一完整的url
    *@domain --- str 二级域名的前辍
    *@modifies --- arr 要修改的url项(scheme,host,port,user,pass,path,query,fragment)
    *return: string(new url)
    */
    public function urlTo($url, $domain=null, $modifies=array())
    {
        if(strlen($url) < 5) return $url;
        if($pos_qmark = strpos($url, '?')){
            //不能判断参数后的字符串
            if(strpos(substr($url,0,$pos_qmark), $domain.'.')){
                return $url;
            }
        }
        $tArr = parse_url($url);

        if($domain && isset($tArr['host'])){
            $sArr = explode('.', $tArr['host']);
            if(count($sArr) > 2){
                $sArr[0] = $domain;
            }else{
                array_unshift($sArr, $domain);
            }
            $tArr['host'] = implode('.', $sArr);
        }

        if($modifies){
            $tArr = array_merge($tArr, $modifies);
        }

        $turl = isset($tArr['scheme'])?$tArr['scheme'].'://':'';
        if(isset($tArr['user']) && isset($tArr['pass'])){
            $turl .= $tArr['user'].':'.$tArr['pass'].'@';
        }
        $turl .= $tArr['host'];
        if(isset($tArr['path']))$turl   .= $tArr['path'];
        if(isset($tArr['query']))$turl   .= '?'.$tArr['query'];
        if(isset($tArr['fragment']))$turl .= '#'.$tArr['fragment'];

        return $turl;
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
    * desc: 1, if sub is 'primary' then default routor is 'site'
    *       2, if sub isn't 'primary' then default routor is sub
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
        }
        return 'primary'==$this->sub?'site':$this->sub;
    }
    /*
    * desc: converting url to file path
    *
    *@url    --- str eg:http://www.demo.com/assets/static/def.png
    *@boot   --- str eg:/home/usr/
    *
    *return str eg:/home/usr/assets/static/def.png
    */
    function url2loc($url, $boot=null)
    {
        $tArr = parse_url($url);
        if(!isset($tArr['path']))return null;
        $file = $tArr['path'];
        if(null === $boot){
            $boot = $this->getLoc();
        }
        $file = realpath($boot.$file);
        return $file;
    }
    /*
    * desc: converting file path to url
    *
    *@file   --- str eg:/home/app/assets/static/def.png
    *@place  --- str eg:/assets
    *@domain --- str eg:http://www.demo.com
    *
    *return str eg:http://www.demo.com/assets/static/def.png
    */
    function loc2url($file, $place, $domain=null)
    {
        if(null === $domain){
            $domain = $this->getConfig('home');
        }
        if($pos = strpos($file, $place)){
            $file = substr($file, $pos);
        }
        return $domain.$file;
    }
    /**
    * author: cty@20120326
    *   func: create url
    *@route   --- str(controller/action)
    *@paraArr --- arr url parameters
    *@diffs   --- str 要排除的参数
    *
    * reutrn: url/str;
    */
    public function makeUrl($route=null, $paraArr=array(), $prex=null, $diffs=null)
    {
        if(!is_array($paraArr))$paraArr=array();
        $port = $this->port();
        if(strpos($route, '?')){
            $url_paras = ltrim(strstr($route, '?'),'?');
            parse_str($url_paras, $url_paras);
            if($url_paras){
                if($diffs){
                    $diffArr = explode(',', $diffs);
                    foreach($diffArr as $dkey){
                        unset($url_paras[$dkey]);
                    }
                }
                $paraArr = array_merge($paraArr, $url_paras);
            }
            $route = strstr($route, '?', true);
        }
        if(0 === strpos($route,'http://') || 0 === strpos($route,'https://')){
            $uArr = parse_url($route);
            $prex = $uArr['scheme'].'://'.$uArr['host'];
            if(isset($uArr['port'])){
                $port = $uArr['port'];
            }
            $route = isset($uArr['path'])?$uArr['path']:'';
        }
        $baseUrl = $prex?$prex:$this->getConfig('home','/');
        if(80 != $port){
            $baseUrl  = str_replace(':'.$port, '', $baseUrl);
            $baseUrl .= ':'.$port;
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
        $query = http_build_query($paraArr);
        $query = (strlen($query)>0)?'&'.$query:'';
        if(2 == $this->urlmode) {
            $query = trim($query, '&');
            if(!$route){
                $slash = '';
            }else{
                $slash = ('/'==$route[0] || '/'==substr($baseUrl,-1))?'':'/';
            }
            $routeUrl = $baseUrl.$slash.$route;
            if($query || $anchor) $routeUrl .= '?'.$query.$anchor;
        }else {
            $routeUrl = $baseUrl.'/?path='.$route.$query.$anchor;
        }
        return $routeUrl;
    }
    public function mkUrl($route=null, $paraArr=array(), $diffs=null, $strict=true)
    {
        if($strict && $paraArr){
            $paraArr = array_filter($paraArr);
        }
        if(!$route){
            return rtrim($this->makeUrl('\\', $paraArr, null, $diffs), '\\/');
        }
        return $this->makeUrl($route, $paraArr, null, $diffs);
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
    function getRequest($prefix='/', &$ouri=null, &$huri=null)
    {
        if(is_object($this->request)){
            $ouri = &$this->request->server['request_uri'];
        }else{
            $ouri = isset($_SERVER['REQUEST_URI'])?$_SERVER['REQUEST_URI']:'';
        }
        if($ouri){
            while(false !== strpos($ouri, '//')){
                $ouri = str_replace('//', '/', $ouri);
            }
        }
        $huri = $ouri;
        if($pq = strpos($ouri, '?')){
            $huri = substr($ouri, 0, $pq);
        }
        return $prefix.trim(trim($ouri), '/');
    }
    function getUri($prefix='/')
    {
        return $this->getRequest($prefix);
    }
    function getOUri()
    {
        $this->getRequest(null, $ouri);
        return $ouri;
    }
    function getHUri()
    {
        $this->getRequest(null, $ouri, $huri);
        return $huri;
    }
    function getRequestUri($prefix='/')
    {
        return $this->getUri($prefix);
    }
    /*
    * desc: 检查是否有冗余url路径
    *
    */
    function hasRedUri()
    {
        if($this->action === substr($this->getHUri(), 0-strlen($this->action))){
            return false;
        }
        return true;
    }

    public function AliasRoute($uri, &$alias, &$suffix=null, $level=0)
    {
        $route = $uri = trim($uri,'/');
        foreach($alias as $fake => $real){
            if(0 == $level){
                if($fake === $uri){
                    $this->alias = $uri;
                    $this->truth = $real;
                    return $this->AliasRoute($real, $alias, $suffix, ++$level);
                }elseif(strpos($fake, '*')){
                    $fake = rtrim($fake, '/*');
                    if(0 === strpos($uri, $fake)){
                        $this->alias = $fake;
                        $this->truth = $real;
                        $suffix = substr($uri, strlen($fake));
                        return $this->AliasRoute($real, $alias, $suffix, ++$level);
                    }
                }
            }else{
                if(isset($alias[$uri])) {
                    return $this->AliasRoute($alias[$uri], $alias, $suffix, ++$level);
                }
            }
        }
        return $route;
    }
    /*
    * desc: 获取路由
    *
    *@route   --- str cgi初始化时不要将route传进来(cli时可以传)
    *@firsted --- bool 是否为第一次请求
    *
    *return     http://www.test.me/search/20/b/3?a=t
    *       --->/search/20/b/3
    */
    function getRoute($route=null, $firsted=false)
    {
        //获取url中的路由==============================
        if(null===$route){
            if(true === $firsted){ 
                $this->CleanUp(); //清理场景
            }
            if(false===$firsted && $this->route){
                return $this->route; //url中的路由可只获取一次即可
            }
            $URI = $this->getUri();
            if(strpos($URI, '?')){
                $URI = strstr($URI, '?', true);
            }
            $route = $this->get('path');
            if($URI && ($alias=$this->getConfig('alias'))){
                $route  = $this->AliasRoute($URI, $alias, $params);
                $route .= $params; //params is a string at tail of URI
                // echo "$URI,$route,$params\n";
            }
            if(0 == strlen($route) && $URI) {
                //process REST style urls
                //REST style's urls append to rewrite of APACHE
                //http://www.smartyhub.com/aaa/bbb/?t=139123456 -- route=/aaa/bbb/?t=139123456
                //so, need remove '?' and '?' after characters.
                /*if(strpos($URI, '?')){
                    $route = strstr($URI, '?', true);
                }else{
                    $route = $URI;
                }*/
                $route = $URI;
            }
            // exit($route);
            $this->route = $route = ltrim($route, '/');
        }
        //获取url中的路由===========================end
        return $route;
    }
    function URI2File($route, $sub, &$FOLDERs=null, &$controller=null, &$action=null)
    {
        $route   = rtrim($route, '/');
        $ctrlLoc = $this->_get_controller_location($sub);//controller root dir
        $action  = 'entry'; //default action
        if(!$route){
            $controller  = $this->_get_default_controller_name(); //实际为最一个目录名
            return $ctrlLoc. '/'. 'K'. ucfirst($controller).'.php';
        }
        // echo "route=$route\n";

        $controller_file = null;
        $pieceArr = explode('/', $route); //把路由中的路径分成一块一块的
        foreach($pieceArr as $k=>$piece) {
            if(is_dir($ctrlLoc. '/'. $FOLDERs. $piece)){
                unset($pieceArr[$k]);
                $FOLDERs .= $piece.'/';
                if(!isset($pieceArr[$k+1])){ //这意味着路由只显示地设置到目录名(controller和action没设置,也没有rest参数)
                    //如果是一个目录,它下面的默认控制器为该目录名
                    $controller = $piece;
                    $controller_file = $ctrlLoc.'/'.$FOLDERs. 'K'. ucfirst($controller).'.php';
                    break;
                }
                continue; //继续寻找目录
            }
            $controller_file = $ctrlLoc.'/'.$FOLDERs. 'K'. ucfirst($piece).'.php';
  
            if(is_file($controller_file)){
                $controller = $piece;
                if(isset($pieceArr[$k+1])) $action = $pieceArr[$k+1];
                unset($pieceArr[$k], $pieceArr[$k+1]);
                break;
            }else{
                //如果最后一个为数字
                $controller  = $this->_get_default_controller_name($FOLDERs);
                $controller_file = $ctrlLoc.'/'.$FOLDERs. 'K'. ucfirst($controller).'.php';
                if(is_file($controller_file)){
                    $action = $piece;
                    break;
                }
            }
            break; //第一个都不是目录就跳出
        }
        return $controller_file;
    }
    /*
    * desc: 获取并整理路由
    *       其间不能有退出函数，包括exit,fastcgi_finish_request之类的函数调用
    *@route  --- str  可显示地设置一个路由(eg.'user/profile')
    *@manual --- bool 标识是否人为显示地运行一个路由(也就是@route显手工传的参数)
    *
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
    public function runRoute($route, $parameters=null, $manual=false, $sub=null)
    {
        if(!isset($this->fc_queuing))$this->fc_queuing = array();
        if(!isset($this->fc_mapping))$this->fc_mapping = array();

        if(null === $sub) $sub = $this->sub;
        $rckey = $sub.'.'.$route;

        if(isset($this->fc_queuing[$rckey])){
            $ctrlFile   = $this->fc_queuing[$rckey]['file'];
            $FOLDERs    = $this->fc_queuing[$rckey]['directory'];
            $controller = $this->fc_queuing[$rckey]['controller'];
            $action     = $this->fc_queuing[$rckey]['action'];
        }else{
            $ctrlFile = $this->URI2File($route, $sub, $FOLDERs, $controller, $action);
            if(is_file($ctrlFile)){
                if(isset($this->fc_mapping[$ctrlFile])){//ctrlFile已经被缓存过,则删除之前的
                    //此举是为了防止不同的URI产生相同的路由,以保证fc_queuing不会无限澎涨
                    unset($this->fc_queuing[$this->fc_mapping[$ctrlFile]]);
                }
                $this->fc_mapping[$ctrlFile] = $rckey;
                $this->fc_queuing[$rckey] = array(
                    'file'       => $ctrlFile,
                    'directory'  => $FOLDERs,
                    'controller' => $controller,
                    'action'     => $action,
                );
            }else{
                if($route404 = $this->getConfig('route404')){
                    return $this->runRoute($route404);
                }
                return $this->Exception("The route {$route} does not exists");
            }
        }
        //整理覆盖参数============================
        $this->action     = $action;
        $this->controller = $controller; //要先赋值,不然在下面的iController的构造函数里取不到controller值
        $this->router     = $controller. '/'. $action;
        $this->path       = $FOLDERs . $controller;    //路径
        $this->appRoute   = $FOLDERs . $this->router;  //实际路由
        //整理覆盖参数=========================end
        // print_r($this->fc_queuing);
        // echo ("结果****************\n");
        // echo ("ctrlFile = $ctrlFile\n");
        // echo ("FOLDERs = $FOLDERs\n");
        // echo ("controller = $controller\n");
        // echo ("action = $action\n");
        return $this->runDCA($ctrlFile,$route, $FOLDERs,$controller,$action, $parameters);
    }
    public function runRouteEx($route=null, $parameters=null, $sub=null)
    {
        $route = trim($route, './');
        if(strpos($route, '.')){
            $route = str_replace('.','/',$route);
        }
        if(strpos($route, '?')){//路由带参数
            $puArr = parse_url($route);
            $route = $puArr['path'];
            parse_str($puArr['query'],$params);
            if(!is_array($parameters))$parameters=array();
            $parameters = array_merge($parameters, $params);
        }
        return $this->runRoute($route, $parameters, true, $sub);
    }

    public function runRouteApi($route=null, $parameters=null)
    {
        return $this->runRoute($route, $parameters, true, 'api');
    }
    /*
    * desc: 运行某目录下[默认]控制器[默认]方法
    *@dirs       --- str 目录(a/b/c)
    *@controller --- str 默认控制器(如果没指定则为dirs的最后一个目录名)
    *@action     --- str 默认方法(如果没指定则为entry)
    */
    public function runDCA($ctrlFile, $route=null, $dirs=null, $controller=null, $action=null, $parameters=null)
    {
        $controllerClass = 'K'. ucfirst($controller);

        $clazz = $this->requireOnce($ctrlFile);
        if(false === $clazz) return false;
        if(strpos($clazz, '\\')){
            $controllerClass = $clazz;
        }
        if(class_exists($controllerClass, false)){
            $iController = new $controllerClass;
            $realAction  = 'action'.ucfirst($action);
            if(!method_exists($iController, $realAction)){
                $this->action = $action  = 'entry';
                $realAction = 'action'.ucfirst($action);
            }
            $this->_append_rest_params($route, $dirs, $controller, $action);
            return $iController->$realAction($parameters); //执行action方法
        }
    }
    private function _append_rest_params($route,$dirs,$controler,$action)
    {
        // $dirs = str_replace('\\', '/', trim($dirs, '/'));
        // echo "$route,$dirs,$controler,$action \n";
        $this->restful = array(); //清空
        $_s = trim($route);
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
    * desc: get requesting's route
    *
    */
    public function hasAlias()
    {
        return $this->alias;
    }
    public function getTruth()
    {
        return $this->truth;
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
        return $this->sub;
    }
    /*
    * desc: 以下几个函数是和CRoute中的4个函进行对比
    *       含app是当前路由信息;
    *       不含app是原始路由信息(http请求级的);
    *
    */
    public function getAppRoute($prex='')
    {
        return $prex . ltrim($this->path.'/'.$this->action, '/');
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
    public function files()
    {
        if($this->request){
            return $this->request->files;
        }
        return $_FILES;
    }
    function get($key, $default=null, $strict=true, $filter=null)
    {
        if($this->request){
            $iGET = &$this->request->get;
        }else{
            $iGET = &$_GET;
        }
        $v = isset($iGET[$key])?(is_array($iGET[$key])?$iGET[$key]:rawurldecode($iGET[$key])):$default;
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
    function gets($keys=null, $filter=null, $default=null)
    {
        if(null === $keys){
            if($this->request){
                return $this->request->get;
            }else{
                return $_GET;
            }
        }
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
        if($this->request){
            $iPOST = &$this->request->post;
        }else{
            $iPOST = &$_POST;
        }
        $key = trim($key);
        $_v  = isset($iPOST[$key])?$iPOST[$key]:$default;
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
    function posts($keys=null, $filter=null, $strict=false, $default=null)
    {
        if($this->request){
            $iPOST = &$this->request->post;
        }else{
            $iPOST = &$_POST;
        }
        if(null === $keys){
            return $iPOST;
        }
        $keys    = trim($keys, ',');
        $keyArr  = explode(',', $keys);
        $dataArr = array();
        $nonkArr = array();
        foreach($keyArr as $key){
            $key = trim($key);
            if('*' == $key){
                $dataArr = array_merge($dataArr, $iPOST);
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
    * desc: get php raw content
    *
    */
    function raw()
    {
        if($this->request){
            return $this->request->rawContent();
        }else{
            return file_get_contents("php://input");
        }
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
        $paramsArr     = &$this->restful;
        
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
        $this->restArr['restful'] = trim($_rest_params, '/');
        if($key){
            return isset($this->restArr[$key])?$this->restArr[$key]:$default;
        }
        return $this->restArr;
    }
    public function rest($key=null, $default=null, $currented=true, $pos=1)
    {
        if(is_numeric($key)){
            $pos = $key;
            $key = 'restful';
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
        return $this->rest('restful', $default, true, $pos);
    }
    public function restful($key='restful', $default=null, $currented=false)
    {
        return $this->para($key, $default, $currented);
    }
    public function getHeader($keys, $prex='HTTP_')
    {
        if(is_object($this->request)){
            $iSERVER = array_change_key_case($this->request->server, CASE_UPPER);;
        }else{
            $iSERVER = &$_SERVER;
        }
        if(is_array($keys)){
            $headerArr = array();
            foreach($keys as $key){
                $key = $prex . strtoupper($key);
                $headerArr[] = isset($iSERVER[$key])?$iSERVER[$key]:null;
            }
            return $headerArr;
        }else{
            $key = $prex . strtoupper($keys);
            return isset($iSERVER[$key])?$iSERVER[$key]:null;
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
        if(!is_string($url)) $url = $this->getRequestUri();
    
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
    /*
    *desc: get client ip
    *
    */
    function ip($def=null, $key=null)
    {
        if(is_object($this->request)){
            $iSERVER = array_change_key_case($this->request->server, CASE_UPPER);
        }else{
            $iSERVER = &$_SERVER;
        }
        if($key && isset($iSERVER[$key])){
            return $iSERVER[$key];
        }
        if(2 == $this->CgiMode){
            if(isset($iSERVER['X-FORWARDED-FOR'])){
                return $iSERVER['X-FORWARDED-FOR'];
            }elseif(isset($iSERVER['X-REAL-IP'])){
                return $iSERVER['X-REAL-IP'];
            }elseif(isset($iSERVER['REMOTE_ADDR'])){
                return $iSERVER['REMOTE_ADDR'];
            }
        }else{
            if(isset($iSERVER['HTTP_X_FORWARDED_FOR'])){
                if(strpos($iSERVER['HTTP_X_FORWARDED_FOR'], ',')){//多级反向代理
                    return strstr($iSERVER['HTTP_X_FORWARDED_FOR'], ',', true);
                }
                return $iSERVER['HTTP_X_FORWARDED_FOR'];
            }elseif(isset($iSERVER['HTTP_X_REAL_IP'])){
                return $iSERVER['HTTP_X_REAL_IP'];
            }elseif(isset($iSERVER['REMOTE_ADDR'])){
                return $iSERVER['REMOTE_ADDR'];
            }elseif(isset($iSERVER['SERVER_ADDR'])){
                return $iSERVER['SERVER_ADDR'];
            }
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
        if(2 == $this->CgiMode){
            $iSERVER = array_change_key_case($this->request->server, CASE_UPPER);
        }else{
            $iSERVER = &$_SERVER;
        }
        if($key && isset($iSERVER[$key])){
            return intval($iSERVER[$key]);
        }elseif(isset($iSERVER['HTTP_X_REAL_PORT'])){
            return intval($iSERVER['HTTP_X_REAL_PORT']);
        }elseif(isset($iSERVER['X_REAL_PORT'])){
            return intval($iSERVER['X_REAL_PORT']);
        }elseif(isset($iSERVER['SERVER_PORT'])){
            return intval($iSERVER['SERVER_PORT']);
        }
        return 80;
    }
    /*
    *desc: url redirection
    *
    */
    function location($url=null, $ending=true, $is301=false)
    {
        if(!$url)$url = '/';
        $this->cleanBuffer();
        if($this->response){
            if($is301){
                $this->response->header('HTTP/1.1 301 Moved Permanently');
            }
            $this->response->header("location", $url);
            $this->response->status(302);
            // $this->response->end('');
            // throw new RuntimeExitException('redirect->'. $url);
        }else{
            if($is301){
                header('HTTP/1.1 301 Moved Permanently');
            }
            header("Location: {$url}");
            if($ending)exit(0);
        }
    }
    function method()
    {
        if(2 == $this->CgiMode){
            return $this->request->server['request_method'];
        }else{
            return isset($_SERVER['REQUEST_METHOD'])?$_SERVER['REQUEST_METHOD']:'GET';
        }
    }
    function isPost()
    {
        return 'POST'==$this->method()?true:false;
    }
    function isAjax()
    {
        if(2 == $this->CgiMode){
            $iSERVER = array_change_key_case($this->request->server, CASE_UPPER);
        }else{
            $iSERVER = &$_SERVER;
        }
        $xmlHTTPREQUESTstr = isset($iSERVER['HTTP_X_REQUESTED_WITH'])?strtoupper($iSERVER['HTTP_X_REQUESTED_WITH']):null;

        return 'xmlhttprequest'==$xmlHTTPREQUESTstr ? true : false;
    }
    public function getCookie($key, $default=null)
    {
        if(2 == $this->CgiMode){
            return isset($this->request->cookie[$key])?$this->request->cookie[$key]:$default;
        }else{
            if(!isset($_COOKIE))return $default;
            return isset($_COOKIE[$key])?$_COOKIE[$key]:$default;
        }
    }
    protected function getRef()
    {
        if(2 == $this->CgiMode){
            return isset($this->request->server['referer'])?$this->request->server['referer']:null;
        }else{
            return isset($_SERVER['HTTP_REFERER'])?
                $_SERVER['HTTP_REFERER']:(
                isset($_SERVER['REFERER'])?$_SERVER['REFERER']:null
            );
        }
    }
    protected function Agent()
    {
        if(2 == $this->CgiMode){
            // file_put_contents('/tmp/z.log', json_encode($this->request->server));
            /*
            {
                "request_method":"POST",
                "request_uri":"/st/access",
                "path_info":"/st/access",
                "request_time":1607649491,
                "request_time_float":1607649491.9933,
                "server_port":45005,
                "remote_port":50710,
                "remote_addr":"127.0.0.1",
                "master_time":1607649491,
                "server_protocol":"HTTP/1.0",
                "server_software":"swoole-http-server",
                "connection":"keep-alive",
                "x-real-ip":"127.0.0.1",
                "host":"api.artwaker.com",
                "http_host":"api.artwaker.com",
                "x-forwarded-for":"127.0.0.1",
                "prefix":"api",
                "content-length":"237",
                "accept":"**",
                "user-agent":"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.88 Safari/537.36",
                "content-type":"application/x-www-form-urlencoded; charset=UTF-8",
                "origin":"http://www.artwaker.com",
                "referer":"http://www.artwaker.com/",
                "accept-encoding":"gzip, deflate",
                "accept-language":"zh-CN,zh;q=0.9,en;q=0.8,la;q=0.7"
                }*/
            return isset($this->request->server['user-agent'])?$this->request->server['user-agent']:null;
        }else{
            return isset($_SERVER['HTTP_USER_AGENT'])?
                $_SERVER['HTTP_USER_AGENT']:(
                isset($_SERVER['USER_AGENT'])?$_SERVER['USER_AGENT']:null
            );
        }
    }
    public function Exception($message, $prefix='<pre>', $suffix='</pre>')
    {
        if($this->getConfig('debug')){
            return parent::Exception($message, $prefix, $suffix);
        }
    }
    public function CleanUp()
    {
        $this->path       = null;
        $this->route      = null;
        $this->alias      = null;
        $this->router     = null;
        $this->appRoute   = null;
        $this->directory  = null;
        $this->controller = null;
        $this->action     = null;
        $this->restArr    = array();
        $this->restful    = array();
    }
};