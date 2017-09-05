<?php
/**
 * athor: cty@20120322
 *  func: base control
 *  desc: 
 * 
 * 
*/
abstract class CCtrl extends CEle {

    protected $layout  = 'layout';
    protected $isClean = true;
    protected $isExit  = true;
    protected $route   = null; //route of current request
    protected $cfile   = null; //controller file path
    protected $vfile   = null; //view file path
    protected $smarty  = null;

    public function setCfile($cfile)
    {
        $this->cfile = $cfile;
    }
    public function setVfile($vfile)
    {
        $this->vfile = $vfile;
    }
    
    public function render($viewId=null, $dataArr=array())
    {
        if(null === $viewId){
            $vfile = $this->vfile;
        }else{
            $vfile = $viewId;
            if(!is_file($viewId)) {
                // $this->httpError(404, 'The view file is not exists!');
                $vfile = dirname($this->vfile).'/'. $viewId . '.php';
            }
        }
        // echo $this->vfile;
        // exit($vfile);
        if(!is_file($vfile)) {
            $this->httpError(404, 'The view file does not exists!');
        }
        //1, get view file contents
        $viewContent = $this->renderFile($vfile, $dataArr);
        //2, get layout file contents
        $layFile = Lff::$App->layoutLoc . '/'. $this->layout . '.php';
        $layContent  = $this->renderFile($layFile, array('content'=>$viewContent));
        //3, output contents
        // ob_clean();
        // echo 'fffffffffffffffff';
        list($jscript, $jsfiles) = $this->getJS();
        // var_dump($jscript, $jsfiles);
        // print_r($jscript);
        if(strlen($jscript) > 0){
            $layContent = str_replace('</head>', $jscript.'</head>', $layContent);
        }
        if(strlen($jsfiles) > 0){
            $layContent = str_replace('</body>', $jsfiles.'</body>', $layContent);
        }
        echo $layContent;
    }
    function renderView($viewId, $paraArr=array(), $return=false)
    {
        // ob_clean();
        // ob_start();
        $viewFile = $this->getViewFile($viewId);
        $content = $this->renderFile($viewFile, $paraArr);
        if(!$return) exit($content);
        return $content;
    }
    function renderLayout($layout, $paraArr=array(), $return=true)
    {
        $layFile = $this->getLayFile($layout);
        $content = $this->renderFile($layFile, $paraArr);
        if(!$return) echo $content;
        return $content;
    }
    /**
    * author: cty@20120326
    *@viewId --- string(dir1/index)
    *return: /../../dir1/index.php(absolute path) 
    */
    function getViewFile($viewId)
    {
        if(is_file($viewId)) return $viewId;
        $viewLoc  = Lff::app()->viewLoc;
        $viewId   = trim($viewId, '/');
        $viewFile = $viewLoc.'/'.$viewId.'.php';
        return $viewLoc;
    }
    function getLayFile()
    {
        $layLoc  = Lff::app()->layLoc;
        $layFile = $layLoc.'/'.$this->layout . '.php';
        return $layFile;
    }
    /**
    * author: cty@20120331
    *   func: get js from jbuffArr
    */
    function getJS()
    {
        $jbuffArr = Lff::$App->jbuffArr;
        $jscript = $jsfiles = '';
        foreach($jbuffArr as $key => $js) {
            if(is_string($key)) {
                $jsfiles .= '<script type="text/javascript" src="'.$js.'" ></script>'."\n";
            }else {
                $jscript .= $js."\n";
            }
        }
        if(strlen($jscript) > 0) {
            $jscript = "<script type='text/javascript' >\n".'$(document).ready(function(){'."\n".$jscript."\n".'});'."\n</script>\n";
        }
        return array($jscript, $jsfiles);
    }
    /*********************************smarty******************************/
    public function LoadSmarty()
    {
        if(null === $this->smarty) {
            $App = Lff::App();
            $tpl_base_dir = $App->TPL_UI;
            $this->smarty = new CSmarty($tpl_base_dir);
            $configArr = $App->getConfig();
            foreach($configArr as $_k => $_v){
                $ascii = ord($_k);
                if($ascii >= 65 && $ascii <= 90){
                    $this->smarty->assign($_k,  $_v);
                }
            }
            $this->smarty->assign('HOME',       $App->home);
            $this->smarty->assign('ROOT',       $App->boot);
            $this->smarty->assign('BOOT',       $App->boot);
            $this->smarty->assign('ASSETS_LOC', $App->AssetsLoc);
            $this->smarty->assign('ASSETS_URL', $App->AssetsUrl);
            $this->smarty->assign('STATIC_LOC', $App->AssetsLoc.'/static');
            $this->smarty->assign('STATIC_URL', $App->AssetsUrl.'/static');
            $this->smarty->assign('UPLOAD_LOC', $App->AssetsLoc.'/static/upload');
            $this->smarty->assign('UPLOAD_URL', $App->AssetsUrl.'/static/upload');
            $this->smarty->assign('UI_URL',     $App->UiUrl);
            $this->smarty->assign('UI_LOC',     $App->UiLoc);
            $this->smarty->assign('TPL_UI',     $App->TPL_UI);
            $this->smarty->assign('TPL_APP',    $App->TPL_APP);
            $this->smarty->assign('TPL_COM',    $App->TPL_COM);
            $this->smarty->assign('TPL_LOC',    $tpl_base_dir);
            $this->smarty->assign('TPL_INC',    $tpl_base_dir.'/templates/include');
            $this->smarty->assign('TPL_ROOT',   $tpl_base_dir.'/templates');
            $this->smarty->assign('TPL_CACHE',  $tpl_base_dir.'/cache');
            $this->smarty->assign('TPL_LAYOUT', $tpl_base_dir.'/templates/layout');
            $this->smarty->assign('TPL_CONFIG', $tpl_base_dir.'/congigs');
        }
        return $this->smarty;
    }
    public function assign($key, $value=null, $nocache=false)
    {
        $smarty = $this->LoadSmarty();
        return $smarty->assign($key, $value, $nocache);
    }
    public function assigns($vArr, $nocache=false)
    {
        $smarty = $this->LoadSmarty();
        foreach ($vArr as $key => $value) {
            $smarty->assign($key, $value, $nocache);
        }
        return true;
    }
    private function _trim_template($template, $root=null)
    {
        $template = $template?$template:'default';
        $template = str_replace('.html', '', $template).'.html';
        if(!$root){
            $root = $this->getAppPath();
        }elseif(1 == $root){
            $root = $this->getController();
        }
        // echo $this->getController()."\n";
        $template = $root . '/'. ltrim($template, '/');
        return $template;
    }
    /*
    * desc: vendes to smarty templates
    *
    *@compile_id_or --- 既是是编译id又是模板控制类型
    *
    */
    public function display($template=null, $cache_id_or=null, $compile_id_or=null, $parent=null)
    {
        $this->_assign_common_variable();
        $smarty = $this->LoadSmarty();
        if(is_array($cache_id_or)){ //变量
            $varArr = &$cache_id_or;
            $this->assigns($varArr);
            $cache_id_or = null;
        }
        /*
        $template = $template?$template:'default';
        $template = str_replace('.html', '', $template).'.html';
        $template = $this->getAppPath() . '/'. ltrim($template, '/');
        */
        $template = $this->_trim_template($template, $compile_id_or);
        $this->isClean && ob_clean();
        $smarty->display($template, $cache_id_or, $compile_id_or, $parent);
        // $this->isExit && exit;
    }
    public function fetch($template=null, $cache_id_or=null, $compile_id_or=null, $parent=null)
    {
        $this->_assign_common_variable();
        $smarty   = $this->LoadSmarty();
        if(is_array($cache_id_or)){ //变量
            $this->assigns($cache_id_or);
            $cache_id_or = null;
        }
        $template = $this->_trim_template($template, $compile_id_or);
        $this->isClean && ob_clean();
        return $smarty->fetch($template, $cache_id_or, $compile_id_or, $parent);
    }
    public function isCached($template=null, $cache_id=null, $expire=3, $xArr=array())
    {
        $smarty     = $this->LoadSmarty();
        $template   = $this->_trim_template($template);
        $compile_id = isset($xArr['compile_id'])?$xArr['compile_id']:null;
        $parent     = isset($xArr['parent'])?$xArr['parent']:null;
        return $smarty->isCached($template, $cache_id, $expire, $compile_id, $parent);
    }
    /*
    * desc: 赋值常用变量到smarty
    *
    */
    private $_is_assigned_common_variable = false;
    private function _assign_common_variable()
    {
        if($this->_is_assigned_common_variable) return;
        $session = $this->getSession();
        $sessArr = $session->all();
        if(is_array($sessArr)){
            foreach($sessArr as $k=>$v){
                $this->assign($k, $v);
            }
        }
        
        // list($top_column, $sec_column) = $this->getColumn();
        $route = $this->getAppRoute('');
        $columns = array_slice(explode('/', $route), 0, 2);
        list($top_column, $sec_column) = array_slice(array_merge($columns, array(null,null)), 0, 2);
        $this->assign('route',      $route);
        $this->assign('top_column', $top_column);
        $this->assign('sec_column', $sec_column);
        $this->assign('controller', $this->getController());
        $this->assign('action',     $this->getAction());
        $this->_is_assigned_common_variable = true;
    }
    /*****************************end smarty******************************/
};
