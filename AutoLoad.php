<?php
require(__DIR__.'/framework/Lff.php');
class CAutoLoad {

    static $debug = true;

    static $IncludedDirs = null; //被设置的include dirs

    static $pathArr = array(
                'framework',
                'framework/db',
                'framework/libs',
                'framework/base',
                'framework/http',
                'framework/model',
                'framework/cache',
                'framework/plugin',
            );

    /*
    *  desc: 
    *
    *@paths --- array(...) 绝对路径
    *
    *
    */
    static function AutoLoad($paths=array())
    {
        $realdir = __DIR__;//dirname(__FILE__);
        foreach(self::$pathArr as &$p){
            $p = $realdir.'/'.$p;
        }
        // print_r(self::$pathArr);

        if(is_array($paths) && count($paths)){
            self::$pathArr = array_merge(self::$pathArr, $paths);
        }

        self::importIncludePath(self::$pathArr);

        spl_autoload_register(array('CAutoLoad', 'AutoLoadFrameClass'));
    }

    static function importIncludePath($pathArr)
    {
        $PATHSPE = PATH_SEPARATOR; //冒号(:)
        foreach($pathArr as $path) {
            if((strpos($path,'*') || strpos($path,'+'))){
                $last_char = substr($path,-1);
                $path  = rtrim($path, '*+');
                $depth = '+'==$last_char?1:0; //扫描深度
                if(is_dir($path)){
                    self::walkdir($path, $subDirs, $depth);
                    foreach($subDirs as $subpath) {
                        set_include_path(get_include_path().$PATHSPE.$subpath);
                    }
                }
                unset($subDirs);
            }else{
                set_include_path(get_include_path().$PATHSPE.$path);
            }
        }
        self::$IncludedDirs = get_include_path();
    }
    /*
    * desc: 子目录扫描
    *@dir     --- str 要目录
    *@subDirs --- array[in]用于接收子目录
    *@depth   --- int 最大扫描级数
    *@depthed --- int 已扫描级数[外层调用时不用传]
    *return void
    */
    static function walkdir($dir, &$subDirs=array(), $depth=0, $depthed=0) 
    {
        if(!is_dir($dir)) return;
        if(0 == $depthed) $subDirs[] = $dir;
        $handler   = opendir($dir);
        $depthed  += 1; //depthed---已扫描的深度
        while(false !== ($filename = readdir($handler)))
        {
            if($filename != '.' && $filename != '..' && $filename != 'System Volume Information') {
                $fullpath = rtrim($dir,'/').'/'.$filename;
                // is_dir($fullpath) && $subDirs[] = $fullpath;
                if(is_dir($fullpath)) {
                    $subDirs[] = $fullpath;
                    if(0 == $depth){
                        self::walkdir($fullpath, $subDirs, $depth, $depthed);
                    }else{
                        if($depthed < $depth){
                            self::walkdir($fullpath, $subDirs, $depth, $depthed);
                        }
                    }
                }
            }
        }
        closedir($handler);
        return;
    }
    /**
     *
     * 根据include_path检查文件是否存在
     * 如果存在返回绝对路径
     * @param string $filename 文件名
     * @return string|false 文件不存在返回false，存在返回绝对路径
     *
     */
    static function fileExists($filename)
    {
        // 检查是不是绝对路径
        if(realpath($filename) == $filename) {
            return $filename;
        }
        //否则，当作相对路径判断处理
        /* 把获取到的include_path中的\替换成/
         * 避免假如路径结尾出有\，导致判断出现错误
        */
        // var_dump(PATH_SEPARATOR);
        // var_dump(DIRECTORY_SEPARATOR);exit;
        $paths = explode(PATH_SEPARATOR, str_replace('\\', DIRECTORY_SEPARATOR, get_include_path()));
        foreach($paths as $path) {
            /*if(substr($path, -1) == '/') {
                $fullpath = $path . $filename;
            }else {
                $fullpath = $path . '/' . $filename;
            }*/
            $path = rtrim($path,'/').'/'.$filename;
            if(file_exists($path)) {
                return realpath($path);
            }
        }
        return false;
    }

    static function AutoLoadFrameClass($class) 
    {
        $class = ucfirst($class);
        $cfile = $class . '.php';
        if(self::fileExists($cfile)){
            require $cfile;
        }
    }
};

