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
    protected $vfile   = null; //view file path
    protected $smarty  = null;

    public function setVfile($vfile)
    {
        $this->vfile = $vfile;
    }
    public function renderFile($file, $paraArr=array())
    {
        if(!is_file($file)) return '';
        ob_clean();
        ob_start();
        ob_implicit_flush(false); //don't flush
        if(is_array($paraArr) && count($paraArr)>0) {
            extract($paraArr, EXTR_OVERWRITE);
        }
        require($file);
        return ob_get_clean();
    }
    public function render($vfile=null, $dataArr=array())
    {
        $App = $this->getCaller();
        if(!is_array($dataArr)) $dataArr = array();
        $dataArr = array_merge(array(
                'HOME'       => $App->home,
                'ROOT'       => $App->boot,
                'BOOT'       => $App->boot,
                'ASSETS_LOC' => $App->AssetsLoc,
                'ASSETS_URL' => $App->AssetsUrl,
                'STATIC_LOC' => $App->AssetsLoc.'/static',
                'STATIC_URL' => $App->AssetsUrl.'/static',
                'UPLOAD_LOC' => $App->AssetsLoc.'/static/upload',
                'UPLOAD_URL' => $App->AssetsUrl.'/static/upload',
                'UI_URL'     => $App->UiUrl,
                'UI_LOC'     => $App->UiLoc,
            ), $dataArr
        );

        if(null === $vfile)$vfile = $this->vfile;
        $vfile = $App->ViewLoc.'/'. $this->getController().'/'.$vfile . '.php';
        // echo $this->vfile;
        // print_r($App);
        // exit($vfile);
        if(!is_file($vfile)) {
            return $this->httpError(404, 'The view file does not exists!');
        }
        //1, get view file contents
        $viewContent = $this->renderFile($vfile, $dataArr);
        //2, get layout file contents
        $layFile = $App->ViewLoc . '/layout/'. $this->layout . '.php';
        $dataArr['content'] = $viewContent;
        echo $this->renderFile($layFile, $dataArr);
    }
    /*********************************smarty******************************/
    public function LoadSmarty()
    {
        if(null === $this->smarty) {
            $App = $this->getCaller();
            $tpl_base_dir = $App->TPL_UI;
            $tpl_cpl_dir  = $App->getConfig('scpdir');
            $this->smarty = new CSmarty($tpl_base_dir, $tpl_cpl_dir);
            /*$configArr = $App->getConfig();
            foreach($configArr as $_k => $_v){
                $ascii = ord($_k);
                if($ascii >= 65 && $ascii <= 90){
                    $this->smarty->assign($_k,  $_v);
                }
            }*/
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
            $this->smarty->assign('TPL_LOC',    $App->TPL_LOC);
            $this->smarty->assign('TPL_COM',    $App->TPL_LOC.'/common');
            $this->smarty->assign('TPL_INC',    $App->TPL_UI.'/templates/include');
            $this->smarty->assign('TPL_ROOT',   $App->TPL_UI.'/templates');
            $this->smarty->assign('TPL_CACHE',  $App->TPL_UI.'/cache');
            $this->smarty->assign('TPL_LAYOUT', $App->TPL_UI.'/templates/layout');
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
