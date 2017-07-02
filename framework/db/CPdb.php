<?php
/**
 * author: cty@20120301
 *   desc: 数据库pdo管理类
 *         此class只提供了最基础的几个方法，如:execute,query等;
 *         具体的sql的生成在各驱动class里时行
 *   call: $db = new C[Driver](array(...));
 *         $db->方法名(...);
 *
 *
*/

abstract class CPdb {
    
    protected $pdb     = null;
    protected $error   = null;
    protected $errno   = null;
    protected $warning = null;
    protected $sqls    = array();
    protected $preview = false;

    protected $dsn     = null;
    protected $user    = 'root';
    protected $pswd    = 'root';
    protected $driver  = null;
    protected $alias   = null; //用于调试

    function __construct($paraArr=array())
    {
        $this->connect($paraArr);
    }
    protected function connect($paraArr=array(), $reconnect=false)
    {
        if(null === $this->pdb || $reconnect) {
            extract($paraArr);
            $this->dsn    = isset($dsn)?$dsn:$this->dsn;
            $this->user   = isset($user)?$user:$this->user;
            $this->pswd   = isset($pswd)?$pswd:$this->pswd;
            $this->alias  = isset($alias)?$alias:$this->alias;
            $this->driver = isset($driver)?$driver:$this->driver;
            try {
                $this->pdb = new PDO($this->dsn, $this->user, $this->pswd, array(PDO::ATTR_TIMEOUT=>3));
            }catch(PDOException $e){
                $this->error = $e->getMessage();
                $this->pdb = null;
                throw new Exception('Connecting db was failure!');
            }
        }
        return $this->pdb;
    }
    public function getConfig($key=null)
    {
        if($key){
            return $this->$key;
        }else{
            return array(
                'dsn'    => $this->dsn,
                'user'   => $this->user,
                'pswd'   => $this->pswd,
                'driver' => $this->driver,
                'alias'  => $this->alias,
            );
        }
    }
    protected function ensureAlive()
    {
        if(null === $this->pdb) {
            return $this->reconnect();
        }
        try{
            ob_start();
            $linkstring = $this->pdb->getAttribute(PDO::ATTR_SERVER_INFO);
            $linkstring = ob_get_clean();
        }catch(PDOException $e){
            $linkstring = $e->getMessage();
        }
        if(strpos($linkstring, 'server has gone away')){
            return $this->reconnect();
        }
        return $this->pdb;
    }
    public function reconnect()
    {
        return $this->connect(array(), true);
    }
    public function preview($preview=true)
    {
        $this->preview = $preview;
        return $this;
    }
    
    public function query($sql, $multi=true)
    {
        return $this->execute($sql, 0, $multi);
    }    
    /*
    * desc: execut sql
    *@etype --- int(-1:delete,0:select,1:insert,2:update)
    *              select目前不存在
    *@multi --- bool 针对查询
    *
    */
    abstract protected function getInsertId();//不同驱动获取方法不一样
    public function execute($sql, $etype=0, $multi=true)
    {
        if(null === $this->pdb) return false;
        $this->sqls[] = $sql;
        $rs = $this->ensureAlive()->prepare($sql);
        if(!$rs) {
            $errArr = $this->pdb->errorInfo();
            $this->error = $errArr[2];
            return false;
        }
        switch($etype) {
            case  0: //select
                if($exe = $rs->execute()){
                    if($multi){
                        return $rs->fetchAll(PDO::FETCH_ASSOC);
                    }else{
                        return $rs->fetch(PDO::FETCH_ASSOC);
                    }
                }
                break;
            case -1: //delete
            case  1: //insert
                $exe = $rs->execute(); break;
            case  9: //create
                $exe = $rs->execute(); break;
            default:
                $exe = $rs->execute();
        }
        // var_dump($this->pdb->lastInsertId());
        if(!$exe) {
            $errArr = $rs->errorInfo();
            $this->error = $errArr[2];
            return false;
        }else{
            if(1 == $etype){ //insert
                return $this->getInsertId();
            }else if(in_array($etype, array(-1,2))){
                return $rs->rowCount(); //影响的行数
            }
        }
        return $exe;
    }
    /*
    * desc: update table
    *
    *@table   --- string table name
    *@valArr  --- array
    *                 field => value, ...)
    *@whs     --- array(
    *                 field => value, ...)
    *           ||string:'field=value,...'
    *
    */
    public function update($table, $valArr, $whs, $exArr=array())
    {
        if(empty($valArr)) return false;
        $backquote = isset($exArr['backquote'])?$exArr['backquote']:'`';
        if(!strpos($table, '.')){
            $table = $backquote.trim($table, $backquote).$backquote;
        }
        //where条件...
        $cdtions = $this->parseWhere($whs);
        if($cdtions){
            $cdtions = ' where '. $cdtions;
        }else {
            $this->warning = 'Updating conditions are null';
        }
        //end where条件
        //要更新的值...
        $_valArr = array();
        foreach($valArr as $f=>$val) {
            // $val = "'".$val."'";
            $val = $this->valCorrectize($val, $f, $exArr);
            $f   = $backquote. trim($f,$backquote). $backquote;
            $_valArr[$f] = $val;
        }
        $arg_old = ini_get('arg_separator.output');
        ini_set('arg_separator.output', ',');
        $vallist = urldecode(http_build_query($_valArr));
        ini_set('arg_separator.output', $arg_old);
        //end要更新的值

        $sql = "update {$table} set {$vallist} {$cdtions}";
        if(isset($exArr['limit']) && intval($exArr['limit'])>0){
            $sql .= ' limit '.$exArr['limit'];
        }
        try{
            // $this->execute('set names utf8');
            $ok = $this->execute($sql, 2);
            if(false === $ok) 
                throw new Exception();
            else
                return $ok;
        }catch(Exception $e){
            // var_dump($e);
            // $this->error = $e->getMessage();
            $this->setError();
            return false;
        }
    }
    /*
    * desc: 组织where(基于树模型)
    *  如：(city=1 and sex=0) or/and (city=2 and sex=1)
    *   第一个城市的女性"或/且"第二个城市的男性
    *   array(
    *       'or/and' => array(
    *           array(
    *              'city' => 1,
    *              'sex' => 0
    *           ),
    *           array(
    *              'city' => 2,
    *              'sex' => 1
    *           )
    *       )
    *   )
    */
    public function parseWhere($whArr, $andordft='and', $depth=0)
    {
        if(!$whArr)return null;
        if(is_scalar($whArr))return $whArr;
        $loops = 0;   //循环的次数
        $where = '';  //生成的最终where字符串
        $space = ' '; //空格
        $depth = $depth + 1;
        //andorfield可能是and or 或字段名
        //whorvalue可能是下一组条件或字段对应的值
        foreach($whArr as $andorfield => $whorvalue){
            if(in_array(strtolower($andorfield),array('and','or')) || is_numeric($andorfield)){
                if(is_array($whorvalue)){
                    if(empty($whorvalue))continue;
                    // $andordft_cur = is_numeric($andorfield)?$andordft:$andorfield;
                    $andordft_cur = is_numeric($andorfield)?$andordft:$andordft;
                    $andordft_sub = is_numeric($andorfield)?'and':$andorfield;
                    $subwh = $this->parseWhere($whorvalue, $andordft_sub, $depth);
                    
                    $bL = $bR = '';   //左括号,右括号
                    if(count($whorvalue)>1){//只有一个就不加括号
                        $bL = '(';   $bR = ')';
                    }
                    //注意:如果depth>1那么操作符(and,or)要使用上一维的操作符
                    $where .= (0==$loops?'':$space.trim($depth>1?$andordft:$andordft_cur)) .$space. $bL .$subwh. $bR;
                    $where  = trim($where);
                }else{
                    $where .= ($loops>0?$space.$andordft:null).$space.$whorvalue;
                }
            }else{
                //这里的andorfield为字段名
                $value = $whorvalue;
                if(is_string($value))$value = "'" . addslashes($value) . "'";
                $andorfield = trim($andorfield);
                if(strpos($andorfield, ' ')){
                    $field = strstr($andorfield, ' ', true);
                }else{
                    // $field = trim(preg_replace("/(\s*[`a-z0-9\_]+\s*).*/i", '$1', $andorfield));//字段
                    $field = rtrim(rtrim($andorfield, '!<>=?%*^'));
                }
                $op    = substr($andorfield, strlen($field));
                $op    = strtolower(trim($op?$op:'='));
                // var_dump($field);
                // var_dump($op);
                /*$fvArr = $this->_value_safize(array($field=>$value), true);
                if(empty($fvArr))continue;
                $value = $fvArr[$field];*/
                $whone = '';
                switch($op){
                    case '%': //%v%
                    case '*': // v%
                        $value = trim($value, "'");
                        $likeval = (('%'==$op)?'%':'') . "{$value}%";
                        $whone = "$field like '{$likeval}'";
                        break;
                    case '^': //not %v%
                    case '!': //not %v
                        $value = trim($value, "'");
                        $likeval = (('^'==$op)?'%':'') . "{$value}%";
                        $whone = "$field not like '{$likeval}'";
                        break;
                    case 'bt': 
                    case 'between': //value是一个数组
                        if(isset($value[0]) && isset($value[1])){
                            $_from = addslashes($value[0]);
                            $_to   = addslashes($value[1]);
                            $whone = "($field between '{$_from}' and '{$_to}')";
                        }
                        break;
                    case 'in':
                    case 'ni':
                        if(!($value))break;
                        if(!is_array($value)){
                            $vallist = $value;
                        }else{
                            foreach($value as &$____v)$____v = addslashes($____v);
                            $vallist = "'" . implode("','", $value) . "'";
                        }
                        $innotin = 'ni' == $op ? 'not in' : 'in';
                        $whone = "$field {$innotin}($vallist)";
                        break;
                    case 'null':    //is null
                    case 'notnull': //is not null
                        $nullnot = 'notnull' == $op ? 'not null' : 'null';
                        $whone = "$field is $nullnot";
                        break;
                    case 'match':
                        $whone = "match($field) against({$value})";
                        break;
                    case 'find_in_set':
                        if(is_array($value)){
                            $value = "'" . addslashes(current($value)) . "'";
                        }
                        $whone = "find_in_set($value,$field)";
                        break;
                    default: //=,>,>=,<,<=,!=,<>
                        if(is_null($value)){
                            $whone = "$field is null";
                        }else{
                            $ord   = ord($op);
                            if($ord>=97 && $ord<=122)$op=" {$op} ";
                            $whone = "$field{$op}$value";
                        }
                }
                if($whone){
                    $where .= ($loops>0?($space.$andordft.$space):null). $whone;
                }
            }
            $loops++;
        }
        return $where;
    }
    
    /*
    * desc: 正确化字段的值，如：
    *       加引号等,如果使用了mysql函数则不加引号
    */
    public function valCorrectize($val, $f=null, $exArr=array())
    {
        if(is_array($val)) {
            if(isset($val['express'])){
                return $val['express'];//函数表达试
            }elseif(isset($val['json'])){
                return $this->jsonCreate($val['json']);
            }else{
                $val = json_encode($val);
            }
        }
        return "'". addslashes($val). "'";
    }
    
    public function lock($table, $type='read')
    {
        return $this->execute('lock table '.$table.' '.$type);
    }
    public function unlock()
    {
        return $this->execute('unlock tables');
    }
    /*
    * desc: 获取上次执行sql语句
    */
    public function getSql($lasted=false)
    {
        if($lasted){
            return current(array_slice($this->sqls, -1));
        }
        return $this->sqls;
    }
    //清空sql
    public function cleanSql()
    {
        $this->sqls = array();
    }
    public function getSqlString()
    {
        $sqlArr = $this->getSql();
        if(!$sqlArr) return false;
        return array_reduce($sqlArr, function($result,$sqls){
            $string = is_array($sqls)?implode(";\n",$sqls):$sqls;
            return $result .= $string.";\n";
        });
    }
    public function getError()
    {
        return $this->error;
    }
    protected function setError()
    {
        if(null === $this->pdb) return false;
        $arr = $this->pdb->errorInfo();
        $this->error = $arr[2];
    }
    public function getWarning()
    {
        return $this->warning;
    }
};
