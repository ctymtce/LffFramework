<?php
class CSession extends CEle{

    private $cookie = 'PHPSESSEX';
    private $domain = null;
    private $folder = '/tmp/sessions';
    private $prefix = 'PHPS_';

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
        if(is_file($file) && $expired) { //expired
            if(filectime($file)+$expired < time()){
                echo "delete: $file\n";
                unlink($file);
                return $this->start($mixId,$domain,$expired);
            }
        }

        if(!$this->init($sessId, $error)){
            throw new Exception($error, 1);
            return false;
        }

        if(!is_file($file)){
            // echo "create: $file\n";
            $this->write($sessId, array());
            chmod($file, 0755);
        }else{
            // var_dump('yyyyyyyyyyy');
        }
        return true;
    }
    /**
     * @inheritDoc
     */
    public function read($id)
    {
        if(file_exists($this->folder . '/' . $id)) {
            return $_SESSION = unserialize(file_get_contents($this->folder . '/' . $id));
        }
    }
    /**
     * @inheritDoc
     */
    public function write($id, $data)
    {
        return file_put_contents($this->_get_file($id), serialize($data));
    }
    private function _get_file($id)
    {
        return $this->folder.'/'.$id;
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
            if(isset($request->cookie[$cookie])){//TMPSESSID
                return $request->cookie[$cookie];
            }else{
                $sid = md5($mixId.uniqid(mt_rand(100000,999999),true));
                $request->cookie[$cookie] = $cookie;
                $response->cookie($cookie, $sid/*, $expired, '/', $domain*/);
                return $sid;
            }
        }else{//FPM MODE
            if(isset($_COOKIE[$cookie])){//TMPSESSID
                return $_COOKIE[$cookie];
            }else{
                $sid = md5($mixId.uniqid(mt_rand(100000,999999),true));
                /*if(empty($domain)){
                    $domain = isset($_SERVER['SERVER_NAME'])?$_SERVER['SERVER_NAME']:null;
                }
                $expired = $expired > 0 ? time()+$expired : 0;*/
                $_COOKIE[$cookie] = $cookie;
                setCookie($cookie, $sid, $expired, '/', $domain);
                return $sid;
            }
        }
    }
    function getSessionId($mixId=null, $domain=null, $expired=0)
    {
        return $this->prefix.$this->getCookieId($mixId);
    }

    function get($key, $default=null)
    {
        $sesses = $this->read($this->getSessionId());
        if(!$sesses || !isset($sesses[$key])) return $default;
        return $sesses[$key];
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
        return isset($_SESSION)?$_SESSION:array();
    }
    function set($key, $val)
    {
        $sessId = $this->getSessionId();
        $sesses = $this->read($sessId);
        if(!$sesses || !is_array($sesses)) {
            $sesses = array();
        }
        $sesses[$key] = $val;
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






















class CSession2 {

    private $cookie = 'TMPSESSID';
    private $domain = null;

    /*
    * desc: 启动session
    *
    *@sid 这里的sid其实是一个后辍
    *
    */
    function start($sid=null, $domain=null, $expired=0)
    {
        $this->domain = $domain;
        if($sid){
            $sid = $this->getSid($sid, $domain, $expired);
            session_id($sid);
        }
        if(!isset($_SESSION)){
            var_dump('nnnnnnnnnnnn');
            if($expired > 0) {
                ini_set('session.cookie_lifetime', $expired);
            }
            session_start();
            $_SESSION['tttt'] = 'aaaaaaaaaaaaaa';
        }else{
            var_dump('yyyyyyyyyyy');
        }
        var_dump($_SESSION);
    }

    /*
    * desc: 生成sessionid
    *
    */
    function getSid($suff='', $domain=null, $expired=0)
    {
        $cookie = $this->cookie;
        if(isset($_COOKIE[$cookie])){//TMPSESSID
            $sid = $_COOKIE[$cookie];
        }else{
            $sid = mt_rand(10000000,99999999).'-'.mt_rand(10000000,99999999);
            if(empty($domain)){
                $domain = isset($_SERVER['SERVER_NAME'])?$_SERVER['SERVER_NAME']:null;
            }
            $expired = $expired > 0 ? time()+$expired : 0;
            // setcookie($cookie, $sid, $expired, '/', $domain);
            Cgi::Cookie($cookie, $sid);
        }
        return strtoupper(md5($sid.$suff));
    }

    function get($key, $default=null)
    {
        if(isset($_SESSION[$key])) return $_SESSION[$key];
        return $default;
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
        return isset($_SESSION)?$_SESSION:array();
    }
    function set($key, $val)
    {
        return $_SESSION[$key] = $val;
    }

    function sets($kvArr)
    {   
        foreach($kvArr as $key => $value) {
            $_SESSION[$key] = $value;
        }
    }

    function add($key, $val)
    {
        return $this->set($key, $val);
    }

    function remove($key)
    {
        if(isset($_SESSION[$key])) unset($_SESSION[$key]);
    }
    function clean()
    {
        session_destroy();
    }

    function setCookie($key, $val, $expired=0, $path='/')
    {
        $domain = $this->domain;
        return setcookie($key, $val, $expired, $path, $domain);
    }
    function getCookie($key, $default=null)
    {
        return (isset($_COOKIE[$key]))?$_COOKIE[$key]:null;
    }

    function pushMessage($msg)
    {
        $_SESSION['___sessionMessage'] = $msg;
    }
    function flushMessage()
    {
        if(isset($_SESSION['___sessionMessage'])){
            $msg = $_SESSION['___sessionMessage'];
            unset($_SESSION['___sessionMessage']);
            return $msg;
        }
        return null;
    }
    function pushUrl($url)
    {
        $this->set('___sessionUrl', $url);
    }
    function flushUrl()
    {
        $url = $this->get('___sessionUrl');
        $this->remove('___sessionUrl');
        return $url;
    }
};
