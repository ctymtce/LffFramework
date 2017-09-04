<?php
/**
 * autor: cty@20120325
 *  desc: global class Lff
 *
 *
*/
class Lff {
    
    static $App = null; //全局实例
    static $Dao = null; //数据库访问对象
    static $Sao = null; //会话访问对象
    
    /*
    * desc: 创建APP实例
    *
    *@configs --- arr  配置
    *@forced  --- bool 强制新建
    *
    */
    public static function App(&$configs=null, $forced=false)
    {
        if(is_null(self::$App) || $forced) {
            self::$App = new CApp($configs);
        }
        return self::$App;
    }
    
    public static function Dao()
    {
        if(is_null(self::$Dao)) {
            self::$Dao = new CDao(
                self::App()->getConfig('ds')
            );
        }
        return self::$Dao;
    }

    public static function Sao()
    {
        if(is_null(self::$Sao)) {
            self::$Sao = new CSession();
        }
        return self::$Sao;
    }
};
