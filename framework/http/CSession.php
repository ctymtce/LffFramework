<?php

class CSession {

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
            if($expired > 0) {
                ini_set('session.cookie_lifetime', $expired);
            }
            session_start();
        }
    }

    /*
    * desc: 生成sessionid
    *
    */
    function getSid($suff='', $domain=null, $expired=0)
    {
        $cookie = $this->cookie;
        $cookArr = &$_COOKIE;
        if(isset($cookArr[$cookie])){
            $sid = $cookArr[$cookie];
        }else{
            $sid = mt_rand(10000000,99999999).'-'.mt_rand(10000000,99999999);
            if(empty($domain)){
                $domain = isset($_SERVER['SERVER_NAME'])?$_SERVER['SERVER_NAME']:null;
            }
            $expired = $expired > 0 ? time()+$expired : 0;
            setcookie($cookie, $sid, $expired, '/', $domain);
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
