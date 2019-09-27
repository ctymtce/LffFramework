<?php
class CSession extends CEle{

    private $domain   = null;
    private $expire   = 0;
    private $cookie   = 'PHPSESSEX';
    private $folder   = '/tmp/sessions';
    private $prefix   = 'PHPS_';
    private $suffix   = '';
    private $enable   = true;
    private $_is_gc   = 0;
    private $CgiMode  = 1;
    private $postpone = 0;

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
            if('domain' == $options['session_domain']){
                if(isset($options['domain'])){
                    $this->domain = $options['domain'];
                }
            }else{
                $this->domain = $options['session_domain'];
            }
        }
        if(isset($options['session_postpone'])){
            $this->postpone = $options['session_postpone'];
        }
        if(isset($options['session_expire'])){
            $this->expire = $options['session_expire'];
        }
        if(isset($options['CgiMode'])){
            $this->CgiMode = $options['CgiMode'];
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
                if(2 == $this->CgiMode){
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
            // $this->write($sessId, array());
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
        return file_put_contents($this->_get_file($sessId), json_encode($data,128));
        /*$file = $this->_get_file($sessId);
        if(2 == $this->CgiMode && is_file($file)){
            return Swoole_Async_writeFile($file, json_encode($data));
        }else{
            return file_put_contents($file, json_encode($data));
        }*/
    }
    private function _get_file($sessId)
    {
        return $this->folder.'/'.$this->prefix.$sessId;
    }
    private function _mk_sid(&$expire=0)
    {
        $sptime = time(); //以当前时间为id
        $expire = $sptime+$this->_expire();
        return date('YmdHis',$sptime).md5(uniqid(mt_rand(100000,999999),true)).$this->suffix;
    }
    private function _expire()
    {
        return $this->expire > 0 ? $this->expire : 86400;
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
    * desc: cookie延期
    * call: $this->getSession()->postpone(null,true);
    *
    *@forced --- bool 强制延期
    *
    */
    function postpone($previd, $forced=false)
    {
        if(!$forced && $this->postpone <= 0) return $previd;
        
        $cookie   = $this->cookie;
        $cdtime   = date('YmdHis', time());
        $expireAt = time()+$this->_expire();

        if(null === $previd){//auto
            if(2==$this->CgiMode && isset($this->request) && isset($this->response) && $this->request && $this->response){
                if(isset($this->request->cookie[$cookie])){
                    $previd = $this->request->cookie[$cookie];
                }else{
                    $previd = $this->_mk_sid($expireAt);
                }
            }else{
                if(isset($_COOKIE[$cookie])){
                    $previd = $_COOKIE[$cookie];
                }else{
                    $previd = $this->_mk_sid($expireAt);
                }
            }
        }

        $prev_expire = substr($previd, 0, 14);
        $prev_expireAt = strtotime($prev_expire)+$this->_expire();
        if(!$forced && $prev_expireAt-time() > $this->postpone){//postpone:int(seconds)
            return $previd;
        }

        $willid = $cdtime.substr($previd, 14);
        $prev_file = $this->_get_file($previd);
        $will_file = $this->_get_file($willid);
        
        if(rename($prev_file, $will_file)){
            if(2==$this->CgiMode && isset($this->request) && isset($this->response) && $this->request && $this->response){
                $request->cookie[$cookie] = $willid;
                $this->cookie($cookie, $willid, 0, '/', $this->domain);//clean
                $this->cookie($cookie, $willid, $expireAt+604800, '/', $this->domain);
            }else{
                $_COOKIE[$cookie] = $willid;
                setCookie($cookie, $willid, 0, '/', $this->domain); //clean
                setCookie($cookie, $willid, $expireAt+604800, '/', $this->domain);
            }
        }
        return $willid;
    }
    /*
    * desc: 获取或生成cookie id
    *
    */
    function getCookieId()
    {
        if(!$this->enable) return false;
        $cookie   = $this->cookie;

        if(2 == $this->CgiMode && (is_object($request=$this->getRequesting()) && is_object($response=$this->getResponding()))){ //SWOOLE MODE
            $this->request  = $request;
            $this->response = $response;
            if(isset($request->cookie[$cookie])){
                if(strtotime(substr($request->cookie[$cookie],0,14))+$this->_expire() < time()){
                    $file = $this->_get_file($request->cookie[$cookie]);
                    if(is_file($file))@unlink($file);
                    $response->cookie($cookie, $request->cookie[$cookie], 0, '/', $this->domain);
                    unset($request->cookie[$cookie]);
                }
            }
            if(isset($request->cookie[$cookie])){//TMPSESSID
                return $this->postpone($request->cookie[$cookie]);
                // return $request->cookie[$cookie];
            }else{
                $sessId = $this->_mk_sid($expireAt);
                $request->cookie[$cookie] = $sessId;
                // $response->cookie($cookie, $sessId, 0, '/', $this->domain);//clean
                $response->cookie($cookie, $sessId, $expireAt+604800, '/', $this->domain);
                return $sessId;
            }
        }else{//FPM MODE
            if(isset($_COOKIE[$cookie])){
                if(strtotime(substr($_COOKIE[$cookie],0,14))+$this->_expire() < time()){
                    $file = $this->_get_file($_COOKIE[$cookie]);
                    //临时log
                    /*$logs = array('expired===============');
                    $logs[] = $this->read($_COOKIE[$cookie]);
                    file_put_contents('/tmp/sessions/z.log', json_encode($logs,128), FILE_APPEND);*/
                    if(is_file($file))@unlink($file);
                    setCookie($cookie, $_COOKIE[$cookie], 0, '/', $this->domain);
                    unset($_COOKIE[$cookie]);
                }
            }
            if(isset($_COOKIE[$cookie])){//TMPSESSID
                return $this->postpone($_COOKIE[$cookie]);
                // return $_COOKIE[$cookie];
            }else{
                $sessId = $this->_mk_sid($expireAt);
                $_COOKIE[$cookie] = $sessId;
                // setCookie($cookie, $sessId, 0, '/', $this->domain); //clean
                setCookie($cookie, $sessId, $expireAt+604800, '/', $this->domain); //client neednt expire
                return $sessId;
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
        // $sesses['expire'] = $this->_expire();
        // $sesses['domain'] = $this->domain;
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
