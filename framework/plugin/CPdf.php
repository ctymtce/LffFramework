<?php
if(!isset($GLOBALS['CPdf.PDF_Chinese'])){
    $GLOBALS['CPdf.PDF_Chinese'] = 1;
    require(__DIR__ . '/pdf/chinese.php');
}
class CPdf extends PDF_Chinese {
    var $B;
    var $I;
    var $U;
    var $HREF;

    function __construct($orientation='P',$unit='mm',$format='A4')
    {
        //Call parent constructor
        $this->FPDF($orientation,$unit,$format);
        //Initialization
        $this->B=0;
        $this->I=0;
        $this->U=0;
        $this->HREF='';
    }

    function WriteHTML($html)
    {
        //HTML parser
        $html=str_replace("\n",' ',$html);
        $a=preg_split('/<(.*)>/U',$html,-1,PREG_SPLIT_DELIM_CAPTURE);
        foreach($a as $i=>$e){
            if($i%2==0){
                //Text
                if($this->HREF)
                    $this->PutLink($this->HREF,$e);
                else
                    $this->Write(10,$e);
            }else{
                //Tag
                if($e{0}=='/')
                    $this->CloseTag(strtoupper(substr($e,1)));
                else{
                    //Extract attributes
                    $a2=explode(' ',$e);
                    $tag=strtoupper(array_shift($a2));
                    $attr=array();
                    foreach($a2 as $v)
                        if(preg_match('/^([^=]*)=["\']?([^"\']*)["\']?$/',$v,$a3))
                            $attr[strtoupper($a3[1])]=$a3[2];
                    $this->OpenTag($tag,$attr);
                }
            }
        }
    }

    function OpenTag($tag,$attr)
    {
        //Opening tag
        if($tag=='B' or $tag=='I' or $tag=='U')
            $this->SetStyle($tag,true);
        if($tag=='A')
            $this->HREF=$attr['HREF'];
        if($tag=='BR')
            $this->Ln(5);
    }

    function CloseTag($tag)
    {
        //Closing tag
        if($tag=='B' or $tag=='I' or $tag=='U')
            $this->SetStyle($tag,false);
        if($tag=='A')
            $this->HREF='';
    }

    function SetStyle($tag,$enable)
    {
        //Modify style and select corresponding font
        $this->$tag+=($enable ? 1 : -1);
        $style='';
        foreach(array('B','I','U') as $s)
            if($this->$s>0)
                $style.=$s;
        $this->SetFont('',$style);
    }

    function PutLink($URL,$txt)
    {
        //Put a hyperlink
        $this->SetTextColor(0,0,255);
        $this->SetStyle('U',true);
        $this->Write(5,$txt,$URL);
        $this->SetStyle('U',false);
        $this->SetTextColor(0);
    }
};
/*
$pdf=new CPdf();

$pdf->AddGBFont('simsun','宋体');
$pdf->AddGBFont('simhei','黑体');
$pdf->AddGBFont('simkai','楷体_GB2312');
$pdf->AddGBFont('sinfang','仿宋_GB2312');

$pdf->Open();
$pdf->AddPage();
$pdf->SetFont('simsun','',20);
$pdf->Write(10,'简体中文汉字');
$pdf->SetFont('simhei','',20);
$pdf->Write(10,'简体中文汉字');
$pdf->SetFont('simkai','',20);
$pdf->Write(10,"简体中文汉字\n");
$pdf->SetFont('sinfang','',20);
$pdf->SetTextColor(255,0,255);
$pdf->Write(10,"============简体中文汉字
                简体中文汉字
                简体中文汉字
            ");
// $pdf->Image("E:/pic/Jellyfish/Jellyfish No.310_02.jpg",null,null,110,180);

$link=$pdf->AddLink();

$pdf->Write(5,'here',$link);
$pdf->WriteHTML("<a href='http://www.baidu.com'>link</a>");

$pdf->Output();
*/