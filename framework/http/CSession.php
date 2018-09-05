<?php
class CSession extends CEle{
    protected $cgimode = 1;
    
    private $domain  = null;
    private $expire  = 0;
    private $cookie  = 'PHPSESSEX';
    private $folder  = '/tmp/sessions';
    private $prefix  = 'PHPS_';
    private $suffix  = '';
    private $enable  = true;
    private $_is_gc  = 0;

    public function options($options=array()){
        if(!is_array($options))return;
        if(isset($options['session_dir'])){
            $this->folder = $options['session_dir'];
        }
        if(isset($options['session_prefix'])){
            $this->prefix = $options['session_prefix'];
        }
        if(isset($options['session_suffix'])){
            $this->suffix = $options['session_suffix'];
        }
        if(isset($options['session_gc'])){
            $this->_is_gc = intval($options['session_gc']);
        }
        if(isset($options['session_cookie'])){
            $this->cookie = $options['session_cookie'];
        }
        if(isset($options['session_domain'])){
            $this->domain = $options['session_domain'];
        }
        if(isset($options['session_expire'])){
            $this->expire = $options['session_expire'];
        }
        if(isset($options['cgimode'])){
            $this->cgimode = $options['cgimode'];
        }
    }
    public function disabled()
    {
        $this->enable = false;
    }
    public function init($sessId, &$error='')
    {
        if(!$this->folder) {
            return false;
        }
        if(!file_exists($this->folder)) {
            @mkdir($this->folder, 0755, true);
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
    *@domain --- str 域名
    *@expire --- int 过期时间
    *
    */
    function start()
    {
        $this->enable = true;

        if($this->_is_gc > 0){//是否回收
            if(mt_rand(1,100) <= $this->_is_gc){//回收概率
                if(2 == $this->cgimode){
                    swoole_timer_after(1, array($this,'gc'));
                }else{
                    $this->gc();//清理
                }
            }
        }

        $sessId = $this->getSessionId();

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
    /*
    * desc: 读取session
    *
    *@sessId --- str session id
    *
    */
    public function read($sessId)
    {
        if(!$this->enable) return false;
        $file = $this->_get_file($sessId);
        if(file_exists($file)) {
            return $_SESSION = json_decode(file_get_contents($file),true);
        }
    }
    /*
    * desc: 写入session
    *
    *@sessId --- str session id
    *
    */
    public function write($sessId, $data)
    {
        if(!$this->enable) return false;
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
    /*
    * desc: 销毁session
    *
    *@sessId --- str session id
    *
    */
    public function destroy($sessId)
    {
        if(!$this->enable) return false;
        $file = $this->_get_file($sessId);
        if(is_file($file)) {
            return @unlink($file);
        }else {
            return false;
        }
    }
    /*
    * desc: 回收session(待续)
    *
    */
    public function gc($dir=null)
    {
        if(is_null($dir)) $dir = $this->folder;
        $handler = opendir($dir);
        while(false !== ($file=readdir($handler)))
        {
            if('.'==$file || '..'==$file || 'System Volume Information'==$file) continue;
            $realpath = rtrim($dir,'/').'/'.$file;
            if(is_dir($realpath)){
                $this->gc($realpath);
            }elseif(0 === strpos($file, 'PHPS_')) {
                if(strtotime(substr($file,5,14))+$this->expire < time()){
                    if(is_file($realpath))@unlink($realpath);
                }
            }
        }
        return closedir($handler);
    }

    /*
    * desc: 获取或生成cookie id
    *
    */
    function getCookieId()
    {
        if(!$this->enable) return false;
        $cookie   = $this->cookie;

        $request  = $this->getRequesting();
        $response = $this->getResponding();

        if(is_object($request) && is_object($response)){ //SWOOLE MODE
            if(isset($request->cookie[$cookie]) && $this->expire>0){
                if(strtotime(substr($request->cookie[$cookie],0,14))+$this->expire < time()){
                    $file = $this->_get_file($request->cookie[$cookie]);
                    if(is_file($file))@unlink($file);
                    unset($request->cookie[$cookie]);
                }
            }
            if(isset($request->cookie[$cookie])){//TMPSESSID
                return $request->cookie[$cookie];
            }else{
                $sid  = date('YmdHis').md5(uniqid(mt_rand(100000,999999),true)).$this->suffix;
                $expire = $this->expire > 0 ? time()+$this->expire+2592000 : 0;
                $request->cookie[$cookie] = $sid;
                $response->cookie($cookie, $sid, $expire, '/', $this->domain);//a month
                return $sid;
            }
        }else{//FPM MODE
            if(isset($_COOKIE[$cookie]) && $this->expire>0){
                if(strtotime(substr($_COOKIE[$cookie],0,14))+$this->expire < time()){
                    $file = $this->_get_file($_COOKIE[$cookie]);
                    if(is_file($file))@unlink($file);
                    unset($_COOKIE[$cookie]);
                }
            }
            if(isset($_COOKIE[$cookie])){//TMPSESSID
                return $_COOKIE[$cookie];
            }else{
                $sid  = date('YmdHis').md5(uniqid(mt_rand(100000,999999),true)).$this->suffix;
                $expire = $this->expire > 0 ? time()+$this->expire+2592000 : 0;
                $_COOKIE[$cookie] = $sid;
                setCookie($cookie, $sid, $expire, '/', $this->domain); //client neednt expire
                return $sid;
            }
        }
    }
    function getSessionId()
    {
        return $this->getCookieId();
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
            if(is_null($val)) {
                unset($sesses[$kvs]);
            }else{
                $sesses[$kvs] = $val;
            }
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
