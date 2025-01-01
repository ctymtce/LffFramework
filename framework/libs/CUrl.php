<?php
class CUrl {
    /**
    * curl GET/POST请求
    *@url       string --- 请求url
    *@paramters array  --- 附加参数
    *         [ get     array --- url参数
    *           headers array --- 请求头数组,
    *           proxy   array --- 代理信息数组,
    *           timeout int   --- 超时时间(s)
    *           ishead  bool  --- 是否将头文件的信息作为数据流输出[false]
    *           &repArr array --- 转储应答信息
    *           loops   int   --- 连接出错时，重复连接的次数
    *         ]
    *@options array --- 控制参数
    *
    */
    static function curlReq($url, $paramters=null, $options=array(), $files=null)
    {
        $method  = isset($options['method'])?$options['method']:'GET';
        $timeout = isset($options['timeout'])?$options['timeout']:5;
        $ishead  = isset($options['ishead'])?$options['ishead']:false;
        $cookie  = isset($options['cookie'])?$options['cookie']:false;
        // $cookie = '/tmp/cookies/curl.cookie.bin';

        $defaults = array( 
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HEADER => $ishead, //是否将头信息作为数据流输出(HEADER信息)
            CURLOPT_RETURNTRANSFER => true, //将curl_exec()获取的信息以文件流的形式返回，而不是直接输出。
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FORBID_REUSE => false, // 在完成交互以后强迫断开连接，不能重用 
        );
        if($cookie){
            $tArr = parse_url($url);
            $cookie_file = sys_get_temp_dir().'/'.ltrim(strstr($tArr['host'],'.'),'.').'.cke';
            $defaults[CURLOPT_COOKIEJAR]  = $cookie_file;
            $defaults[CURLOPT_COOKIEFILE] = $cookie_file;
        }
        $headers = array(
            'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36',
            'Accept: */*',
            'Connection: keep-alive',
            'Accept-Charset: GB2312,utf-8;q=0.7,*;q=0.7', 
            'Cache-Control: max-age=0', 
        );
        if(isset($options['headers']) && is_array($options['headers'])) {
            $headers = array_merge($headers, $options['headers']);
        }
        $ch = curl_init();
        //设置常用参数==============
        curl_setopt_array($ch, $defaults);

        //检查文件和body============
        if(is_array($files) && count($files)>0) { //文件上传
            $isCURLFile = class_exists('CURLFile',false);
            foreach($files as $key => $file){
                if($isCURLFile){
                    $fileobj = new CURLFile(realpath($file));
                    $paramters[$key] = $fileobj;
                }else{
                    $paramters[$key] = '@' . realpath($file);
                }
            }
            // 'pic'=>'@'.realpath($path).";type=".$type.";filename=".$filename
            // print_r($paramters);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $paramters);
            $headers[] = 'Expect: ';
        }else{
            if(is_array($paramters) && $paramters){
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($paramters));
            }elseif(is_string($paramters)){
                curl_setopt($ch, CURLOPT_POSTFIELDS, $paramters);
            }
        }
        
        //设置头信息================
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        //https验证=================
        if((false !== strpos(strtolower($url),'https://'))){
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        }

        //检查代理==================
        if(isset($options['proxy']) && is_array($options['proxy']) && $options['proxy']){
            $pxyArr = $options['proxy'];
            if(isset($pxyArr['pxyHost'])){
                $pxyPort = isset($pxyArr['pxyPort'])?$pxyArr['pxyPort']:80;
                $pxyType = (isset($pxyArr['pxyType'])&&'SOCKS5'==$pxyArr['pxyType'])?CURLPROXY_SOCKS5:CURLPROXY_HTTP; //(CURLPROXY_SOCKS5)
                curl_setopt($ch, CURLOPT_PROXYTYPE, $pxyType);  
                curl_setopt($ch, CURLOPT_PROXY,     $pxyArr['pxyHost']);
                curl_setopt($ch, CURLOPT_PROXYPORT, $pxyPort);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);    //启用时会将服务器服务器返回的“Location:”放在header中递归的返回给服务器
                //授权信息
                if(isset($pxyArr['auth']) && is_array($pxyArr['auth'])){
                    $authArr = $pxyArr['auth'];
                    if(isset($authArr['user']) && is_array($authArr['pswd'])) {
                        $user = $authArr['user'];
                        $pswd = $authArr['pswd'];
                        $proxyAuthType = CURLAUTH_BASIC; //(CURLAUTH_NTLM)
                        curl_setopt($ch, CURLOPT_PROXYAUTH, $proxyAuthType);  
                        $authinfo = "[{$user}]:[{$pswd}]";
                        curl_setopt($ch, CURLOPT_PROXYUSERPWD, $authinfo);
                    }
                }
            }
        }

        //执行请求==================
        do{
            $result = curl_exec($ch);
        }while(false===$result && ($loops=isset($loops)?++$loops:1)<1);

        if(false === $result) return false;
        $infos = curl_getinfo($ch);
        curl_close($ch); 

        // print_r($result);
        // print_r($infos);
        return $result;
    }
    /**
    * desc: send http request by curl 
    * @param string   -- $url target url
    * @param array    -- $postArr post paramters key-val pairs
    * @upArr arr|null -- array('key'=>本地文件路径),es中上传文件基本不用,所以置于最末端
    * return: array, info
    */
    static function curlSend($url, $paramters=array(), $options=array(), $files=null)
    {
        if(!is_array($options)) $options = array();
        $options['method'] = isset($options['method'])?$options['method']:'POST';
        $result = self::curlReq($url, $paramters, $options, $files);
        if(isset($options['format']) && 'json'==$options['format']){
            return json_decode($result, true);
        }
        return $result;
    }
    
    static function curlGet($url, $paramters=array(), $options=array())
    {
        if(!is_array($options)) $options = array();
        $options['method'] = 'GET';
        if($paramters){
            $url .= (strpos(rtrim($url,'?'),'?')?'&':'?').http_build_query($paramters);
        }
        return self::curlReq($url, null, $options);
    }
    static function getJSON($url, $paramters=array(), $options=array())
    {
        return json_decode(self::curlGet($url, $paramters, $options), true);
    }
};