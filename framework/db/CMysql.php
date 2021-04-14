<?php
/**
 * author: cty@20120301
 *   desc: mysql数据库pdo管理类
 *   call: $db = new CMysql(db,host,array(...));
 *         $db->方法名(...);
 *
 *
 *
 *
*/

class CMysql extends CPdb {
    
    function __construct($dbName='mysql', $host='127.0.0.1', $params=array())
    {
        $this->dbName = $dbName;
        //'mysql:dbname=mysql;host=127.0.0.1';
        $params['dsn'] = "mysql:dbname={$dbName};";
        if(strpos($host,'.sock')){
            $params['dsn'] .= "unix_socket={$host}";
        }else{
            $params['dsn'] .= "host={$host}";
        }
        parent::__construct($params);
    }
    public function getEncoding()
    {
        return $this->encoding;
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
        if(!isset($exArr['only_data']) || (isset($exArr['only_data']) && !$exArr['only_data'])){
            $count = $this->count($table, $exArr['where'], $exArr);
            if(0 == $count) return array();
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
        $sql = "delete from $table where $cdtions limit $limit";
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

        if(isset($exArr['ondups']) && ($ondups=trim($exArr['ondups']))){
            unset($exArr['ondups']);//以防万一
            $aftArr = explode('|', trim($ondups,' |'));
            $doArr  = array();
            foreach($aftArr as $aft){
                $aft = trim($aft);
                if($pos = strpos($aft, ':')){
                    $do   = substr($aft, 0, $pos);
                    $work = substr($aft, $pos +1);
                    $doArr[strtolower($do)]  = $work;
                }else{
                    $doArr[strtolower($aft)] = '';
                }
            }
            $upArr = current($dataArr);//默认要更新的字段
            if(isset($doArr['update']) && $doArr['update']){
                $upArr = array_intersect_key($upArr, array_flip(explode(',',$doArr['update'])));//更新指定字段
            }
            if(isset($doArr['ignore']) && $doArr['ignore']){//要忽略的字段
                $upArr = array_diff_key($upArr, array_flip(explode(',',$doArr['ignore'])));
            }
            if($upArr){
                foreach($upArr as &$v){
                    $v = "'".addslashes(trim($v,"'"))."'";
                }
            }
            if(isset($doArr['express']) && $doArr['express']){//自定义表达式
                $epArr = explode(',',$doArr['express']);
                foreach($epArr as $k=>$ep){
                    if(!strpos($ep, '=')) continue;
                    list($f, $express) = explode('=', $ep);
                    $epArr[$f] = $express;
                    unset($epArr[$k]);
                }
                // $epArr = array_intersect_key($epArr, $upArr);
                if($epArr){
                    $upArr = array_merge($upArr, $epArr);
                }
            }
            if($upArr){
                foreach($upArr as $k=>$v001){
                    $upArr["`{$k}`"] = $v001;
                    unset($upArr[$k]);
                }
                $arg_old = ini_get('arg_separator.output');
                ini_set('arg_separator.output', ',');
                $exArr['ondups'] = urldecode(http_build_query($upArr));
                ini_set('arg_separator.output', $arg_old);
            }
        }
        
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
        $delayed  = isset($exArr['delayed'])?$exArr['delayed']:false;
        $optype   = $replaced?'replace':'insert';
        $delay    = $delayed?'delayed':'';
        $ignore   = $ignored?'ignore':'';
        $ondups   = isset($exArr['ondups'])?'on duplicate key update '.$exArr['ondups']:'';
        // INSERT INTO TABLE (a,b,c) VALUES (1,2,3) ON DUPLICATE KEY UPDATE c=c+1;
        $sql = trim("{$optype} {$delay} {$ignore} into {$table}($fields) values $values {$ondups}");
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
        $locking = isset($exArr['locking'])?'for update':'';

        $where  = empty($where)?'':" where {$where}";
        $order  = empty($order)?'':$order;
        $having = empty($having)?'':$having;
        if(false === strpos($table, '.')){
            $table  = '`'.trim($table,'`').'`';
        }
        return rtrim("select {$fields} from {$table} {$where} {$group} {$having} {$order} {$limitstring} {$locking}");
    }
    public function getDesc($table)
    {
        $table = trim($table);
        $_tarr = explode(' ', $table);
        $prex  = strtolower($_tarr[0]);
        if(!strpos($table, '.')){
            $table = '`'.trim($table,'`').'`';
        }
        if(count($_tarr) > 1 && ('select'==$prex)) {
            //说明传的不是table name而是sql语句
            $row = $this->getOne('explain '.$table);
            $table = $row['table'];
        }

        $sql = "show full columns from {$table}";
        $initArr = $this->query($sql);
        if(!$initArr){
            $sql = "desc {$table}";
            $initArr = $this->query($sql);
            if(!$initArr) return false;
        }
        $descArr = array();
        foreach($initArr as $row) {
            $trr = array();
            extract($row);
            //fetch length
            if(1 == preg_match("/\(([0-9]+?)\)/", $Type, $arr)) {
                $len = $arr[1];
            }else {
                $len = null;
            }
            $Type = preg_replace("/\([0-9]+?\)/", '', $Type);
            $trr['name'] = $Field;
            $trr['type'] = $Type;
            $trr['lens'] = $len;
            $trr['null'] = 'YES'==$Null?'NULL':'NOT NULL';
            $trr['prik'] = 'PRI'==$Key?'PK':'';
            $trr['unix'] = 'UNI'==$Key?'UNI':'';
            $trr['indx'] = 'MUL'==$Key?'MUL':'';
            $trr['deft'] = $Default;
            $trr['auto'] = $Extra;
            $trr['comm'] = isset($Comment)?$Comment:null;
            $descArr[$Field] = $trr;
        }
        return $descArr;
    }
    /*
    * desc: get CREATE TABLE information
    *
    */
    public function getCreates($table)
    {
        $table  = '`'.trim($table,'`').'`';
        $sql = "show create table {$table}";
        return $this->execute($sql);
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
        if(is_scalar($jArr)){
            $jArr = array($jArr);
        }
        if(empty($jArr))return "column_create('','')";
        $jsonstr = '';
        foreach($jArr as $k=>$vs){
            if(is_array($vs)){
                $jsonstr .= ",'{$k}',".$this->jsonCreate($vs)."";
            }else{
                $jsonstr .= ",'{$k}','".addslashes($vs)."'";
            }
        }
        return 'column_create('.ltrim($jsonstr,',').')';
    }
    public function getCount($sql)
    {
        if(null === $this->pdb) return false;
        $pos_space1 = strpos($sql, ' ');//第一个空格的位置
        $funArr  = array('sum','count','distinct','group','min','max','avg','union');    //聚合函数    
        foreach($funArr as $fn){
            if($fn = stripos($sql,$fn))break;
        }
        $sqlType = strtolower(trim(substr($sql, 0, $pos_space1),'(` )'));
        if($sqlType=="select" && !$fn) {
            //(为select型,且没有聚合函数)
            $reSql = strtolower(substr($sql, $pos_space1+1)); //re:sql剩余部分
            $start = $limit = 0;
            if($pos_limit = strpos($reSql, 'limit')){
                $sql = substr($sql, 0, $pos_limit + $pos_space1); //limit之后的去掉
                $limit_str = trim(substr($reSql, $pos_limit+6));
                if($pos_quote = strpos($limit_str, ',')){
                    $tArr = explode(',', $limit_str);
                    $start = intval($tArr[0]);
                    $limit = intval($tArr[1]);
                }else{
                    $limit = intval($limit_str);
                }
            }
            $pos_from = strpos($reSql, 'from'); //pos_from在reSql中的位置刚好是要替换的长度
            $sql = substr_replace($sql, ' count(*) as cnt ', $pos_space1, $pos_from+1);
            $rsmax = $this->getOne($sql);
            if(!$rsmax)return 0;
            $max = floatval($rsmax['cnt']); //查询出该语句最大行数      
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
