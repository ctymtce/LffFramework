<?php
/**
 * author: cty@20120301
 *   desc: mssql数据库pdo管理类
 *   call: $db = new CMssql(db,host,array(...));
 *         $db->方法名(...);
 *
 *
 *
 *
*/

class CMssql extends CPdb {
    
    function __construct($dbName='master', $host='127.0.0.1', $params=array())
    {
        $this->dbName = $dbName;
        //'odbc:Driver={SQL Server};Server=127.0.0.1,1433;Database=master'
        $params['dsn'] = "odbc:Driver={SQL Server};Server={$host};Database={$dbName};";
        parent::__construct($params);
    }
    /*
    * desc: query one
    *
    */
    public function getOne($table, $exArr=array())
    {
        $exArr['limit'] = 1;
        $rowArr = $this->getAll($table, $exArr);
        if(isset($rowArr[0])){
            return $rowArr[0];
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
        if(null === $this->pdb) return false;
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

        if(isset($exArr['group'])){//分组模式
            $clause = trim(preg_replace("/limit.+/si",'',$this->mkQuery($table, $exArr)));
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
    public function delete($table, $whArr)
    {
        $table   = trim($table);
        $cdtions = $this->parseWhere($whArr);
        $sql = "delete from $table where $cdtions";
        return $this->execute($sql, -1);
    }
    public function remove($table, $exArr)
    {
        return $this->delete($table, $exArr);
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
        $table = trim($table, '`');
        $fields = implode(',', array_keys(current($dataArr)));
        
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
        $sql = "{$optype} {$delay} {$ignore} into {$table}($fields) values $values";
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
        if(false !== $ok){
            return $ok;
        }
        return $ok;
    }
    /*
    * desc: updating
    *
    */
    public function update($table, $valArr, $cdtions, $exArr=array())
    {
        return parent::update($table, $valArr, $cdtions, array_merge(array('backquote'=>''),$exArr));
    }
    protected function getInsertId()
    {
        $last = $this->execute('select @@IDENTITY as lastid');
        $last = current($last);
        if($last && isset($last['lastid'])){
            return number_format($last['lastid'], 0,'','');
        }
        return 0;
    }
    /*
    * desc: constructing query sql
    *
    *@tableor --- str TABLE or string after FROM
    */
    public function mkQuery($tableor, $exArr)
    {
        $exArr   = empty($exArr)?array():$exArr;
        $page    = isset($exArr['page'])   ? $exArr['page']  : 1;
        $limit   = isset($exArr['limit'])  ? $exArr['limit'] : 100;
        $fields  = isset($exArr['fields']) ? $exArr['fields']: '*';
        $where   = isset($exArr['where'])  ? $exArr['where'] : '';
        $order   = isset($exArr['order'])  ? 'order by '. $exArr['order']:'';
        $group   = isset($exArr['group'])  ? 'group by '. $exArr['group']:'';
        $having  = isset($exArr['having']) ? 'having '.$exArr['having']  :'';
        
        $start   = ($page-1)*$limit; //rownumber的位置
        $where   = empty($where)?'':" where {$where}";
        $order   = empty($order)?'':$order;
        $having  = empty($having)?'':$having;
        $tableor = trim(trim($tableor),'`');
        if($limit > 1 && !empty($order)){
            $sql = "select top {$limit} * from (select {$fields},row_number() over({$order}) as rownumber from {$tableor} {$where} {$group} {$having}) R where rownumber>{$start}";
        }else{
            $sql = "select top {$limit} {$fields} from {$tableor} {$where} {$group} {$having} {$order}";
        }
        return $sql;
    }
    public function getDesc($table)
    {
        // $sql = "select sys.columns.name Field, sys.types.name Type, sys.columns.max_length, sys.columns.is_nullable, (select count(*) from sys.identity_columns where sys.identity_columns.object_id = sys.columns.object_id and sys.columns.column_id = sys.identity_columns.column_id) as is_identity, (select value from sys.extended_properties where sys.extended_properties.major_id = sys.columns.object_id and sys.extended_properties.minor_id = sys.columns.column_id) as description  from sys.columns, sys.tables, sys.types where sys.columns.object_id = sys.tables.object_id and sys.columns.system_type_id=sys.types.system_type_id and sys.tables.name='{$table}' order by sys.columns.column_id";
        $sql = "SELECT Field=A.NAME,IsIdentity = COLUMNPROPERTY( A.ID,A.NAME,'ISIDENTITY '),  
         IsPrimaryKey=CASE WHEN EXISTS(Select 1 FROM SYSOBJECTS Where XTYPE='PK ' AND PARENT_OBJ=A.ID AND NAME IN (  
         SELECT NAME FROM SYSINDEXES Where INDID IN(  
         SELECT INDID FROM SYSINDEXKEYS Where ID = A.ID AND COLID=A.COLID))) THEN 1 ELSE 0 END, 
         ColumnType=B.NAME,A.LENGTH,A.ISNULLABLE,DefaultValue=ISNULL(E.TEXT,' '),ColumnExplain=ISNULL(G.[VALUE],' '),  
         Scale= ISNULL(COLUMNPROPERTY(A.ID,A.NAME,'SCALE '),0)  
        FROM SYSCOLUMNS A LEFT JOIN SYSTYPES B ON A.XUSERTYPE=B.XUSERTYPE  
        INNER JOIN SYSOBJECTS D ON A.ID=D.ID AND D.XTYPE='U ' AND D.NAME<>'DTPROPERTIES '  
         LEFT JOIN SYSCOMMENTS E ON A.CDEFAULT=E.ID LEFT JOIN sys.extended_properties  G   
         ON A.ID=G.major_id  AND A.COLID=G.minor_id  LEFT JOIN sys.extended_properties  F   
        ON D.ID=F.major_id  AND F.minor_id=0 where D.NAME='{$table}' order by IsPrimaryKey desc ";
        $initArr = $this->query($sql);
        // print_r($initArr);
        $descArr = array();
        foreach($initArr as $row) {
            $trr = array();
            extract($row);
            //fetch length
            $trr['name'] = $Field;
            $trr['type'] = $ColumnType;
            $trr['lens'] = $LENGTH;
            $trr['null'] = 1==$ISNULLABLE?'NULL':'NOT NULL';
            $trr['prik'] = 1==$IsPrimaryKey?'PK':'';
            $trr['unix'] = '';
            $trr['indx'] = '';
            $trr['deft'] = $DefaultValue;
            $trr['auto'] = $IsIdentity;
            $trr['comm'] = $ColumnExplain;
            $descArr[$Field] = $trr;
        }
        // print_r($descArr);
        return $descArr;
    }
    protected function addLimit($sql, $start=0, $limit=20)
    {
    }
    /*
    * desc: 动态列(dynnmic-column)组装
    *       
    */
    public function jsonCreate($jArr)
    {
    }
    public function getCount($sql)
    {
    }

    //事务******************************************
    public function Begin()
    {
        return $this->execute('begin transaction');
    }
    public function Commit()
    {
        return $this->execute('commit transaction');
    }
    public function Rollback()
    {
        return $this->execute('rollback transaction');
    }
    //事务***************************************end
};
