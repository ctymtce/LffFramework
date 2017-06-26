<?php
/**
 * author: cty@20120301
 *   desc: mongodb管理类
 *   call: $db = new CMongo(db,host,array(...));
 *         $db->方法名(...);
 *
 *
 *
 *
*/

use MongoDB\Driver\Manager      as MongoDB;
use MongoDB\Driver\BulkWrite    as BulkWrite;
use MongoDB\Driver\WriteConcern as WriteConcern;
use MongoDB\Driver\Command      as Command;
use MongoDB\Driver\Query        as Query;
use MongoDB\BSON\ObjectID       as ObjectID;

class CMongo {
    
    private $Mongo   = null;
    private $error   = null;
    private $errno   = null;
    private $warning = null;
    private $sqls    = array();
    private $preview = false;

    private $dbHost = null;

    function __construct($dbName=null, $dbHost='127.0.0.1:27017', $params=array())
    {
        $this->dbHost = $dbHost;
        $this->dbName = $dbName;
        $this->driver = 'mongo';
        $this->connect();
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
    public function connect()
    {
        $this->Mongo = new MongoDB('mongodb://'.$this->dbHost.'/'.$this->dbName);
    }
    public function ping()
    {
        ob_start();
        try{
            if(1 == $this->Mongo->executeCommand('admin', (new Command(array('ping'=>1))))->toArray()[0]->ok) return true;
        }catch(Exception $e){
            $this->error = $e->getMessage();
        }
        ob_end_clean();
        return false;
    }
    public function alive()
    {
        while(($loops=isset($loops)?++$loops:0) < 5){
            if($this->ping()) return true;
            usleep(1000)*mt_rand(1,10);
            $this->connect();
        }
        return false;
    }
    public function execute()
    {
        $command = new Command(array(
                'use' => 'test',
            )
        );
    }
    /*
    * desc: query one
    *
    */
    public function getOne($table, $whArr=array(), $exArr=array())
    {
        $exArr['limit'] = 1;
        $rowArr = $this->getAll($table, $whArr, $exArr);
        if(is_array($rowArr)){
            return array_pop($rowArr);
        }
        return null;
    }
    /**
    * func: select sql
    * desc: used for select statement primary
    *
    */
    public function getAll($table, $whArr=array(), $exArr=array(), &$count=0)
    {
        if(!$this->alive()) return false;
        if(!strpos($table, '.')){
            $table = $this->dbName .'.'. $table;
        }
        //判断是否为聚合查询==============
        if(isset($exArr['group']) || isset($exArr['aggregate'])){
            return $this->aggregating($table, $whArr, $exArr);
        }
        //判断是否为聚合查询===========end
        $whArr = $this->parseWhere($whArr);
        $exArr = $this->parseExtra($exArr);
        // print_r($whArr);
        $query = new Query($whArr, $exArr);
        try{
            $cursor = $this->Mongo->executeQuery($table, $query);

            $docArr = array();
            foreach($cursor as $doc) {
                if(isset($doc->_id)){
                    $doc->_id = $doc->_id->__toString();
                }
                $docArr[] = $doc;
            }
            if((isset($exArr['only_data']) && !$exArr['only_data']) || (isset($exArr['limit']) && $exArr['limit']>1) || !isset($exArr['limit'])){
                $count = $this->count($table, $whArr);
            }
            return json_decode(json_encode($docArr),true);
        }catch(Exception $e){
            $this->error = $e->getMessage();
            return false;
        }
    }
    public function count($table, $whArr=array(), $exArr=array())
    {
        $db = null;
        if(strpos($table, '.')){
            list($db, $table) = explode('.', $table);
        }
        $commands = array(
            'count' => $table,
            'query' => $whArr,
        );
        $command = new Command($commands);  
        try{
            return $this->Mongo->executeCommand($db, $command)->toArray()[0]->n; 
        }catch(MongoDB\Driver\Exception\RuntimeException $e){
            $this->error = $e->getMessage();
            return 0;
        }
    }
    /*
    不去重
    db.g1.aggregate([
        {
            $group: {
                _id: {
                    "d": "$d",
                }, 
                _cnt: {$sum: 1}
            }
        },
        {$sort: {"_id": 1}}
    ]);

    去重
    db.g1.aggregate([
        {$group: {
            _id: {
                "d": "$d",
                "m": "$m"
            }
        }},
        {$group: {"_id":"$_id.d", _cnt: {$sum: 1}}},
        {$sort: {"_id":1}}
    ]);
    不去掉
    db.g1.group({
        key:{'d':1},
        initial:{'t':0},
        reduce:function(obj,prev){ if(prev.m!=obj.m)prev.t++;   }}
    );
    $commands = array(
        'aggregate' => $table,
        'pipeline'  => array(
            array(
                '$match' => $whArr,
            ),
            array(
                '$group' => array(
                    '_id'  => array('d'=>'$d', 'm'=> '$m'),
                    '_cnt' => array(
                        '$sum'=>1,
                    ),
                )
            ),
            array(
                '$group' => array(
                    '_id'  => array('d'=>'$_id.d'),
                    '_cnt' => array(
                        '$sum'=>1,
                    ),
                )
            )
        )
    );
    */
    public function aggregating($table, $whArr=array(), $egArr=array())
    {
        $whArr = $this->parseWhere($whArr);
        $egArr = $this->parseExtra($egArr);

        $db = $this->dbName;
        if(strpos($table, '.')){
            list($db, $table) = explode('.', $table);
        }
        
        $commands = array(
            'aggregate' => $table,
            'pipeline'  => array()
        );

        if(isset($egArr['aggregate'])){
            $stageArr = array();
            foreach($egArr['aggregate'] as &$agitem){
                $phpstage = current(array_keys($agitem));
                if(0 === strpos($phpstage, '$')) {
                    $stageArr[] = $agitem;
                    continue;
                }
                $mgostage = '$'.$phpstage;//键名
                if('match' == $phpstage){
                    $match = $this->parseWhere($agitem[$phpstage]);
                    if($match){
                        $stageArr[] = array($mgostage => $match);
                    }
                }elseif('group' == $phpstage){
                    //多分组实现group中的distinct
                    if(is_string($agitem['group'])){//格式：group=day,city;day
                        for($i=0,$gpArr=explode(';', $agitem['group']),$len=count($gpArr); $i<$len; $i++){
                            $one_group = $gpArr[$i];
                            $group = array(
                                '_id' => array(),
                                'cnt' => array('$sum' => 1),
                            );
                            foreach(explode(',', $one_group) as $g_field){
                                if(0 == $i){
                                    $group['_id'][$g_field] = '$'.$g_field;
                                }else{
                                    $group['_id'][$g_field] = '$_id.'.$g_field;
                                }
                            }
                            // array_push($egArr['aggregate'], array('$group' => $group));
                            $stageArr[] = array('$group' => $group);
                        }
                    }
                }else{
                    $stageArr[] = array($mgostage => $agitem[$phpstage]);
                }
            }
            unset($egArr['aggregate']);
            $commands['pipeline'] = $stageArr;
        }else{//group之类的
            if($whArr){
                array_push($commands['pipeline'], array('$match' => $whArr));
            }
            if(isset($egArr['group'])){
                if(isset($egArr['agfun'])){//聚合函数
                    //格式 -> 别名=聚合函数:字段(cnt=sum:1)
                    $pos = strpos($egArr['agfun'], '=');
                    if($pos){
                        $alias = substr($egArr['agfun'], 0, $pos);
                        list($aggre_fun, $aggre_field) = explode(':', substr($egArr['agfun'],$pos+1));
                        if(0 == intval($aggre_field)) {
                            $aggre_field = '$'.$aggre_field;
                        }else{
                            $aggre_field = intval($aggre_field);
                        }
                    }
                }
                if(!isset($alias)){
                    $alias = 'cnt';
                    $aggre_fun = 'sum';
                    $aggre_field = 1;
                }
                //多分组实现group中的distinct
                for($i=0,$gpArr=explode(';', $egArr['group']),$len=count($gpArr); $i<$len; $i++){
                    $one_group = $gpArr[$i];
                    $group = array(
                        '_id' => array(),
                        $alias => array('$'.$aggre_fun => $aggre_field),
                    );
                    foreach(explode(',', $one_group) as $g_field){
                        if(0 == $i){
                            $group['_id'][$g_field] = '$'.$g_field;
                        }else{
                            $group['_id'][$g_field] = '$_id.'.$g_field;
                        }
                    }
                    array_push($commands['pipeline'], array('$group' => $group));
                }
            }
            if(isset($egArr['sort'])){
                array_push($commands['pipeline'], array('$sort' => $egArr['sort']));
            }
        }
        // print_r($commands);
        try{
            $command = new Command($commands);  
            $cursor  = $this->Mongo->executeCommand($db, $command); 
            $docArr  = $cursor->toArray()[0]->result;
            $docArr  = json_decode(json_encode($docArr),true);
            foreach($docArr as &$doc) {
                if(isset($doc['_id'])){
                    $doc = array_merge($doc, $doc['_id']);
                    unset($doc['_id']);
                }
            }
            // print_r($docArr);
            return $docArr;
        }catch(Exception $e){
            $this->error = $e->getMessage();
            return false;
        }
    }
    /*
    * desc: delete a documnet
    *   db.test_2.remove({id:1});
    * call: $Mogo->delete('test.t', array('id'=>36), 2);
    *
    *@table --- string table name
    *@whArr --- mix
    */
    public function delete($table, $whArr, $limit=1)
    {
        $whArr = $this->parseWhere($whArr);
        $bulk = new BulkWrite();
        if($limit > 0){
            for($i=0; $i<$limit; $i++){
                $bulk->delete($whArr, array('limit'=>1));
            }
        }else{
            $bulk->delete($whArr, array('limit'=>0));//全删
        }
        try{
            $obj = $this->Mongo->executeBulkWrite($table, $bulk, new WriteConcern(WriteConcern::MAJORITY, 1000));
            return $obj->getDeletedCount();
        }catch(Exception $e){
            $this->error = $e->getMessage();
            return false;
        }
    }
    public function remove($table, $exArr, $limit=1)
    {
        return $this->delete($table, $exArr, $limit);
    }
    public function add($table, $docArr)
    {
        return $this->insert($table, $docArr);
    }
    /*
    * desc: 插入一条文档
    *
    *@doc --- arr 文档
    *
    */
    public function insert($table, $doc, $exArr=array())
    {
        return $this->inserts($table, array($doc), $exArr);
    }
    /*
    * func: 插入一条数据
    * desc: $dataArr已经是一个整理过数据(字段不会多)
    *@docArr = [row1,row2,...]
    */
    public function inserts($table, $docArr, $exArr=array())
    {
        if(!strpos($table, '.')){
            $table = $this->dbName .'.'. $table;
        }
        $bulk = new BulkWrite();
        foreach($docArr as $doc){
            $_id = $bulk->insert($doc)->__toString();
        }
        try{
            $this->Mongo->executeBulkWrite($table, $bulk);
            return $_id;
        }catch(Exception $e){
            $this->error = $e->getMessage();
            return false;
        }
    }
    /*
    * desc: updating
    *    $bulk->update(
    *        ['x' => 2],
    *        ['$set' => ['y' => 3]],
    *        ['multi' => false, 'upsert' => false]
    *    );
    * call: $Mogo->update('test.t', array('att'=>'aa'), array('id'=>1),array('multi'=>false));
    *
    */
    public function update($table, $valArr, $whArr, $exArr=array())
    {
        $whArr = $this->parseWhere($whArr);
        $bulk = new BulkWrite();
        $bulk->update($whArr, array('$set'=>$valArr), $exArr);
        try{
            $obj = $this->Mongo->executeBulkWrite($table, $bulk, new WriteConcern(WriteConcern::MAJORITY, 1000));
            // print_r(get_class_methods($obj));
            return $obj->getModifiedCount();
        }catch(Exception $e){
            $this->error = $e->getMessage();
            return false;
        }
    }
    protected function getInsertId(){}
    public function mkQuery(){}
    public function getDesc(){}
    protected function addLimit(){}
    /*
    * desc: 动态列(dynnmic-column)组装
    *       
    */
    public function jsonCreate($jArr)
    {
        return $jArr;
    }
    public function getCount($table, $whArr=array())
    {
        return $this->count($table, $whArr);
    }
    /*
    * desc: 解析where条件
    *
    */
    public function parseWhere($whArr)
    {
        $ftArr = array();
        if(!$whArr || !is_array($whArr))return $ftArr;
        foreach($whArr as $andorfield => $whorvalue){
            $andorfield = strtolower(trim($andorfield));
            /*if('_id' == $andorfield){
                $ftArr['_id'] = new ObjectID($whArr['_id']);
            }*/
            if(in_array($andorfield,array('and','or'))){
                //db.XXX.find({"$or":[{"name":"n1"}, {"name":"n2"}]})
                if(is_array($whorvalue)){
                    $ftArr['$'.$andorfield] = array();
                    foreach($whorvalue as $k=>$vs){
                        array_push($ftArr['$'.$andorfield], $this->parseWhere(
                                is_numeric($k)?$vs:array($k=>$vs)
                            )
                        );
                    }
                }else{
                    $ftArr['$'.$andorfield] = array($whorvalue);
                }
            }else{
                //这里的andorfield为字段名
                $value = $whorvalue;
                if(is_string($value))$value = addslashes($value);
                if(strpos($andorfield, ' ')){
                    list($field, $op) = explode(' ', $andorfield);
                }else{
                    $op = trim($andorfield,'abcdefghijklmnoporstuvwxyz_1234567890');
                    if($op){
                        $field = strstr($andorfield, $op, true);//字段
                        $op    = trim($op);
                    }else{
                        $field = $andorfield;
                    }
                }
                if('_id' == $field){
                    if(is_string($value)){
                        $value = new ObjectID($value);
                    }elseif(is_array($value)){
                        foreach($value as &$v0002){
                            if(is_string($v0002))$v0002 = new ObjectID($v0002);
                        }
                    }
                }
                switch($op){
                    case '%': //%v%
                    case '*': // v%
                        break;
                    case '^': //not %v%
                    case '!': //not %v
                        break;
                    case 'bt': //value是一个数组
                        break;
                    case 'ft': //全文搜索
                        $ftArr[$field] = array('$search' => $value);
                        break;
                    case 'mo': //取模
                        $ftArr[$field] = array('$mod' => $value);
                        break;
                    case 'sz': //size
                        $ftArr[$field] = array('$size' => $value);
                        break;
                    case 'sl': //slice
                        $ftArr[$field] = array('$slice' => $value);
                        break;
                    case 'es': //exists
                        $ftArr[$field] = array('$exists' => $value);
                        break;
                    case 'in':
                    case 'ni':
                        if(isset($value['table'])){
                            $caluse_cmds = array(
                                'distinct' => $value['table'],
                                'key' => $value['field'],
                                // 'query' => isset($value['where'])?$this->parseWhere($value['where']):array(),    
                            );
                            $ftArr[$field] = array(
                                // '$'.('in'==$op?'in':'nin') => array(new Command($caluse_cmds)),
                            );
                        }else{
                            $ftArr[$field] = array('$'.('in'==$op?'in':'nin') => $value);
                        }
                        break;
                    case 'al': 
                    case 'll': //list,value->[1,2...]
                        $ftArr[$field] = array('$all' => $value);
                        break;
                    case 'wh': //where,value是一函数
                        $ftArr['$where'] = $value;
                        break;
                    case 'el': //elemMatch子查询
                        $ftArr[$field] = array(
                            '$elemMatch' => $this->parseWhere($value),
                        );
                        break;
                    default: //=,>,>=,<,<=,!=,<>
                        if($op){
                            $opArr = array('='=>'$eq','>'=>'$gt','>='=>'$gte','<'=>'$lt','<='=>'$lte','!='=>'$ne','<>'=>'$ne');
                            $ftArr[$field] = array($opArr[$op] => $value);
                        }else{
                            $ftArr[$field] = $value;
                        }
                        break;
                }
            }
        }
        return $ftArr;
    }
    /*
    * desc: 解析选项
    *
    */
    public function parseExtra($exArr)
    {
        if(!$exArr) return array();
        if(isset($exArr['order'])){
            if(is_array($exArr['order'])){
                $exArr['sort'] = $exArr['order'];
            }else{
                foreach(explode(',', $exArr['order']) as $one_order){
                    $one_order = trim($one_order);
                    if(strpos($one_order, ' ')){
                        list($field, $adsc) = explode(' ', $one_order);
                        $exArr['sort'][$field] = 'asc'==strtolower($adsc)?1:-1;
                    }else{
                        $exArr['sort'][$one_order] = 1;
                    }
                }
            }
            unset($exArr['order']);
        }
        if(isset($exArr['fields'])){
            /*
            [projection] => Array
            (
                [member] => 1/0
                [attr] => 1/0
            )*/
            $exArr['fields'] = str_replace(array(' ',"\t","\n","\r"), '', $exArr['fields']);
            if('*' != $exArr['fields']){
                if(0 === strpos($exArr['fields'], '^')){
                    $exArr['fields'] = trim($exArr['fields'], '^');
                    $exArr['projection'] = array_flip(explode(',', $exArr['fields']));
                    foreach($exArr['projection'] as &$v0001)$v0001=0;
                }else{
                    $exArr['projection'] = array_flip(explode(',', $exArr['fields']));
                    foreach($exArr['projection'] as &$v0001)$v0001=1;
                }
            }
            unset($exArr['fields']);
        }
        if(isset($exArr['page']) && isset($exArr['limit'])){
            $exArr['page'] = intval($exArr['page']);
            $exArr['skip'] = ($exArr['page']-1)*$exArr['limit'];
            unset($exArr['page']);
        }
        // print_r($exArr);
        return $exArr;
    }

    //事务******************************************
    public function Begin(){}
    public function Commit(){}
    public function Rollback(){}
    //事务***************************************end

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
};
