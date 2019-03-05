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
};