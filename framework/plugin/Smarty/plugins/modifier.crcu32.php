<?php
/**
 * Smarty plugin
 * 
 */

function smarty_modifier_crcu32($str)
{
    return sprintf("%u", crc32($str));
} 
