<?php
/**
 * author: cty@20120408
 *   func: CCache abstract class
 *
*/
abstract class CCache {
    //保存
    abstract public function save($id, $val, $expire=1800);
    //加载
    abstract public function load($id);
    //移除
    abstract public function remove($id);
    //清除所有
    abstract public function clean($all=true);
};
