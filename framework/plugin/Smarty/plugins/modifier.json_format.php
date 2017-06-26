<?php
/**
 * Smarty plugin
 * Author: cty@20140529
 * json格式化
 * @author Author: cty@20140529
 * @param  str|arr $json     输入
 * @param  string $indentStr 缩进字符
 *return: string
*/
function smarty_modifier_json_format($json, $indentStr='    ', $isplain=true)
{
    if(is_array($json)) {
        $json = json_encode($json);
    }
    $result = '';
    $pos = 0;
    $strLen = strlen($json);
    // $indentStr = "    ";
    $newLine = "\n";
    $prevChar = '';
    $outOfQuotes = true;
    for($i = 0; $i <= $strLen; $i++) {
        // Grab the next character in the string.
        $char = substr($json, $i, 1);
        // Are we inside a quoted string?
        if ($char == '"' && $prevChar != '\\') {
            $outOfQuotes = !$outOfQuotes;
            // If this character is the end of an element,
            // output a new line and indent the next line.
            
        } else if (($char == '}' || $char == ']') && $outOfQuotes) {
            $result.= $newLine;
            $pos--;
            for ($j = 0; $j < $pos; $j++) {
                $result.= $indentStr;
            }
        }
        // Add the character to the result string.
        $result.= $char;
        // If the last character was the beginning of an element,
        // output a new line and indent the next line.
        if(($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
            $result.= $newLine;
            if ($char == '{' || $char == '[') {
                $pos++;
            }
            for ($j = 0; $j < $pos; $j++) {
                $result.= $indentStr;
            }
        }
        $prevChar = $char;
    }

    if($isplain){
        $matchs = preg_match_all("/\\\u[0-9a-f]{4}/si", $result, $pArr);
        if($pArr && isset($pArr[0])){
            foreach($pArr[0] as $char_json){
                $char_plain = json_decode('"'.$char_json.'"');
                if($char_plain)$result = str_replace($char_json, $char_plain, $result);
            }
        }
    }
    return $result;
} 

