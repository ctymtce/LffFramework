<?php
/**
 * desc: 一系列的数学函数
 *
*/
class CMath {

    public static function crcU64($val)
    {
        $hex64 = sprintf("0x%s%s", hash('crc32',$val), hash('crc32b',$val));
        return self::bcHexdec($hex64);
    }
    public static function bcHexdec($hex)
    {
        if(strlen($hex) == 1) {
            return hexdec($hex);
        } else {
            $remain = substr($hex, 0, -1);
            $last = substr($hex, -1);
            return bcadd(bcmul(16, self::bcHexdec($remain)), hexdec($last));
        }
    }
    public static function bcDechex($dec)
    {
        $last = bcmod($dec, 16);
        $remain = bcdiv(bcsub($dec, $last), 16);

        if($remain == 0) {
            return dechex($last);
        } else {
            return self::bcDechex($remain).dechex($last);
        }
    }
    /*
    * desc: (-∞,100]->100,[101,200]->200,...,(1001,2000)->2000,....
    *
    *@min --- return minimal value 
    *
    */
    public static function UpInteger($val, $min=100)
    {
        $val = floatval($val);
        if($val < $min) return $min;
        $exp = strlen($val) - 1;
        $mul = pow(10, $exp);
        $int = ceil($val/$mul) * $mul;
        return max($int, $min);
    }
    /**
    * 取得离val最近的,比val大的且能被10或100...整除的那个数
    * @param int $val
    * @param bool $isfive,表示是否以50为模(eg.
    *  (50,100,150...)
    */
    static function UpTrimUnit($val, $isfive=true)
    {
        //取得离val最近的,比val大的且能被10或100...整除的那个数
        $len = strlen(ceil($val));
        $val = ceil($val/(pow(10,$len-1))) * pow(10,$len-1);
        $val = ($val < 10)?10:$val;
        if($isfive) {
            $fiveMod = 5*pow(10,$len-1);
            //echo "fiveMod:$fiveMod($len,$val); <br/>";
            if($val%$fiveMod > 0){
                $val = $val + ($fiveMod - ($val%$fiveMod));
            }
        }
        return $val;
    }
    static function DownTrimUnit($val, $isfive=true)
    {
        //取得离val最近的,比val小的且能被10或100...整除的那个数
        $val = floatval($val);
        $mutiple = 1; //放大的倍数
        if($val < 100){
            do{
                $mutiple *= 10;
                $val *= 10; //放大10,100...倍再计算
            }while($val < 100);
        }

        $len = strlen(ceil($val));
        $high = $val/(pow(10,$len-1)); //[1,10]
        $high = floor($high);
        if($isfive){
            $high -= $high%5 ;
            $high = max(1, $high);
        }
        $val = $high * pow(10,$len-1);
        $val = ($val < 10)?10:$val;
        return $val /= $mutiple;
    }
    /**
    * 将一个数人性化切分成多个数字
    * eg: 1000->array(300,300,400)
    * @param int $val,被切分的数
    * @param int $num,切分成个数
    *
    *return array
    */
    static function HumanizeSplit($val, $num=3, $sorter=1, $index=0)
    {
        $val = floatval($val);
        $avg = $val / $num;

        $hAvg = self::DownTrimUnit($avg, false);
        // echo "avg=$avg,hAvg=$hAvg\n";
        $splits = array_fill($index, $num-1, $hAvg);
        $splits[] = $val - array_sum($splits);

        if(-1 == $sorter){
            rsort($splits); //倒序
            $tArr = $splits;
            $splits = array();
            foreach($tArr as $v){//保持索引
                $splits[$index++] = $v;
            }
        }

        return $splits;
    }
};