<?php
/**
 * author: cty@20120328
 *   func: the basest model class
 *   desc: user model class must extend this class
 *
 *
*/

class CModel extends CEle{
    function __call($method, $args)
    {
        if(method_exists($this->Dao(), $method)){
            /*foreach($args as $argk=>&$argv){
                $args[$argk] = &$argv;
            }*/
            return call_user_func_array(array(
                $this->Dao(), $method
            ), $args);
        }
        return parent::__call($method, $args);
    }
    /*
    * desc: 数据库访问对象
    *
    */
    public function Dao()
    {
        return Lff::Dao();
    }
    public function getMore($table, $whArr=array(), $exArr=array(), &$total=0)
    {
        return $this->Dao()->getMore($table, $whArr, $exArr, $total);
    }
    public function Charset($charset='latin1',&$oldcharset=null)
    {
        return $this->Dao()->Encoding($charset, $oldcharset);
    }
};
