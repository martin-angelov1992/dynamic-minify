<?php
function endc( $array ) { return end( $array ); }
function getNewName($last_name)
{
    global $charMap;
    $last_char = substr($last_name,-1);
    $key = array_search($last_char, $charMap);
    $last_in_arr = end($charMap);
    if($last_in_arr != $last_char)
        return substr($last_name, 0, -1).$charMap[$key+1];
    else
        return $last_name.$charMap[0];
}
?>