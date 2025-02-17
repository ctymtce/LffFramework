<?php
/**
 * athor: cty@20120322
 *  func: basest class
 *  desc: CEle --- class elements
 *        
 * 
*/
abstract class CEle {
    protected $cgimode  = 1; //1:FPM,2:SWOOLE

    function __call($method, $args)
    {
        $caller = $this->getCaller();
        if(false === $caller) $caller = Lff::App();
        if(method_exists($caller, $method)) {
            switch(count($args))
            {   //to compatible old php versions and imporve performance
                case 0: return $caller->$method();
                case 1: return $caller->$method($args[0]);
                case 2: return $caller->$method($args[0],$args[1]);
                case 3: return $caller->$method($args[0],$args[1],$args[2]);
                case 4: return $caller->$method($args[0],$args[1],$args[2],$args[3]);
                case 5: return $caller->$method($args[0],$args[1],$args[2],$args[3],$args[4]);
                case 6: return $caller->$method($args[0],$args[1],$args[2],$args[3],$args[4],$args[5]);
                default: return call_user_func_array(array($caller, $method), $args);
            }
        }else{
            $this->Exception('The function "'.$method.'" does not exists');
        }
    }
    /*
    * desc: 获取调用者的祖先
    *
    *return object or false if failure
    */
    public function getCaller($property='cgimode')
    {
        //获取调用对象
        if(isset($this->caller)) {
            return $this->caller;
        }
        //there may be Exception called by in __destruct function if u explicitly exit program,
        //because __destruct's caller is by PHP's inner system.
        $traces = debug_backtrace();
        // print_r($traces);
        do{
            $caller = array_pop($traces);
            if(isset($caller['object'])){
                if($property){
                    if(property_exists($caller['object'], $property)){
                        break;
                    }
                }else{
                    break;
                }
            }
        }while($traces);
        if(!isset($caller['object'])) return false;
        if($property){
            if(property_exists($caller['object'], $property)){
                return $this->caller = $caller['object'];
            }
        }else{
            return $this->caller = $caller['object'];
        }
        return false;
    }
    /*
    * desc: 以下两个方法是获取请求/响应对象
    *       为了兼容旧版本，故加ing作区别
    *
    */
    public function getRequesting()
    {
        $caller = $this->getCaller();
        if(!$caller) return false;
        if(!property_exists($caller,'request')) {
            return false;
        }
        return $caller->request;
    }
    public function getResponding()
    {
        $caller = $this->getCaller();
        if(!$caller) return false;
        if(!property_exists($caller,'response')) {
            return false;
        }
        return $caller->response;
    }
    public function cleanBuffer()
    {
        // if(ob_get_length() > 0) ob_clean();
    }
    public function CallStacks($error='', $spliter="\n")
    {
        $trace = explode("\n", (new Exception())->getTraceAsString());
        $trace = array_reverse($trace);
        // array_shift($trace); // remove {main}
        // array_pop($trace);   // remove 当前方法
        $length = count($trace);
        $result = array($error);
        
        for($i=0; $i<$length; $i++){
            $result[] = ($i+1).')'.substr($trace[$i], strpos($trace[$i], ' ')); // replace '#someNum' with '$i)', set the right ordering
        }
        return implode($spliter, $result);
    }
    public function Exception($message, $prefix='<pre>', $suffix='</pre>')
    {
        if(!ini_get('display_errors'))return;
        try {
            throw new Exception($message);
        }catch(Exception $e) {
            if('cli' == PHP_SAPI){
                $prefix = $suffix = '';
            }
            return printf("%s%s%s", $prefix, $e->getMessage()."\n".$e->getTraceAsString(), $suffix);
        }
    }
    public function writeFile($filename, $logs, $mod='a')
    {
        if(2 == $this->cgimode){
            return Swoole_Async_writeFile($filename, $logs, null, FILE_APPEND);
        }
        return file_put_contents($filename, $logs, FILE_APPEND);
    }
    public function writeLog($logs, $basename, $prex='', $mod="a")
    {
        $dir = $this->getConfig('logdir','/var/log');
        if($prex)$prex .= '-';
        $filename = $dir.'/'.$prex.$basename.'.'.date("Ymd").'.log';

        $time = date("Y-m-d.H:i:s");
        /*
        ob_start();
        echo ">>>>>>>>>>>>>>>>>>>>({$time})\n";
        print_r($logs);
        echo "\n";
        echo "<<<<<<<<<<<<<<<<<<<<({$time})\n";
        echo "\n";
        $logconent = ob_get_clean();
        */
        $logconent = ''
        . "\n>>>>>>>>>>>>>>>>>>>>({$time})\n"
        . print_r($logs,true)
        . "\n<<<<<<<<<<<<<<<<<<<<({$time})\n";
        return $this->writeFile($filename, $logconent, $mod);
    }
    function writeError($logs, $prex='')
    {
        return $this->writeLog($logs, 'error', $prex);
    }
    public function httpError($code=500, $errmsg=null, $exited=true)
    {
        ob_clean();
        header("HTTP/1.0 $code");
        if(!empty($errmsg))print_r($errmsg);
        if($exited)exit(0);
    }
    public function fatalError($error, $code=500)
    {
        $this->writeLog($error);
        if($code){
            $this->httpError($code);
        }
        exit(0);
    }
    public function debug($val, $exit=false)
    {
        echo '<pre>';
        if(empty($val))
            var_dump($val);
        else
            print_r($val);
        echo '</pre>';
        $exit && exit(1);
    }
    public function dump($val, $exit=false)
    {
        $this->debug($val, $exit);
    }
    public function requireOnce($file)
    {
        $filekey = 'rqo_'.$file;
        if(!isset($GLOBALS[$filekey])){
            $GLOBALS[$filekey] = include($file);
        }
        return $GLOBALS[$filekey];
    }

    /*
    * call: 
    *   $dataArr = array(
    *      array(
    *          'id' => 1,
    *          'age' => 10,
    *          'name' => 'n1',
    *      ),
    *      array(
    *          'id' => 2,
    *          'age' => 12,
    *          'name' => 'n2',
    *      ),
    *   ;
    *   $this->getArrayColumn($dataArr, 'id', false, false, 'age>=10&&(name=="n2"||name=="n3")')
    *@where --- string eg:(age>10&height>160)
    *
    *return array(must be)
    */
    public function getArrayColumn($dataArr, $field='id', $distinct=true, $filter=false, $where=null)
    {
        if(!$dataArr || is_scalar($dataArr)) return array();
        if($where){
            if(preg_match_all("/([^\s\<\>\!\=\&\(]+)\s*[\<\>\!\=]{1,2}\s*[^\s\<\>\!\=\&\)]+/si", $where, $pArr, PREG_OFFSET_CAPTURE)) {
                array_shift($pArr);
                $dataArr = array_filter($dataArr, function($item)use($pArr,$where){
                    if(1 == count($pArr)){
                        $fArr = array_shift($pArr);
                        $wh = ''; //new where
                        $lp = 0;  //last position
                        $ln = count($fArr); //length of fArr
                        foreach($fArr as $k=>$f_p){//field position
                            list($fd, $po) = $f_p;
                            $va  = isset($item[$fd])?$item[$fd]:null; //value in item
                            if(is_null($va) || is_array($va)){
                                $va = 'null';
                                $qt = null;
                            }else{
                                $qt = is_numeric($va)?null:"'";
                            }
                            $wh .= (substr($where, $lp, $po-$lp).($qt.$va.$qt));
                            $lp  = $po + strlen($fd);//last position
                            if($k == $ln-1){
                                $wh .= substr($where, $lp);
                            }
                        }
                        // echo "$wh \n";
                        $is = eval("return ({$wh});");
                        if(!$is)return false;
                    }
                    return true;
                });
            }
        }
        if(!$field || '*'==$field) return $dataArr;
        if(strpos($field, ',')){//多字段
            $fdArr = array_flip(explode(',', $field));
            return array_map(function($row)use($fdArr){
                return array_intersect_key($row, $fdArr);
            }, $dataArr);
        }else{
            $newArr = array_map(function($row)use($field){
                return isset($row[$field])?$row[$field]:null;
            }, $dataArr);
            if($distinct) $newArr = array_values(array_unique($newArr));
            if($filter)   $newArr = array_filter($newArr);
            return $newArr;
        }
    }
    /*
    * desc: 将二维数组rowArr,subArr连接在一起,
    *       连接的方式是以其中一个字段(这个字段必须是两个数组都含有的)
    *
    *@fields --- str 通常是主键id和外键id(如 id(左表):userid(右表))
    *
        输入:
        rowArr = array(
            'userid' = 123,
            'age' = 22
        )
        subArr = array(
            'id' = 123
            'detail' = ...
        )
        返回:
        array(
            'userid' = 123,
            'age' = 22
            '__sub' => array(
                'id' = 123
                'detail' = ...
            )
        )
    *
    */
    public function joinToArray($rowArr, &$subArr, $fields, $subname='__sub', $overwrite=false, $defaults=array())
    {
        if(!is_array($rowArr) || (!is_array($subArr) && !is_array($defaults))) return $rowArr;
        if(empty($rowArr) || (empty($subArr) && empty($defaults)))return $rowArr;
        // list($pk,$fk) = explode(':', $fields);
        list($kL,$kR) = explode(':', $fields);
        $_subArr = array();
        foreach($subArr as $rs){
            $_subArr[$rs[$kR]] = $rs;
        }
        // $this->dump($_subArr);
        // $this->dump($rowArr);
        foreach($rowArr as &$rr){
            if(!isset($rr[$kL])) continue;
            if(!$overwrite){//如果存在不要覆盖
                if(isset($rr[$subname]) && is_array($rr[$subname]) && count($rr[$subname])>0) continue;
            }
            // $rr[$subname] = isset($_subArr[$rr[$kL]])?$_subArr[$rr[$kL]]:array();
            if(isset($_subArr[$rr[$kL]])){
                $rr[$subname] = $_subArr[$rr[$kL]];
            }elseif(!empty($defaults)){
                $rr[$subname] = $defaults;
            }else{
                $rr[$subname] = array();
            }
        }
        return $rowArr;
    }
    /*
    * desc: 和joinToArray差不多，只是先将subArr转换成树的格式再组装到rowArr
    *       
    *
    *
    */
    public function joinToTrray($rowArr, $subArr, $fields, $subname='__sub')
    {
        if(empty($rowArr) || 
            empty($subArr) || 
            !is_array($rowArr) ||
            !is_array($subArr)
        )return $rowArr;
        list($pk,$fk) = explode(':', $fields);
        // CTool::table2tree($subArr, $fk);
        $subArr = CFun::groupBy($subArr, $fk);
        // $this->dump($subArr);
        foreach($rowArr as &$rr){
            $rr[$subname] = isset($subArr[$rr[$pk]])?$subArr[$rr[$pk]]:array();
        }
        return $rowArr;
    }
    /*
    * desc: 和joinToArray差不多，只是按照skeys平坦了置入Datas
    *     默认是覆盖合并  
    *
    *
    */
    public function joinToField($Datas, $Subs, $fields, $skeys=null, $prefix='', $defs=null, $safe=false)
    {
        if(empty($Datas) || (empty($Subs) && empty($defs))|| !is_array($Datas) || !is_array($Subs))return $Datas;
        list($kL,$kR) = explode(':', $fields);
        $Subs = $this->fieldAsKey($Subs, $kR);
        if(!$skeys){
            $skeys = array_flip(array_keys(current($Subs)));//所有字段
        }else{
            $skeys = array_flip(explode(',', str_replace(array(' '),'',$skeys)));
        }
        foreach($Datas as &$rr){
            if(!isset($rr[$kL])) continue;
            if($safe){
                if(!isset($dkeys)){
                    $dkeys = array_flip(array_keys($rr));
                    $skeys = array_diff_key($skeys, $dkeys);
                }
            }
            $value_left = $rr[$kL];
            if(isset($Subs[$value_left])){
                $tArr = array_intersect_key($Subs[$value_left], $skeys);
                if($prefix){
                    $tArr = array_combine(
                        array_map(function($k)use($prefix){return $prefix.$k;}, 
                            array_keys($tArr)),
                        $tArr
                    );
                }
                $rr = array_merge($rr, $tArr);
            }elseif($defs){
                $rr = array_merge($rr, $defs);
            }
        }
        return $Datas;
    }
    public function safeToField($Datas, $Subs, $fields, $skeys=null, $safe=true)
    {
        return $this->joinToField($Datas, $Subs, $fields, null,'',null, $safe);
    }
    public function fieldAsKey($dataArr, $field='id')
    {
        if(!$dataArr || !is_array($dataArr))return $dataArr;
        $newArr = array();
        foreach($dataArr as $row) {
            if(!isset($row[$field])){
                return $dataArr;
            }
            $fieldval = $row[$field];
            $newArr[$fieldval] = $row;
        }
        return $newArr;
    }
    /*
    * desc: 移除数组中的空(null)值
    *
    *
    */
    public function removeArrayNull(&$array, $emptystring=false, $zero=false, $spval=null)
    {
        if(!is_array($array))return $array;
        foreach ($array as $k=>&$vs){
            if(is_array($vs)){
                if(count($vs) > 0){
                    $this->removeArrayNull($vs, $emptystring, $zero, $spval);
                }
                if(0 == count($vs))unset($array[$k]);
            }else{
                if(is_null($vs)){
                    unset($array[$k]);
                }else if(''===$vs){
                    if($emptystring)unset($array[$k]);
                }else if(0===$vs){
                    if($zero)unset($array[$k]);
                }else if($spval===$vs){
                    unset($array[$k]);
                }
            }
        }
        return $array;
    }
    /*
    * desc: 在一个二维数组中找出key为$key，值为val的项。其他的扔掉
    * @array  --- arr 二维数组      
    * @key  --- int|string 
    * @val --- mixed      
    */
    public function arrayFilter($array, $key, $val)
    {
        $array = array_map(function($record)use($key,$val){
            if(isset($record[$key]) && $val==$record[$key]){
                return $record;
            }
            return null;
        }, $array);
        $this->removeArrayNull($array);
        return $array;
    }
    /*
    * desc: insert a element to array by specied index
    * @array  --- array      
    * @index  --- int|string 
    * @insert --- mixed      
    */
    public function arrayInsert(&$array, $index, $insert, $after=true)
    {
        if(is_int($index)) {
            array_splice($array, $index, 0, $insert);
        }else {
            $place = array_search($index, array_keys($array));
            if($after) $place += 1;
            $array = array_merge(
                array_slice($array, 0, $place),
                $insert,
                array_slice($array, $place)
            );
        }
    }
    public function renameArrayKey(&$array, $fromkey, $tokey)
    {
        if(!isset($array[$fromkey])) return false;
        $fromkey = (string)$fromkey;
        $this->arrayInsert($array, $fromkey, array(
                $tokey=>$array[$fromkey]
            )
        );
        unset($array[$fromkey]);
        return true;
    }
    /*
    * desc: flatize a array
    *
    *@array --- arr
    *@excludekey --- bool it decides wheather override the same keys 
    *
    */
    public function flattingArray($array, $excludekey=true, $isindex=false)
    {
        if($isindex){
            $result = array_reduce($array, function ($result, $value) {
                return array_merge($result, array_values($value));
            }, array());
            return $result;
        }else{
            foreach($array as $k=>$r0001){
                if(is_array($r0001)){
                    unset($array[$k]);
                    if($excludekey){
                        $r0001 = array_diff_key($r0001, $array);
                    }
                    $array = array_merge($array, $this->flattingArray($r0001,$excludekey,$isindex));
                }
            }
            return $array;
        }
    }
    /*
    * desc: similar to array_search
    *
    *@need  --- mix to be searched value
    *@array --- arr to be searched array
    *
    *return array or null
    */
    public function findsArray($need, $sArr)
    {
        if(!is_array($sArr))return null;
        if(!function_exists('inner_searcher')){
            function inner_searcher($need, $sArr, &$foundKeys){
                foreach($sArr as $k => $vals){
                    // echo str_repeat('    ', $deep)."$k \n";
                    if(is_array($vals)){
                        $found = inner_searcher($need, $vals, $foundKeys);
                        if(null !== $found) {
                            $foundKeys[] = $found;
                            return $k;
                        }
                    }else{
                        if($vals == $need){
                            return $k;
                        }
                    }
                }
                return null;
            }
        }
        $top = inner_searcher($need, $sArr, $foundKeys);
        if(null !== $top) {
            if($foundKeys){
                array_push($foundKeys, $top);
                $foundKeys = array_reverse($foundKeys);
            }else{
                $foundKeys = array($top);
            }
        }
        return $foundKeys;
    }
};
