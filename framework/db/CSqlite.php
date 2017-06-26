<?php
/**
 * author: cty@20160923
 *   desc: sqlite数据库pdo管理类
 *   call: $db = new CSqlite(db,host,array(...));
 *         $db->方法名(...);
 *
*/

class CSqlite extends CPdb {
    
    function __construct($dbName='sqlite.db', $host='127.0.0.1', $params=array())
    {
        $this->dbName = $dbName;
        //$dsn = 'sqlite:sql.db';
        $params['dsn'] = "sqlite:{$dbName}";
        parent::__construct($params);
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
            return array_shift($rowArr);
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
        $exArr['where'] = $this->parseWhere($whArr);
        $sql = $this->mkQuery($table, $exArr);
        if(empty($exArr['only_data'])){
            $count = $this->count($table, $exArr['where'], $exArr);
        }
        return $this->query($sql);
    }
    public function count($table, $whArr=array(), $exArr=array())
    {
        $exArr['where'] = $this->parseWhere($whArr);
        $exArr['limit'] = 1;
        unset($exArr['page'],$exArr['order']);

        if(isset($exArr['group'])){//分组模式:select g,count(xx) from table group by g
            // $clause = trim(preg_replace("/limit.+/si",'',$this->mkQuery($table, $exArr)));
            $clause = $this->mkQuery($table, array_merge($exArr,array('unlimit'=>true)));
            if($pos = strpos($clause, 'limit')){//去掉limit子句
                $clause = substr($clause, 0, $pos);
            }
            $sql = "select count(*) C01 from ($clause) A";
        }else{
            if(isset($exArr['fields'])){
                $exArr['fields'] = strtolower($exArr['fields']);
                if(false !== strpos($exArr['fields'],'distinct')){
                    $fldas = $exArr['fields'];
                }else{//count里不能多个字段
                    $fldas = '*';
                }
            }else{
                $fldas = '*';
            }
            $exArr['fields'] = 'count('.$fldas.') C01';
            $sql = $this->mkQuery($table, $exArr);
        }
        $row = $this->query($sql, false);
        if(isset($row['C01'])) return floatval($row['C01']);
        return 0;
    }
    /*
    * desc: delete row
    *
    *@table --- string table name
    *@whArr --- mix
    */
    public function delete($table, $whArr, $limit=1)
    {
        if(false === strpos($table, '.')){
            $table  = '`'.trim($table,'`').'`';
        }
        $cdtions = $this->parseWhere($whArr);
        $sql = "delete from $table where $cdtions";
        return $this->execute($sql, -1);
    }
    public function remove($table, $exArr, $limit=1)
    {
        return $this->delete($table, $exArr, $limit);
    }
    public function add($table, $dataArr)
    {
        return $this->insert($table, $dataArr);
    }
    /*
    * func: 插入一条数据
    * desc: $dataArr已经是一个整理过数据(字段不会多)
    *
    */
    public function insert($table, $dataArr, $exArr=array())
    {
        return $this->inserts($table, array($dataArr), $exArr);
    }
    public function replace($table, $dataArr)
    {
        return $this->insert($table, $dataArr, array('replaced'=>true));
    }

    /*
    * func: 插入一条数据
    * desc: $dataArr已经是一个整理过数据(字段不会多)
    *@dataArr = [row1,row2,...]
    */
    public function inserts($table, $dataArr, $exArr=array())
    {
        if(false === strpos($table, '.')){
            $table  = '`'.trim($table,'`').'`';
        }
        $fields = '`'. implode('`,`', array_keys(current($dataArr))) .'`';
        $valueArr = array();
        foreach($dataArr as $row){
            $valstr = '';
            foreach($row as $f=>$val) {
                $valstr .= $this->valCorrectize($val, $f, $exArr).',';
            }
            $valueArr[] = '('.trim($valstr,',').')';
        }
        $values   = implode(',', $valueArr);

        $replaced = isset($exArr['replaced'])?$exArr['replaced']:false;
        $ignored  = isset($exArr['ignored'])?$exArr['ignored']:false;
        $ignored  = $replaced?false:$ignored;
        $optype   = $replaced?'replace':'insert';
        $ignore   = $ignored?'or ignore':'';
        $sql = trim("{$optype} {$ignore} into {$table}($fields) values {$values}");
        if($this->preview){
            return array(
                'type'   => $optype,
                'ignore' => $ignore,
                'table'  => $table,
                'fields' => $fields,
                'values' => $values,
            );
        }
        $ok = $this->execute($sql, 1);
        if($ok){
            return number_format(floatval($this->getInsertId()), 0,'','');;
        }
        return $ok;
    }
    /*
    * desc: updating
    *
    */
    public function update($table, $valArr, $cdtions, $exArr=array())
    {
        return parent::update($table, $valArr, $cdtions, $exArr);
    }
    protected function getInsertId()
    {
        return number_format(floatval($this->pdb->lastInsertId()), 0,'','');
    }
    public function mkQuery($table, $exArr)
    {
        $exArr  = empty($exArr)?array():$exArr;
        $page   = isset($exArr['page'])   ? $exArr['page']  : 1;
        $fields = isset($exArr['fields']) ? $exArr['fields']: '*';
        $where  = isset($exArr['where'])  ? $exArr['where'] : '';
        $order  = isset($exArr['order'])  ? 'order by '. $exArr['order']:'';
        $group  = isset($exArr['group'])  ? 'group by '. $exArr['group']:'';
        $having = isset($exArr['having']) ? 'having '.$exArr['having']  :'';
        
        if(isset($exArr['unlimit']) && $exArr['unlimit']){
            $limitstring = '';//不需要limit
        }else{
            $limit  = isset($exArr['limit'])  ? $exArr['limit'] : 100;
            $start  = ($page-1)*$limit;
            $limitstring = "limit {$start},{$limit}";
        }

        $where  = empty($where)?'':" where {$where}";
        $order  = empty($order)?'':$order;
        $having = empty($having)?'':$having;
        if(false === strpos($table, '.')){
            $table  = '`'.trim($table,'`').'`';
        }
        $sql    = "select {$fields} from {$table} {$where} {$group} {$having} {$order} $limitstring";
        return $sql;
    }
    public function getDesc($table)
    {
        if(!class_exists('CSQLParser')){
            return false; //暂时不启用
        }
        $table = trim($table);
        $sql = "select * from sqlite_master WHERE type='table' and name='{$table}'";
        $initArr = $this->query($sql);
        if(!$initArr){
            return false;
        }
        $create_sql = $initArr[0]['sql'];
        $parsedArr  = (new CSQLParser())->parse($create_sql);
        $table      = trim($table,'`');
        $initArr    = $parsedArr[$table]['fields'];
        $descArr = array();
        foreach($initArr as $row) {
            $field = $row['name'];
            if(isset($row['more'])){
                foreach($row['more'] as &$v0001)$v0001=strtolower($v0001);
                $row = array_merge($row, array_flip($row['more']));
            }
            $descArr[$field] = array(
                'name' => $field,
                'type' => $row['type'],
                'lens' => isset($row['length'])?$row['length']:0,
                'null' => isset($row['null'])?$row['null']:0,
                'prik' => isset($row['primary key'])?'PK':0,
                'unix' => isset($row['unique'])?'UNI':0,
                'indx' => '',
                'deft' => isset($row['default'])?$row['default']:'',
                'auto' => isset($row['autoincrement'])?1:0,
                'comm' => isset($row['comment'])?$row['comment']:null,
            );
        }
        return $descArr;
    }
    protected function addLimit($sql, $start=0, $limit=20)
    {
        $sql = strtolower($sql);
        if(false === strpos($sql, 'limit')) {
            $sql  = preg_replace("/limit.*/i", '', $sql);
            $sql .= " Limit {$start},{$limit}";
        }
        return $sql;
    }
    /*
    * desc: 动态列(dynnmic-column)组装
    *       
    */
    public function jsonCreate($jArr)
    {
        return json_encode($jArr);
    }
    public function getCount($sql)
    {
        if(null === $this->pdb) return false;
        $funArr  = array("sum","count","min","max","avg","distinct","group", "union");    //聚合函数    
        foreach($funArr as $fun){
            $fun = strpos($sql,$fun);
            if($fun)break;
        }
        $sqlType = preg_match("/[a-z]*\S/si",$sql,$sqlTypeArr);
        $sqlType = strtolower($sqlTypeArr[0]);
        if($sqlType=="select" && !$fun) {
            //(为select型,且没有聚合函数)
            preg_match("/limit\s+?([0-9]*),{0,1}\s*?([0-9]*)/si", $sql, $arr);
            array_shift($arr);
            $start = $limit = 0;
            if(count($arr) > 0) {
                if(empty($arr[1])) {
                    $limit = $arr[0];
                }else {
                    $start = $arr[0];
                    $limit = $arr[1];
                }
            }
            $sqlmax = preg_replace("/limit.*?,.*?[0-9]+/si","",$sql);  //max表示该sql语句查询出的最大行数
            $sqlmax = preg_replace("/trim\(.*?\)/si", "", $sqlmax, 1);
            $sqlmax = preg_replace("/select.*?\sfrom\s/si","Select Count(*) as count From ",$sqlmax,1);
            $rsmax = $this->getOne($sqlmax);
            if(!$rsmax)return 0;
            $max = $rsmax['count'];    //查询出该语句最大行数      
            // preg_match_all("/limit.*?([0-9]+).*?,.*?([0-9]+)/si",$sql,$cntArr);//找出limit后的两个数字
            $max = $max-$start;
            $nRows = ($max>$limit)?$limit:$max;//如果limit后的个数比最大行要小则采用limit后的那个数
        }else { 
            $query = $this->pdb->query($sql);
            $nRows = $query->rowCount();
        }
        return $nRows;
    }

    //事务******************************************
    public function Begin()
    {
        return $this->execute('start transaction');
    }
    public function Commit()
    {
        return $this->execute('commit');
    }
    public function Rollback()
    {
        return $this->execute('rollback');
    }
    //事务***************************************end
};
