<?php
/**
 * desc: 数据库操作入口
 *
 *
 *
 *
 *
*/
class CDao extends CEle{

    private $keyArr = array(
        'join'     => 0,
        'flat'     => 0,
        'order'    => 0,
        'group'    => 0,
        'limit'    => 0,
        'keyas'    => 0,
        'alias'    => 0,
        'fields'   => 0,
        'having'   => 0,
        'prefix'   => 0,
        'defaults' => 0,
    );
    private $intArr  = array(
        'int',
        'tinyint',
        'smallint',
        'mediumint',
        'bigint',
        'boolean',
        'serial',
        'bit',
    );
    private $floatArr = array(
        'float',
        'double',
        'decimal',
        'real'
    );
    private $pdbArr = array();
    private $errArr = array();

    private $autoDriver = true; //自动切换到主驱动
    private $autoMaster = true; //自动切换到主节点

    function __construct($dsArr)
    {
        if(empty($dsArr)){
            $this->Exception('Error Database Configures');
        }
        $this->dsArr = &$dsArr;
    }
    /*
    * desc: 连接db
    *
    *@type --- str [master|slaver|null] 节点,x表示并不指定具体的节点
    *@name --- str type下的一指定主机
    *
    */
    private function getDb($type=null, $name=null, $driver=null)
    {
        $type  = is_null($type)?'master':$type;
        $dbArr = $this->getServer($type, $name, $driver); //type(eg.虽然传的是slaver但实际并没有配置slaver,这时type实际为master)
        if(!isset($dbArr['host'])) return false;
        $cid    = md5($dbArr['host'].':'.$dbArr['user'].':'.$driver); //cid=md5(host.user); //同一driver/master/slaver同一实例，只能有一个连接
        $dbName = isset($dbArr['dbName'])?$dbArr['dbName']:null;
        if(!isset($this->pdbArr[$cid])) { //如果两次连接信息一样则不用再次连接数据库(这对事务很重要)
            // $driver = isset($dbArr['driver'])?$dbArr['driver']:'mysql';
            $dbArr['driver'] = $driver;
            $DriverClass = 'C'.ucfirst($driver);
            $this->pdbArr[$cid] = new $DriverClass($dbName, $dbArr['host'], $dbArr);
            return $this->pdbArr[$cid];
        }
        return $this->pdbArr[$cid];
    }
    /*
    * desc: 服务器选择
    *
    *   配置格式:
    *   dsArr => array(
    *       mongo => array(
    *           master => array(
    *               array(
    *                   host  => 127.0.0.1:27017,
    *                   user  => '',
    *                   pswd  => '',
    *                   dbName=> test,
    *                   alias => master,
    *               ),
    *           ),
    *       ),
    *       mysql => array(
    *           master => array(
    *               array(
    *                   host  => 127.0.0.1:3306,
    *                   user  => root,
    *                   pswd  => 123456,
    *                   dbName=> test,
    *                   alias => master,
    *               ),
    *           ),
    *       ),
    *   ),
    *
    */
    private function getServer($type='master', $name=null, &$driver=null)
    {
        if(!in_array($type, array('master', 'slaver'))) return false;
        $dsArr = $this->dsArr;
        //获取驱动
        if(!$driver || !isset($dsArr[$driver])){
            $driver = current(array_keys($dsArr));
        }
        $dsArr = $dsArr[$driver];

        //如果slaver不存在则取master
        if(isset($dsArr[$type]) && !empty($dsArr[$type])) {
            $dsArr = $dsArr[$type];
        }else{
            $dsArr = $dsArr['master'];
        }
        if($name && isset($dsArr[$name])){
            return $dsArr[$name];
        }else{
            shuffle($dsArr);//随机选择master或slaver服务器
            return current($dsArr);
        }
    }
    /*
    * desc: 获取连接
    *
    *@noAuto --- bool 显示地指定是否要改变clusterType和clusterName的值
    *
    */
    private function getConnection($noAuto=false)
    {
        if($this->isTransaction()){
            //保证一组事务操作都在同一连接上
            return $this->dbTransaction();
        }
        $clusterType = isset($this->clusterType)?$this->clusterType:null;
        $clusterName = isset($this->clusterName)?$this->clusterName:null;
        if($this->autoMaster && !$noAuto){
            $this->clusterType = null;
            $this->clusterName = null;
        }
        //driver是不同的数据库(在此可以共存)
        $driverName  = isset($this->driverName)?$this->driverName:null;
        if($this->autoDriver && !$noAuto){
            $this->driverName = null;
        }
        return $this->getDb($clusterType, $clusterName, $driverName);
    }
    //事务******************************************
    /*
    * desc: 指定的master服务器
    *
    */
    public function Begin($name=null)
    {
        if(isset($this->isTransaction)){
            $this->isTransaction++;         //事务记数加1
        }else{
            $this->isTransaction = 1;       //标记有事务
        }
        if(1 == $this->isTransaction){      //防止事务嵌套
            $this->Master();                //事务必须是在主节点上进行
            $this->clusterName   = $name;
            $this->autoDriver    = false;   //不要自动切换
            $this->autoMaster    = false;   //不要自动切换
            $this->dbTransaction = $this->getDb($this->getCluster(), $name);
            //注:isTransaction和dbTransaction应成双成对地出现,这样在判断是只需要判断isTransaction即可
            return $this->dbTransaction->Begin();
        }
    }
    public function Commit()
    {
        $this->isTransaction--;             //事务记数减1
        if($this->isTransaction <= 0){      //标记事务已结束
            $this->Cluster();               //恢复自动选择节点
            $this->autoDriver    = true;    //恢复自动切换
            $this->autoMaster    = true;    //恢复自动切换
            $this->isTransaction = 0;       //标记结束事务
            return $this->dbTransaction->Commit();
        }
    }
    public function Rollback()
    {
        $this->isTransaction--;             //事务记数减1
        if($this->isTransaction <= 0){      //标记事务已结束
            $this->Cluster();               //恢复自动选择节点
            $this->autoDriver    = true;    //不要自动切换
            $this->autoMaster    = true;    //恢复自动切换
            $this->isTransaction = 0;       //标记结束事务
            return $this->dbTransaction->Rollback();
        }
    }
    public function isTransaction()
    {
        return isset($this->isTransaction)&&$this->isTransaction?true:false;        
    }
    public function dbTransaction()
    {
        if(isset($this->dbTransaction) && $this->isTransaction){
            return $this->dbTransaction;
        }
        return false;
    }
    //事务***************************************end

    //驱动选择======================================
    /*
    * desc: 驱动选择
    *       这是为了让不同的驱动共存(如:同时使用了mysql和mongo)
    *
    */
    public function Driver($name=null)
    {
        $this->driverName = $name;
        return $this;
    }
    public function Mysql()
    {
        return $this->Driver('mysql');
    }
    public function Mssql()
    {
        return $this->Driver('mssql');
    }
    public function Mongo()
    {
        return $this->Driver('mongo');
    }
    //驱动选择===================================end

    //节点分布**************************************
    public function getCluster()
    {
        return isset($this->clusterType)?$this->clusterType:null;
    }
    public function Cluster($type=null, $name=null)
    {
        $this->clusterType = $type;
        $this->clusterName = $name;
        return $this;
    }
    public function Master($name=null)
    {
        return $this->Cluster('master', $name);
    }
    public function Slaver($name=null)
    {
        return $this->Cluster('slaver', $name);
    }
    public function Encoding($encoding='latin1',&$oldencoding=null)
    {
        $oldencoding = $this->getConnection(true)->getEncoding();
        $this->Execute("set names {$encoding}");
        return $this;
    }
    //节点分布***********************************end

    /*
    * desc: 获取数据列表
    *
    *@table --- str [dbname.]table
    *@whArr --- arr array(
    *                   'filed [>|>=|<|<=|!=|in|nin|...]' => value 
    *                    字段  操作符                     => 值
    *               ) ---> 详见CPdb.parseWhere方法
    *@exArr --- arr array(
    *                   page      --- int 分页
    *                   limit     --- 一页的个数
    *                   group     --- 分组
    *                   order     --- 排序
    *                   having    --- having过滤
    *                   fields    --- 字段
    *                   alias     --- userid=id,memberid=buyerid
    *                   only_data --- bool(true:只查数据不要计算count,false:查询count),
    *                   join_.... --- array(
    *                       table1 => fk1:pk1|wh1;fk2:pk2|wh2;...,
    *                       table2 => fk:pk,(主表的外建和副表的主键序列)
    *                       table3 => fk:pk|flat=f1,f2...(扁平化的组装)
    *                       table4 => fk:pk|defaults=comments=0&services=0
    *                   )(join查询)
    *               )
    *
    *return: dataArr=array('data'  => array(),
    *                      'total' => total
    *                      ...)
    */
    public function getMore($table, $whArr=array(), $exArr=array(), &$total=0)
    {
        if(!is_array($exArr)) $exArr = array();
        if($exArr){
            $this->ftExtras($table, $exArr);
        }
        
        //数据库查询相关变量=========================
        $exArr['page']  = isset($exArr['page'])?intval($exArr['page']):1;
        $exArr['page']  = max($exArr['page'], 1);
        $exArr['limit'] = isset($exArr['limit'])?intval($exArr['limit']):20;
        // $exArr['limit'] = $exArr['limit']?$exArr['limit']:20; //对于limit不能加此判断,因为0是有意义的
        if(isset($exArr['fields'])){
            $exArr['fields'] = $this->ftFields($table, $exArr['fields']);
        }
        //数据库查询相关变量======================end

        $db = $this->getConnection();
        if(is_scalar($whArr)){
            $primarykey = $this->getPrimaryKey($table);
            if(!$primarykey) $primarykey = 'id';
            $whArr = array($primarykey => $whArr);//默认当成id使用
        }
        $rowArr = $db->getAll($table, $whArr, $exArr, $total);
        if(!empty($rowArr)){
            $aggregated = isset($exArr['aggregated'])?$exArr['aggregated']:false;
            if(isset($exArr['alias'])){
                parse_str($exArr['alias'], $aliasArr);
                if(is_array($aliasArr)){
                    foreach($aliasArr as $alias_filed=>$real_field_val){
                        //real_field_val可能是一常数
                        foreach($rowArr as &$r0002){
                            if(isset($r0002[$real_field_val])){
                                $alias_value = $r0002[$real_field_val];
                                $this->arrayInsert($r0002, $real_field_val, array(
                                        $alias_filed => $alias_value
                                    )
                                );
                            }else{//常数
                                $r0002[$alias_filed] = $real_field_val;
                            }
                        }
                    }
                }
            }
            if(!$aggregated){
                if(isset($exArr['join']) && is_array($exArr['join'])){
                    foreach($exArr['join'] as $_table => $_fkpks){
                        if(!$_fkpks)continue;
                        $_alias = $_table = trim($_table); //默认别名
                        if($_spe = strpos($_table, ' ')){//空格(空格后的是别名)
                            $_alias = trim(substr($_table, $_spe+1)); //它必须在前面,不然_table的值会变
                            $_table = trim(substr($_table, 0, $_spe));
                        }else{
                            if($_hyp = strrpos($_alias, '_')){//下划线(下划线是别名)
                                $_alias = trim(substr($_alias, $_hyp+1));
                            }
                            if($_dot = strpos($_alias, '.')){
                                $_alias = substr($_alias, $_dot+1);
                            }
                        }
                        // var_dump($_table);
                        $_fkpks  = trim($_fkpks, ';');
                        $fkpkArr = explode(';', $_fkpks);
                        for($i=0,$max=count($fkpkArr); $i<$max; $i++){//一个表的多个字段join同一个表
                            $fkpk = $fkpkArr[$i];//fk,pk,ewh(附加条件)
                            $_ewh_arr = array(); //附加条件
                            $_eex_arr = array(); //附加参数
                            if(strpos($fkpk, '|')){
                                $_tmp_arr = explode('|', $fkpk);
                                $fkpk = array_shift($_tmp_arr);
                                foreach($_tmp_arr as $_tmp_str){
                                    $_k = strchr($_tmp_str, '=', true);
                                    if(false===$_k || !isset($this->keyArr[$_k])){
                                        parse_str($_tmp_str, $_tmp_wh);
                                        $_ewh_arr = array_merge($_ewh_arr, $_tmp_wh);
                                    }else{
                                        $_eex_arr[$_k] = ltrim(strchr($_tmp_str,'='),'=');
                                    }
                                }
                            }
                            list($kL, $kR) = explode(':', $fkpk);
                            if(1){
                                $_left_arr = $this->getArrayColumn($rowArr, $kL); //左表的值
                                $_left_cnt = count($_left_arr); //个数
                                $_wh_arr   = array_merge(array("$kR in"=>$_left_arr), $_ewh_arr);
                                if($this->isUniqueKey($_table, $kR)){
                                    //一对一模式
                                    $_ex_arr = array_merge(array('limit'=>$_left_cnt,'only_data'=>true), $_eex_arr);
                                    // $fRows = $db->getAll($_table, $_wh_arr, $_ex_arr);
                                    $fRows = $this->getMore($_table, $_wh_arr, $_ex_arr);
                                    // echo $db->getSqlString(true);
                                    $defaults = array();
                                    if(isset($_eex_arr['defaults'])){
                                        parse_str($_eex_arr['defaults'], $defaults);
                                    }
                                    if($fRows || $defaults){
                                        // $_subkey = $i>0?($_table.$i):$_table;
                                        $_subkey = $i>0?($_alias.$i):$_alias;
                                        if(isset($_eex_arr['flat'])){
                                            $rowArr = $this->joinToField($rowArr, $fRows, "$kL:$kR", $_eex_arr['flat'], isset($_eex_arr['prefix'])?$_eex_arr['prefix']:'', $defaults);
                                        }else{
                                            $rowArr = $this->joinToArray($rowArr, $fRows, "$kL:$kR", $_subkey, false, $defaults);
                                        }
                                    }
                                }else{
                                    //一对多模式(树壮)
                                    $_ex_arr = array_merge(array('limit'=>1000*$_left_cnt,'only_data'=>true), $_eex_arr);
                                    // $fRows = $db->getAll($_table, $_wh_arr, $_ex_arr);
                                    $fRows = $this->getMore($_table, $_wh_arr, $_ex_arr);
                                    // echo $db->getSqlString(true);
                                    if($fRows){
                                        //因为这时的pk和fk相当于调换了位置,所以和上面的是相反的
                                        $rowArr = $this->joinToTrray($rowArr, $fRows, "$kL:$kR", $_alias);
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if(isset($exArr['keyas'])){
                $rowArr = $this->fieldAsKey($rowArr, $exArr['keyas']);
            }
        }else{
            if(false === $rowArr){
                $this->writeDaoError(array(__FUNCTION__,$table,$db->getError(),$db->getSql(true)));
            }
        }
        if(isset($exArr['log'])){
            $this->writeDaoLog($db->getSql(true), $exArr['log']);
        }
        // printf("%s\n", $db->getSql(true));
        // print_r($db->getSql());
        return $rowArr;
    }
    /*
    * desc: 和getMore相同,只返回data部分,不返回total
    *
    *@table --- str [dbname.]table
    *return array(row1,row2...)
    */
    public function getData($table, $whArr=array(), $exArr=array())
    {
        if(is_scalar($exArr)){
            $limit = floatval($exArr);
            $exArr = array('only_data'=>true, 'limit'=>$limit);
        }else{
            $exArr = array_merge($exArr, array('only_data'=>true));
        }
        return $this->getMore($table, $whArr, $exArr);
    }
    /*
    * desc: 获取一条记录(同getData)
    *
    */
    public function getAtom($table, $whArr=array(), $exArr=array())
    {
        $exArr = is_array($exArr)?$exArr:array();
        /*if(is_scalar($whArr)){
            $whArr = array('id'=>$whArr);//默认当成id使用
        }*/
        // if(empty($whArr))return false;//不允许空条件查询(防止业务上的错误)
        $exArr['limit']     = 1;
        $exArr['only_data'] = true;
        $rowArr = $this->getMore($table, $whArr, $exArr);
        if($rowArr && isset($rowArr[0])){
            return $rowArr[0];
        }
        return $rowArr;
    }
    /*
    * desc: 聚合查询(mongo-only)
    * call: $this->aggregate('db.table',null,array(
    *           'aggregate' => array(
    *               array('match' => array('cdate>'=>'2016-10-14')),
    *               array('group' => 'cdate,status'),
    *               array('sort'  => array('cdate'=>-1)),
    *               array('limit' => 5),
    *           )
    *       )
    *   );
    * see also: https://docs.mongodb.com/v3.2/reference/operator/aggregation-pipeline/
    */
    public function aggregate($table, $whArr=array(), $exArr=array())
    {
        $db = $this->getConnection();
        $rowArr = $db->aggregating($table, $whArr, $exArr);
        if(false === $rowArr){
            $this->writeDaoError(array(__FUNCTION__,$table,$db->getError(),$db->getSql(true)));
        }
        if(isset($exArr['log'])){
            $this->writeDaoLog($db->getSql(true), $exArr['log']);
        }
        return $rowArr;
    }
    /*
    * desc: 根据条件获取条数
    *       一般情况下，不需要在exArr里填写fields参数
    *       但有些情况下是必须要填写fields参数的，如group模式下
    */
    public function getCount($table, $whArr=array(), $exArr=array())
    {
        return $this->getConnection()->count($table, $whArr, $exArr);
    }
    /*
    * desc: 添加一行记录
    *
    *@table --- str [dbname.]table
    *@data  --- arr 如果为空表示添加一条空记录(id自增)
    *@exArr --- arr array(
    *                   exists => '字段|update=要更新的字段序列|ignore=要忽略的字段序列'
    *                   ondups => 'update:要更新的字段序列|ignore:要忽略的字段序列|express:字段=值'
    *                   join_表名 => 左键:右键|fields=^字段1,字段2 ^表示非模式即不要插入相应字段
    *               )
    *
    */
    public function addAtom($table, $data=array(), $exArr=array())
    {
        if(!$data || is_scalar($data)) return false;
        $this->fxValues($table, $data);
        if(!$data) return false;
        if($exArr){
            $this->ftExtras($table, $exArr);
        }
        $addArr = $this->ftValues($table, $data);

        $db = $this->getConnection();
        if(isset($exArr['exists']) && ($exists=trim($exArr['exists']))){//检查是否已经存在
            if($pos = strpos($exists, '|')){
                $afters = trim(substr($exists, $pos+1));//数据存在之后的一系列操作
                $exists = substr($exists, 0, $pos);
            }
            $tArr  = preg_split("/[^a-z0-9_]+/i", $exists);//长度必须大于0
            $fArr  = array_flip($tArr);
            $whArr = array_intersect_key($data, $fArr);
            if($whArr && $this->getAtom($table,$whArr)){
                if(isset($afters)){
                    $aftArr = explode('|', trim($afters,' |'));
                    $doArr  = array();
                    foreach($aftArr as $aft){
                        $aft = trim($aft);
                        if(strpos($aft, '=')){
                            list($do, $work) = explode('=', $aft);
                            $doArr[strtolower($do)]  = $work;
                        }else{
                            $doArr[strtolower($aft)] = '';
                        }
                    }
                    if(isset($doArr['update'])){
                        $upArr = array_diff_key($data,$whArr);//默认要更新的字段
                        if($doArr['update']){
                            $upArr = array_intersect_key($upArr, array_flip(explode(',',$doArr['update'])));//更新指定字段
                        }
                        if(isset($doArr['ignore']) && $doArr['ignore']){//要忽略的字段
                            $upArr = array_diff_key($upArr, array_flip(explode(',',$doArr['ignore'])));
                        }
                        if($upArr) return $this->updateData($table, $upArr, $whArr);
                    }
                }
                return true;
            }
        }
        $id = $db->insert($table, $addArr, $exArr);
        // printf("%s\n", $db->getSql(true));
        // print_r($db->getError());
        if(isset($exArr['log'])){
            $this->writeDaoLog($db->getSql(true), $exArr['log']);
        }
        if(false === $id){
            $this->writeDaoError(array(__FUNCTION__,$table,$addArr,$db->getError(),$db->getSql(true)));
        }else{
            //检查join插入=========================
            $join = isset($exArr['join'])?$exArr['join']:null;
            if($join && is_array($join)){
                foreach($join as $jTable => $fkpk){
                    $fkpk = trim(trim(trim($fkpk), ';'));
                    if(!$fkpk)continue;
                    $jTable  = trim($jTable);
                    $eex_arr = array(); //附加参数
                    if($pos = strpos($fkpk,'|')){
                        $exstr = substr($fkpk,$pos+1); //条件
                        $fkpk  = substr($fkpk,0,$pos);
                        parse_str(str_replace('|','&',$exstr),$eex_arr);
                    }
                    list($kL, $kR) = explode(':', $fkpk);
                    if(isset($eex_arr['fields']) && $eex_arr['fields']){
                        if('^' == $eex_arr['fields'][0]){
                            $jDesc = $this->LoadTableCache($jTable);
                            if($jDesc){
                                $jData = array_diff_key(array_flip(array_keys($jDesc->types)), array_flip(explode(',', ltrim($eex_arr['fields'],'^'))));
                            }
                        }else{
                            $jData = array_flip(explode(',', $eex_arr['fields']));//join data
                        }
                        if(isset($jData)){
                            //检查带有默认值的字段
                            $jDefs = array();
                            foreach($jData as $dk=>$dv){
                                if(strpos($dk, '.')){
                                    list($dk, $dv) = explode('.', $dk);
                                    $jDefs[$dk] = $dv;
                                    $jData[$dk] = $dv;
                                }
                            }
                            $jData = array_intersect_key($data, $jData);
                            if($jDefs){
                                $jDefs = array_diff_key($jDefs, $jData);
                                $jData = array_merge($jData, $jDefs);
                            }
                            $jData[$kR] = $id;
                            $this->addAtom($jTable, $jData, array_intersect_key($eex_arr, array('ondups'=>1)));//增加更新(onduplicate key update)
                        }
                    }
                }
            }
            //检查join插入======================end
        }
        return $id;
    }
    /*
    * desc: 添加多行记录
    *
    *@table --- str [dbname.]table
    *@dataArr --- arr 二维数组
    *
    */
    public function addMore($table, $dataArr=array(), $exArr=array())
    {
        if(empty($dataArr))return false;
        $this->fxValues($table, $dataArr);
        if(!$dataArr) return false;
        $dataArr = $this->ftValues($table, $dataArr);

        $db = $this->getConnection();
        $ok = $db->inserts($table, $dataArr, $exArr);
        // echo $db->getSql(true);
        if(isset($exArr['log'])){
            $this->writeDaoLog($db->getSql(true), $exArr['log']);
        }
        if(false === $ok){
            $this->writeDaoError(array(__FUNCTION__,$table,$dataArr,$db->getError(),$db->getSql(true)));
        }else{
        }
        return $ok;
    }
    /*
    * desc: 修改数据
    *
    *@table --- str [dbname.]table
    *@data  --- arr 一维数组
    *
    */
    public function updateData($table, $data, $whArr=array(), $exArr=array())
    {
        if(empty($whArr))return false;//不允许空条件查询(防止业务上的错误)
        $this->removeArrayNull($data);
        $upArr = $this->ftValues($table,$data);
        if(empty($upArr))return false;
        if($exArr){
            $this->ftExtras($table, $exArr);
        }
        $db = $this->getConnection();
        
        if(is_scalar($whArr)){
            $primarykey = $this->getPrimaryKey($table);
            if(!$primarykey) $primarykey = 'id';
            $whArr = array($primarykey => $whArr);//默认当成id使用
        }
        $ok = $db->update($table, $upArr, $whArr, $exArr);
        // echo $db->getSql(true);
        if(isset($exArr['log'])){
            $this->writeDaoLog($db->getSql(true), $exArr['log']);
        }
        if(false === $ok){
            $this->writeDaoError(array(__FUNCTION__,$table,$upArr,$db->getError(),$db->getSql(true)));
        }else{
            //检查join修改=========================
            $join = isset($exArr['join'])?$exArr['join']:null;
            if($join && is_array($join)){
                foreach($join as $jTable => $fkpk){
                    $fkpk = trim(trim(trim($fkpk), ';'));
                    if(!$fkpk)continue;
                    $jTable  = trim($jTable);
                    $eex_arr = array(); //附加参数
                    if($pos = strpos($fkpk,'|')){
                        $exstr = substr($fkpk,$pos+1); //条件
                        $fkpk  = substr($fkpk,0,$pos);
                        parse_str(str_replace('|','&',$exstr),$eex_arr);
                    }
                    list($kL, $kR) = explode(':', $fkpk);
                    if(isset($eex_arr['fields']) && $eex_arr['fields'] && isset($whArr[$kL])){
                        if('^' == $eex_arr['fields'][0]){
                            $jDesc = $this->LoadTableCache($jTable);
                            if($jDesc){
                                $jData = array_diff_key(array_flip(array_keys($jDesc->types)), array_flip(explode(',', ltrim($eex_arr['fields'],'^'))));
                            }
                        }else{
                            $jData = array_flip(explode(',', $eex_arr['fields']));//join data
                        }
                        if(isset($jData)){
                            $jData = array_intersect_key($data, $jData);
                            $jWh   = array($kR=>$whArr[$kL]);
                            if(isset($eex_arr['where'])){
                                $jWh[] = $eex_arr['where'];
                            }
                            if(isset($eex_arr['onmiss']) && 'insert'==$eex_arr['onmiss']){
                                $jOld = $this->getAtom($jTable, $jWh);
                                if(!$jOld){
                                    $jAdded = $this->addAtom($jTable, array_merge($jData,array($kR=>$whArr[$kL])));
                                }
                            }
                            if(!isset($jAdded)){
                                $this->updateData($jTable, $jData, $jWh);
                            }
                        }
                    }
                }
            }
            //检查join修改======================end
        }
        return $ok;
    }
    /*
    * desc: 替换性地添加多条数据
    *
    *@fields  -- 作为条件要替换的字段
    *@dataArr -- arr 二维
    *
    */
    public function replaceData($table, $dataArr, $fields='id', $exArr=array())
    {
        $fArr = explode(',', preg_replace("/[^a-z0-9_\,]/si", '', $fields));
        foreach($dataArr as $row){
            $this->removeArrayNull($row);
            $wh = array();
            foreach($fArr as $f){
                if(!isset($row[$f])) return false;
                $wh[$f] = $row[$f];
            }
            if(empty($wh))return false;
            $old = $this->getAtom($table, $wh);
            if($old){
                $ok = $this->updateData($table, $row, $wh);
            }else{
                $ok = $this->addAtom($table, $row);
            }
            if(false === $ok) return false;
        }
        return true;
    }
    /*
    * desc: 等同于replaceData
    *
    */
    public function replaceMore($table, $dataArr, $fields='id', $exArr=array())
    {
        return $this->replaceData($table, $dataArr, $fields, $exArr);
    }
    /*
    * desc: 替换性地添加一条数据
    *
    *@fields  -- 作为条件要替换的字段
    *@dataArr -- arr 一维
    *
    */
    public function replaceAtom($table, $row, $fields='id', $exArr=array())
    {
        return $this->replaceData($table, array($row), $fields, $exArr);
    }
    /*
    * desc: 批量修改(用于复杂的update操作)
    *
    *@table --- str [dbname.]table
    *@dataArr --- arr 二维数组
    *               array(
    *                   v1=>array(f1:v1,f2:v2,...),
    *                   v2=>array(f1:v1,f2:v2,...),
    *                   v3=>array(f1:v1,f2:v2,...),
    *               )
    *               v1,v2,v2和whArr中v1,v2,v3的个数和值完全一样
    *@whArr   --- arr
    *               array(field=>array(v1,v2,v3,...))
    *sql = update t1 set 
    *       f1 = CASE id 
    *           WHEN 1 THEN 11
    *           WHEN 2 THEN 22
    *       END,
    *       f2 = CASE id 
    *           WHEN 1 THEN -11
    *           WHEN 2 THEN -22
    *       END
    *       where id in(1,2)
    *eg.
    *   $this->updatePackage('test.t1', array(1=>array('f1'=>'0','f2'=>'-0'),2=>array('f1'=>'2222','f2'=>'-222')), array('id'=>array(1,2)));
    *
    */
    public function updatePackage($table, $dataArr, $whArr, $exArr=array())
    {
        //组装sql
        $whfield  = current(array_keys($whArr)); //条件字段名(通常为id)
        $whvalArr = $whArr[$whfield];
        if(count($dataArr) != count($whvalArr))return false; //个数不等
        $upfieldArr = array_keys(current($dataArr)); //要更新的字段
        $sql_sets = '';
        foreach($upfieldArr as $upfield){
            $sql_sets .= "{$upfield} = CASE {$whfield} ";
            foreach($whvalArr as $case_v){
                $sql_sets .= "WHEN '{$case_v}' THEN '" . addslashes($dataArr[$case_v][$upfield]) . "' ";
            }
            $sql_sets .= "END,";
        }
        $sql_sets  = trim($sql_sets, ',');
        $sql_where = "{$whfield} in('".implode("','",$whvalArr)."')";
        $sql = "update {$table} set {$sql_sets} where {$sql_where}";
        //组装sql end
        return $this->Execute($sql, 2);
    }
    /*
    * desc: 删除数据
    *
    *@table --- str [dbname.]table
    *@whArr --- arr 条件
    *@exArr --- arr|int int是为limit值
    *
    *return 返回删除的行数
    */
    public function deleteData($table, $whArr, $exArr=array())
    {
        if(!$table || empty($whArr))return false; //不允许空条件查询(防止业务上的错误)
        if(is_scalar($exArr)){
            $limit = intval($exArr);
        }else{
            $limit = isset($exArr['limit'])?$exArr['limit']:1;
        }
        if($exArr){
            $this->ftExtras($table, $exArr);
        }
        $db = $this->getConnection();
        if(is_scalar($whArr)){
            $primarykey = $this->getPrimaryKey($table);
            if(!$primarykey) $primarykey = 'id';
            $whArr = array($primarykey => $whArr);//默认当成id使用
        }
        // $ok = $db->wh($whArr)->remove($limit);
        $ok = $db->remove($table, $whArr, $limit);
        // echo $db->getSql(true);
        if(isset($exArr['log'])){
            $this->writeDaoLog($db->getSql(true), $exArr['log'], 'dao');
        }
        if(false === $ok){
            $this->writeDaoError(array(__FUNCTION__,$table,$whArr,$exArr,$db->getError(),$db->getSql(true)));
            // throw new Exception($db->getSql(), 1);
        }else{
            //检查join删除=========================
            $join = isset($exArr['join'])?$exArr['join']:null;
            if($join && is_array($join)){
                foreach($join as $jTable => $fkpk){
                    $fkpk = trim(trim(trim($fkpk), ';'));
                    if(!$fkpk)continue;
                    $jTable  = trim($jTable);
                    list($kL, $kR) = explode(':', $fkpk);
                    if(isset($whArr[$kL])){
                        $jLimit = $this->isUniqueKey($jTable, $kR)?1:10000;
                        $this->deleteData($jTable, array($kR=>$whArr[$kL]), $jLimit);
                    }
                }
            }
            //检查join删除======================end
        }
        return $ok;
    }
    public function mkWhere($whArr)
    {
        return $this->getConnection(true)->parseWhere($whArr);
    }
    public function mkQuery($whstring, $exArr=null)
    {
        return $this->getConnection(true)->mkQuery($whstring, $exArr);
    }
    public function Execute($sqlArr, $etype=0)
    {
        $singled = false;
        if(is_scalar($sqlArr)) {
            $sqlArr = array($sqlArr);
            $singled = true;
        }
        if(empty($sqlArr))return false;
        $db = $this->getConnection();
        $retArr = array();
        foreach($sqlArr as $sql){
            $retArr[] = $db->execute($sql, $etype);
            $this->errArr[] = $db->getError();
        }
        if($singled) return current($retArr);
        return $retArr;
    }
    public function Locking($table=null, $ltype='read')
    {
        return $this->getConnection(true)->lock($table, $ltype);
    }
    public function Release($table=null)
    {
        return $this->getConnection(true)->unlock($table);
    }
    public function tableExists($table)
    {
        return $this->LoadTableCache($table)?true:false;
    }

    public function getTypes($table)
    {
        $tc = $this->LoadTableCache($table);
        if($tc) return $tc->types;
        return false;
    }
    /*
    * desc: 值过滤,添加或更新时使用
    *
    */
    private function ftValues($table, $records, $exArr=array())
    {
        $tc = $this->LoadTableCache($table);
        if(!$tc || !$records) return $records;

        $quoting  = isset($exArr['quoting'])?$exArr['quoting']:false;
        $kickout  = isset($exArr['kickout'])?$exArr['kickout']:true;

        if($this->__isMutx($records)){//多维模式
            foreach($records as &$record){
                $this->__ftVals($tc, $tc->types, $record, $quoting, $kickout);
            }
        }else{//一维
            $this->__ftVals($tc, $tc->types, $records, $quoting, $kickout);
        }
        return $records;
    }
    /*
    * desc: 判断是否为矩阵模式
    *
    */
    private function __isMutx(&$records)
    {
        if(!is_array($records)) return false;
        foreach($records as $kk=>$vv){
            if(is_string($kk))return false;
            if(!is_array($vv))return false;
        }
        return true;
    }
    private function __ftVals(&$tc, &$typeArr, &$record, $quoting=false, $kickout=false)
    {
        if(!$record || is_scalar($record)) return;
        $fieldArr = array_keys($typeArr);

        foreach($record as $f=>&$v) {
            if(!isset($typeArr[$f])){
                unset($record[$f]);
                continue;
            }
            if(!in_array($f, $fieldArr)) {
                if($kickout){
                    unset($record[$f]);
                }else{
                    if($quoting) $v = "'" . $v . "'";
                }
                continue;
            }
            //类型
            $_type = $typeArr[$f]['type'];
            $_type = trim(str_replace('unsigned','',$_type));
            if(is_array($v)){
                //mongo专有
                if('elements' == $_type){
                    if(isset($typeArr[$f]['elem'])){
                        foreach($v as &$subv){
                            $this->__ftVals($tc, $typeArr[$f]['elem'], $subv, $quoting, $kickout);
                        }
                    }
                }elseif('hash' == $_type){//其实就是一个k-v型的数组
                    if(isset($typeArr[$f]['elem'])){
                        $this->__ftVals($tc, $typeArr[$f]['elem'], $v, $quoting, false);
                    }
                }else{
                    if(false !== strpos($_type, 'array')){
                        $subtype = str_replace(array('array',' '), '', $_type);
                        if(in_array($subtype, $this->intArr) || in_array($subtype, $this->floatArr)){
                            foreach($v as &$subv){
                                $subv = floatval($subv);
                            }
                        }
                    }
                }
                continue;
            }
            //其它类型
            if(in_array($_type, $this->intArr)){
                if('mongo' == $tc->driver){
                    $v = floatval($v);
                }else{
                    if(function_exists('bcadd')){
                        $v = bcadd(strval($v), 0, 0);
                    }else{
                        $v = number_format(floatval($v), 0,'','');
                    }
                }
                continue;
            }elseif(in_array($_type, $this->floatArr)){
                if(isset($typeArr[$f]['xArr'])){
                    $exArr = $typeArr[$f]['xArr'];
                    if('mongo' == $tc->driver){
                        $v = floatval($v);
                    }else{
                        if(function_exists('bcadd')){
                            $v = bcadd(strval($v), 0, $exArr['len2']);
                        }else{
                            $v = number_format(floatval($v),$exArr['len2'],$exArr['dot'],'');
                        }
                    }
                    continue;
                }
            }elseif('datetime' == $_type){
                if(empty($v)) {
                    unset($record[$f]);
                    continue;
                }
            }elseif('date' == $_type){
                if(empty($v)) {
                    unset($record[$f]);
                    continue;
                }
            }
            if($quoting) $v = "'" . addslashes($v) . "'";
        }
    }
    /*
    * desc: 修正值
    *
    *@had --- have 检查
    */
    private function fxValues($table, &$records, $had=false)
    {
        $tc = $this->LoadTableCache($table);
        if(!$tc) return $records;
        $typeArr  = &$tc->types;
        if(isset($records[0])){
            foreach($records as &$record){
                if(!$this->__fxVals($tc, $typeArr, $record, $had)) return false;
            }
        }else{
            if(!$this->__fxVals($tc, $typeArr, $records, $had)) return false;
        }
        return true;
    }
    private function __fxVals(&$tc, &$typeArr, &$record, $had=false)
    {
        foreach($typeArr as $f=>$arr){
            if(!isset($record[$f])){
                if('PK' == $arr['prik'] || 'UNI'==$arr['unix'])continue;
                if(is_null($arr['deft']) && 'NOT NULL' == $arr['null']){
                    //这是一种矛盾的设计
                    $record[$f] = '';
                }
                continue;
            }
            if((isset($arr['auto']) && $arr['auto']) || $arr['prik'])continue;
            if(is_null($record[$f]) && 'NOT NULL' == $arr['null'] && is_null($arr['deft'])){
                $record[$f] = '';
            }
            if(!$record[$f] && ('datetime'==$arr['type'] || 'date'==$arr['type'] || 'time'==$arr['type'])){
                unset($record[$f]);
            }
            // if($had && isset($arr['have']) && !isset($record[$f])) return false;
        }
        return true;
    }
    /*
    * desc: 字段过滤(对于mongo无效)
    *       像mssql不支持backquote,那它在组装完成后将其替换掉
    *@fields  --- string filed list("f1,f2" or "^f1")
    *@descArr --- array  table structure info
    *return: string of field list
    */
    public function ftFields($table, $fields, $backquote='`')
    {
        if(empty($fields) || '*'==$fields) {
            return '*';
        }
        $tc = $this->LoadTableCache($table);
        if(!$tc || 'mongo'==$tc->driver) return $fields;
        $typeArr  = &$tc->types;
        $fieldArr = array_keys($typeArr);

        $fds = trim($fields);
        $distinct = null;
        if(0 === strpos(strtolower($fds), 'distinct')){
            $fds = str_ireplace('distinct ', '', $fds);
            $distinct = 'distinct ';
        }
        if(empty($typeArr)) {
            return '*';
        }
        $igArr = array();
        if(strpos($fds, '(')){//这意味着有函数表达式
            $ok = preg_match_all("/(?|[`a-z\_][a-z\_0-9\s\+\-\*\/`]+?,|[a-z\_][0-9a-z_\s]+?\(.*?\).*?\,|\(.*?\).*?\,|\*,)/i", ltrim($fds,'^').',', $pArr);
            if(!isset($pArr[0])) return '*';
            $pArr = $pArr[0];
        }else{//这是为了提高性能
            if(false !== strpos($fds, '^')){
                // echo ltrim(strstr($fds,'^',true),'^');
                $pArr = explode(',', trim(strstr($fds,'^',true),','));
                $igArr = explode(',', ltrim(strstr($fds,'^'),'^'));
            }else{
                $pArr = explode(',', ltrim($fds,'^'));
            }
        }
        $fArr = array();
        if($igArr){
            foreach($igArr as &$if){
                $if = ltrim(trim(trim(trim($if,','))), '^');
            }
        }
        if($pArr){
            foreach($pArr as $_f){
                if($_f){
                    $fArr[] = ltrim(trim(trim(trim($_f,','))), '^');
                }
            }
        }else{
           $fArr = explode(',', trim($fds,'^'));
           foreach($fArr as &$_f)$_f=trim($_f);
        }
        if(false !== ($pos = strpos($fds,'^'))){ //过滤模式
            foreach($fieldArr as $k=>$f){
                if(in_array($f, $igArr)) unset($fieldArr[$k]);
            }
            if($fArr){
                $fieldArr = array_merge($fArr, $fieldArr);
            }
            $fds = $backquote.implode($backquote.','.$backquote, $fieldArr).$backquote;
            if(strpos($fds, ' ')){
                $fds = str_replace(' ', $backquote.' '.$backquote, $fds);
            }
            if(false !== strpos($fds, $backquote.$backquote)){
                $fds = str_replace($backquote.$backquote, $backquote, $fds);
            }
        }else {
            foreach($fArr as $k=>&$f){
                if('*' == $f)continue;
                if(strpos($f,'+') || strpos($f,'-') || strpos($f,'*') || strpos($f,'/') || strpos($f,'('))continue;//表达式
                // if(preg_match("/[0-9a-z_]+?\(.*\)/si", $f))continue;//说明是mysql函数
                if(strpos($f, ' ')){ //有空格说明字段起了别名
                    $realf = strstr($f, ' ', true);
                    $alias = trim(strstr($f, ' '));
                    if(false !== strpos(strtolower($alias), 'as ')){
                        $alias = trim($alias);
                        $alias = ltrim(strstr($alias, ' '));
                    }
                    $realf = trim($realf, $backquote);
                    $alias = trim($alias, $backquote);
                    if(!in_array($realf, $fieldArr)) {
                        unset($fArr[$k]);
                        continue;
                    }
                    $f = $backquote.$realf.$backquote .' '. $backquote.$alias.$backquote;
                }else{
                    if(!in_array(trim($f,$backquote), $fieldArr)) {
                        unset($fArr[$k]);
                    }else{
                        if(false === strpos($f, $backquote)){
                            $f = "{$backquote}{$f}{$backquote}";
                        }
                    }
                }
            }
            // print_r($fArr);exit;
            if(empty($fArr)){
                $fds = '*';
            }else{
                $fds = implode(',', $fArr);
            }
        }
        return $distinct ? $distinct.$fds : $fds;
    }
    public function ftExtras($table, &$exArr)
    {
        if(!$exArr || !is_array($exArr)) return;
        $main_prex = isset($exArr['main_prex'])?$exArr['main_prex']:$table;
        foreach($exArr as $k=>$v){
            if(is_array($v))continue;
            if(strpos($v,':') && 'join' == strtolower(substr($k,0,4))){
                $join_table = $main_prex . substr($k,4);
                if(is_string($v)){
                    $exArr['join'][$join_table] = $v;
                    unset($exArr[$k]);
                }
            }
        }
    }

    public function isPrimaryKey($table, $field)
    {
        $tc     = $this->LoadTableCache($table);
        $types  = &$tc->types;
        return 'PK'==(isset($types[$field]['prik'])&&$types[$field]['prik'])?true:false;
    }
    public function isUniqueKey($table, $field)
    {
        $tc = $this->LoadTableCache($table);
        if($this->isPrimaryKey($table, $field)) return true;
        $types = &$tc->types;
        return 'UNI'==(isset($types[$field]['unix'])&&$types[$field]['unix'])?true:false;
    }
    public function getPrimaryKey($table)
    {
        $tc = $this->LoadTableCache($table);
        if(!$tc) return false;
        return $tc->prkey;
    }
    /*
    * desc: get CREATE TABLE information
    *
    */
    public function getCreates($table)
    {
        $db = $this->getConnection();
        $tArr = $db->getCreates($table);
        if(isset($tArr[0])){
            $tArr = array_pop($tArr);
        }
        if(isset($tArr['Create Table'])){
            return $tArr['Create Table'];
        }
        return $tArr;
    }
    /*
    * desc: 为表格创建模板
    *
    */
    public function LoadTableCache($table)
    {
        if(!isset($this->Arr809613623213726)){
            //暂存 dbmodel(原:dbmodelArr)
            $this->Arr809613623213726 = array();
        }
        //缓存查询以提高效率**********************
        $cacheid = 'cahce_'.$table;//不需要单独提取dbname(因为,如果是非当前库table自动带一个dbname的)
        if(isset($this->Arr809613623213726[$cacheid]) && is_object($this->Arr809613623213726[$cacheid])){
            //防止重复加载以提高效率
            return $this->Arr809613623213726[$cacheid];
        }
        //提取必要的文件史、类名等****************
        $db = $this->getConnection(true);
        if(!$db) return false;
        if($pos = strpos($table,'.')){
            $dbName = substr($table, 0, $pos);
            $class  = substr($table, $pos +1);
        }else{
            $dbName = $db->dbName;
            if('sqlite' == $db->getConfig('driver'))$dbName = basename($dbName,'.db');
            $class  = $table;
        }
        $class = sprintf("_%s_%s", $dbName,$class);

        //判断是否已经创建************************
        $dbLoc = $this->getConfig('boot') .'/dao/'. $dbName;
        $tableLoc = $dbLoc .'/'. $class . '.php';
        if(is_file($tableLoc)){
            // require($tableLoc);
            $this->requireOnce($tableLoc);
            return $this->Arr809613623213726[$cacheid] = new $class;
        }

        //获取描述信息****************************
        $descArr = $db->getDesc($table);
        if(!$descArr){
            $this->error = $db->getError();
            return false;
        }else{
            if(!is_dir($dbLoc))mkdir($dbLoc, 0755);
        }

        //生成模板********************************
        $driver     = $db->getConfig('driver');
        $primarykey = null;
        foreach($descArr as $f=>&$dArr){
            $_type = $dArr['type'];
            preg_match("/(.*?)\(([0-9]+),([0-9]+)\)/i", $_type, $pArr);
            if(4 == count($pArr)){
                //在浮点型数据中提取整数部分和小数部分的长度
                $dArr['type'] = $pArr[1];
                $dot = intval($pArr[3])>0?'.':'';
                $dArr['xArr'] = array('len1'=>$pArr[2], 'len2'=>$pArr[3], 'dot'=>$dot);
            }
            if(!$primarykey && 'PK' == $dArr['prik']){
                $primarykey = $f;
            }
        }
        $fields = var_export($descArr, true);
        $fields = preg_replace("/\s*\=\>\s*[\n\r]{0,2}/si", '=>', $fields);
        $fields = str_replace("  ", "\t", $fields);
        $fields = str_replace("\n", "\n\t", $fields);
        // echo $fields;
        
        $template  = "<?php\n";
        $template .= "/**\n";
        $template .= " * desc: {$table} model\n";
        $template .= " *\n";
        $template .= "*/\n\n";
        
        $template .= "class {$class} {\n";
        $template .= "\tpublic \$driver = '{$driver}';\n";
        $template .= "\tpublic \$db     = '{$dbName}';\n";
        $template .= "\tpublic \$table  = '{$table}';\n";
        $template .= "\tpublic \$prkey  = '{$primarykey}';\n";
        $template .= "\tpublic \$types  = {$fields};\n";
        $template .= "};";
        $template .= "";

        //保存文件**********************
        $ok = file_put_contents($tableLoc, $template);
        if(!$ok) return false;
        // require($tableLoc);
        $this->requireOnce($tableLoc);
        return $this->Arr809613623213726[$cacheid] = new $class;
    }
    public function getLastSqlString()
    {
        return $this->getConnection(true)->getSql(true);
    }
    public function getError()
    {
        return implode(";\n", $this->errArr);
    }

    private function writeDaoLog($logs, $basename)
    {
        return $this->writeLog($logs, $basename, 'dao');
    }
    private function writeDaoError($logs)
    {
        if(is_array($logs)){
            $logs[] = $this->CallStacks("\n\t\t");
        }
        return $this->writeError($logs, 'dao');
    }
};
