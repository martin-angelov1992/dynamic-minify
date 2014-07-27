<?php
include "define.php";
include "functions.php";
include "JSminify.php";
class Minify
{
    /**
     *
     * Minify JS code
     *
     * @param string $text the unminified string
     * @return string the minified string
     *
     */
    public static function JS($text)
    {
        return preg_replace('~\s+~', // Replace more spaces with a single space if ?\> in new line is not ahead
            ' ', preg_replace('/(((?<=[[:punct:]])\s+(?=[[:punct:]]))|'. //If space between 2 punctuations
            '((?<=\w)\s+(?=[[:punct:]]))|'. //If space after literal and before punctuation
            '((?<=[[:punct:]])\s+(?=\w))|(^\s+))/s', //If space after punctuation and before literal
            "", $text));
    }
    /**
     *
     * Minify HTML code
     *
     * @param string $text the unminified string
     * @return string the minified string
     *
     */
    public static function HTML($text)
    {
        return preg_replace('~(\s*\n\s*)~', "", $text);
    }
    /**
     *
     * Minify Mixed HTML/JavaScript code
     *
     * @param string $text the unminified string
     * @return string the minified string
     *
     */
    public static function mixed($text, $context = "html", $global=array(), $globals_only = false)
    {
        global $charMap;
        $current = "";
        $context = array($context);
        $read = array();
        for ($i=0; $i<strlen($text); $i++) 
        {
            $current .= $text[$i];
            if(substr($current, -strlen("<script>")) == "<script>" || substr($current, -strlen('<script ')) == '<script ')
            {
                $read[] = array($context[sizeof($context)-1], $current);
                $context[] = "JS";
                $current = "";
            }
            if(substr($current, -strlen("</script>")) == "</script>")
            {
                if(sizeof($context) && $context[sizeof($context)-1] == "JS")
                {
                    array_pop($context);
                    $read[] = array("JS", $current);
                    $current = "";
                }
            }
            if(substr($current, -strlen("<?")) == "<?")
            {
                $read[] = array($context[sizeof($context)-1], $current);
                $context[] = "PHP";
                $current = "";
            }
            if(substr($current, -strlen("?>")) == "?>")
            {
                array_pop($context);
                $read[] = array("PHP", $current);
                $current = "";
            }
            if(substr($current, -strlen("'")) == "'" && $context[sizeof($context)-1] != "PHP" && $context[sizeof($context)-1] != "DOUBLE_QUOTES") //not in php context
            {
                if($context[sizeof($context)-1] == "SINGLE_QUOTES") // getting out of SINGLE_QUOTES context
                {
                    array_pop($context);
                    $read[] = array("SINGLE_QUOTES", $current);
                }
                else // getting into SINGLE_QUOTES context
                {
                    $read[] = array($context[sizeof($context)-1], $current);
                    $context[] = "SINGLE_QUOTES";
                }
                $current = "";
            }
            if(substr($current, -strlen('"')) == '"' && $context[sizeof($context)-1] != "PHP" && $context[sizeof($context)-1] != "SINGLE_QUOTES")
            {
                if($context[sizeof($context)-1] == "DOUBLE_QUOTES")
                {
                    array_pop($context);
                    $read[] = array("DOUBLE_QUOTES", $current);
                }
                else
                {
                    $read[] = array($context[sizeof($context)-1], $current);
                    $context[] = "DOUBLE_QUOTES";
                }
                $current = "";
            }
        }
        if($current)
            $read[] = array($context[0], $current);
        $ret = "";
        $js_codes = array();
        foreach($read as $key => $code)
        {
            if($code[0] == "JS")
                $js_codes[] = $code[1];
            else if(($code[0] == "SINGLE_QUOTES" || $code[0] == "DOUBLE_QUOTES") && 
            isset($read[$key-1]) && isset($read[$key+1]) && $read[$key-1][0] == "JS" && $read[$key+1][0] == "JS")
                $js_codes[] = array($code[0], $code[1]);
        }
        $js_minify = new JSminify($js_codes, $global, $globals_only);
        $js_minified = $js_minify->getAll();

        foreach($read as $key => $code)
        {
            if($code[0] == "JS")
            {
                $read[$key] = array("JS", $js_minified[0]);
                array_shift($js_minified);
            }
            else if(($code[0] == "SINGLE_QUOTES" || $code[0] == "DOUBLE_QUOTES") && 
            isset($read[$key-1]) && isset($read[$key+1]) && $read[$key-1][0] == "JS" && $read[$key+1][0] == "JS")
            {
                $read[$key] = $js_minified[0];
                array_shift($js_minified);
            }
        }

        foreach($read as $code)
        {
            if(method_exists('Minify',$code[0]))
                $ret .= self::$code[0]($code[1]); //calling JS or HTML function
            else
                $ret .= $code[1];
        }

        if($globals_only)
            return array("globals" => $js_minify->getGlobals(), "content" => $ret);
        else
            return $ret;
    }
}
?>