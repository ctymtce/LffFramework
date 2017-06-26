<?php
/**
 * Smarty plugin
 * 
 */

function smarty_modifier_ext($str)
{
    return substr($str, strrpos($str, '.')+1);
} 
