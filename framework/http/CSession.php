<?php
class CSession extends CEle{
    protected $cgimode = 1;
    
    private $cookie  = 'PHPSESSEX';
    private $domain  = null;
    private $folder  = '/tmp/sessions';
    private $prefix  = 'PHPS_';

    public function options($options=array()){
        if(!is_array($options))return;
        if(isset($options['session_dir'])){
            $this->folder = $options['session_dir'];
        }
        if(isset($options['session_prefix'])){
            $this->prefix = $options['session_prefix'];
        }
        if(isset($options['session_cookie'])){
            $this->cookie = $options['session_cookie'];
        }
        if(isset($options['cgimode'])){
            $this->cgimode = $options['cgimode'];
        }
    }

    public function init($sessId, &$error='')
    {
        if(!$this->folder) {
            return false;
        }
        if(!file_exists($this->folder)) {
            @mkdir($this->path, 0755, true);
        }
        if(!file_exists($this->folder) || !is_writable($this->folder)) {
            $error = 'Session directory is unwritable';
            return false;
        }
        return true;
    }
    /*
    * desc: 启动session
    *
    *@sid 这里的sid其实是一个后辍
    *
    */
    function start($mixId=null, $domain=null, $expired=86400)
    {
        $this->domain = $domain;
        $sessId = $this->getSessionId($mixId, $domain, $expired);

        $file = $this->_get_file($sessId);

        if(!$this->init($sessId, $error)){
            throw new Exception($error, 1);
            return false;
        }

        if(!is_file($file)){
            $this->write($sessId, array());
        }
        return true;
    }
    /**
     * @inheritDoc
     */
    public function read($sessId)
    {
        $file = $this->_get_file($sessId);
        if(file_exists($file)) {
            return $_SESSION = json_decode(file_get_contents($file),true);
        }
    }
    /**
     * @inheritDoc
     */
    public function write($sessId, $data)
    {
        return file_put_contents($this->_get_file($sessId), json_encode($data));
        /*$file = $this->_get_file($sessId);
        if(2 == $this->cgimode && is_file($file)){
            return Swoole_Async_writeFile($file, json_encode($data));
        }else{
            return file_put_contents($file, json_encode($data));
        }*/
    }
    private function _get_file($sessId)
    {
        return $this->folder.'/'.$this->prefix.$sessId;
    }
    /**
     * @inheritDoc
     */
    public function destroy($id)
    {
        if($file = $this->_get_file($id)) {
            return @unlink($file);
        }else {
            return false;
        }
    }
    /**
     * @inheritDoc
     */
    public function timeout($timeout=30)
    {
        $this->timeout = $timeout;
    }
    private function gc()
    {
        /*$iterator = new \DirectoryIterator($this->path);
        $now = time();
        foreach ($iterator as $file) {
            if ($file->isDot()) {
                continue;
            }
            if ($file->getMTime() < $now - $this->timeout) {
                @unlink($file->getRealPath());
            }
        }*/
    }

    /*
    * desc: 生成sessionid
    *
    */
    function getCookieId($mixId=null, $domain=null, $expired=0)
    {
        $cookie   = $this->cookie;

        $request  = $this->getRequesting();
        $response = $this->getResponding();

        if($request && $response){ //SWOOLE MODE
            if(isset($request->cookie[$cookie]) && $expired>0){
                if(strtotime(substr($request->cookie[$cookie],5,14))+$expired < time()){
                    $file = $this->_get_file($request->cookie[$cookie]);
                    if(is_file($file))@unlink($file);
                    unset($request->cookie[$cookie]);
                }
            }
            if(isset($request->cookie[$cookie])){//TMPSESSID
                return $request->cookie[$cookie];
            }else{
                $sid  = date('YmdHis').md5($mixId.uniqid(mt_rand(100000,999999),true));
                $request->cookie[$cookie] = $sid;
                $response->cookie($cookie, $sid/*, $expired, '/', $domain*/);
                return $sid;
            }
        }else{//FPM MODE
            if(isset($_COOKIE[$cookie]) && $expired>0){
                if(strtotime(substr($_COOKIE[$cookie],5,14))+$expired < time()){
                    $file = $this->_get_file($_COOKIE[$cookie]);
                    if(is_file($file))@unlink($file);
                    unset($_COOKIE[$cookie]);
                }
            }
            if(isset($_COOKIE[$cookie])){//TMPSESSID
                return $_COOKIE[$cookie];
            }else{
                $sid  = date('YmdHis').md5($mixId.uniqid(mt_rand(100000,999999),true));
                /*$expired = $expired > 0 ? time()+$expired+10 : 0;*/
                $_COOKIE[$cookie] = $sid;
                setCookie($cookie, $sid, 0, '/', $domain); //client neednt expired
                return $sid;
            }
        }
    }
    function getSessionId($mixId=null, $domain=null, $expired=0)
    {
        return $this->getCookieId($mixId, $domain, $expired);
    }

    function get($key, $default=null)
    {
        $sesses = $this->read($this->getSessionId());
        return isset($sesses[$key])?$sesses[$key]:$default;
    }

    function gets($keys, $default=null)
    {
        $keys = trim($keys, ',');
        $keyArr  = explode(',', $keys);
        $dataArr = array();
        foreach($keyArr as $key){
            $dataArr[$key] = $this->get($key, $default);
        }
        return $dataArr;
    }
    function all()
    {
        return $this->read($this->getSessionId());
    }
    function set($kvs, $val=null)
    {
        $sessId = $this->getSessionId();
        $sesses = $this->read($sessId);

        if(empty($sesses) || !is_array($sesses)) {
            $sesses = array();
        }
        if(is_array($kvs)){
            foreach($kvs as $k=>$v){
                $sesses[$k] = $v;
            }
        }else{
            $sesses[$kvs] = $val;
        }

        $ok = $this->write($sessId, $sesses);
        return $ok?$val:false;
    }

    function sets($kvArr)
    {   
        foreach($kvArr as $key => $value) {
            $this->set($key, $value);
        }
        return true;
    }

    function remove($key)
    {
        $sessId = $this->getSessionId();
        $sesses = $this->read($sessId);
        unset($sesses[$key]);
        return $this->write($sessId, $sesses);
    }
    function clean()
    {
        return $this->destroy($this->getSessionId());
    }
};