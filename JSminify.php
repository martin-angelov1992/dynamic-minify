<?php
class JSminify
{
    private $parts;
    private $context = array();
    private $global;
    private $globals_only;
    private $scope_vars = array();
    public function __construct($parts, $global, $globals_only)
    {
        $this->globals_only = $globals_only;
        $this->global = $global;
        $this->parts = $parts;
        $this->context[] = "GLOBAL";
        $this->scope_vars[] = $global;
        foreach($parts as $key => $part)
        {
            if(!is_array($part))
                $this->parts[$key] = $this->minifyPart($part);
            else
                $this->parts[$key][1] = $this->minifyQuote($part[1], $key);
        }
    }
    public function getGlobals()
    {
        return $this->global;
    }
    private function getScopeNum()
    {
        $scope_num = 0;
        foreach($this->context as $single_context)
            if($single_context == "FUNCTION_CONTENT" || $single_context == "FUNCTION_PARAMS")
                ++$scope_num;
        return $scope_num;
    }
    private function getAvailibleName()
    {
        for($i=$this->getScopeNum(); $i>=0; --$i)
        {
            if(sizeof($this->scope_vars[$i]))
            {
                return getNewName(endc($this->scope_vars[$i]));
            }
        }
        return "a";
    }
    private function getName($name)
    {
        foreach($this->scope_vars as $arr)
        {
            if(isset($arr[$name]))
                return $arr[$name];
        }
        return false;
    }
    private function minifyQuote($quote, $key)
    {
        $minify = "";
        for ($i=0; $i<strlen($quote); $i++) 
        {
            $minify .= $quote[$i];
            if($this->context[sizeof($this->context)-1] == "EVAL" && preg_match('/(^|\W)\w+\W$/', $minify, $matches))
            {
                preg_match('/\w+/', $matches[0], $matches2);
                preg_match('/\W$/', $matches[0], $match_symbol);
                if(($this->context[sizeof($this->context)-1] == "GLOBAL" && $this->globals_only) || (!$this->globals_only && $this->context[sizeof($this->context)-1] != "GLOBAL") && $name = $this->getName($matches2[0]))
                {
                    $minify = preg_replace('/\w+\W$/', $name.$match_symbol[0], $minify);
                }
            }
        }
        return $minify;
    }
    private function minifyPart($part)
    {
        $minify = "";
        for ($i=0; $i<strlen($part); $i++) 
        {
            $minify .= $part[$i];
            if(preg_match('/\/script>$/', $minify, $matches))
                continue;
            if(!preg_match('/\w$/', $minify) && $this->context[sizeof($this->context)-1] == "JS_QUOTES_EXTERNAL")
                array_pop($this->context);
            if(preg_match('/(\W|^)function\s*\w*\s*\($/', $minify))
            {
                $this->context[] = "FUNCTION_PARAMS";
                $this->scope_vars[$this->getScopeNum()] = array();
            }
            else if(preg_match('/(\W|^)\w+\s*\($/', $minify, $matches))
            {
                $this->context[] = "FUNCTION_CALLING";
                if(preg_match('/\Weval\s*\($/', $minify))
                    $this->context[] = "EVAL";
                else if(preg_match('/\Wfor\s*\($/', $minify))
                    $this->context[] = "LOOP";
            }
            if($part[$i] == ")")
            {
                if($this->context[sizeof($this->context)-1] == "FUNCTION_CALLING")
                {
                    array_pop($this->context);
                    if($this->context[sizeof($this->context)-1] == "EVAL" || $this->context[sizeof($this->context)-1] == "LOOP")
                        array_pop($this->context);
                }
            }
            if($part[$i] == "{")
            {
                if($this->context[sizeof($this->context)-1] == "FUNCTION_PARAMS")
                {
                    array_pop($this->context);
                    $this->context[] = "FUNCTION_CONTENT";
                }
                $this->context[] = "PARENTHES";
            }
            if($part[$i] == "}")
            {
                array_pop($this->context);
                if($this->context[sizeof($this->context)-1] == "FUNCTION_CONTENT")
                {
                    unset($this->scope_vars[$this->getScopeNum()]);
                    array_pop($this->context);
                }
            }
            if($part[$i] == "/" && $this->context[sizeof($this->context)-1] != "SQUARE_BRACKETS")
            {
                if($this->context[sizeof($this->context)-1] == "JS_QUOTES")
                {
                    array_pop($this->context);
                    if(preg_match('/\w/', $part[$i+1]))
                        $this->context[] = "JS_QUOTES_EXTERNAL";
                }
                else
                    $this->context[] = "JS_QUOTES";
            }
            if($part[$i] == "[")
            {
                $this->context[] = "SQUARE_BRACKETS";
            }
            if($part[$i] == "]")
            {
                array_pop($this->context);
            }
            if($this->context[sizeof($this->context)-1] != "JS_QUOTES" && $this->context[sizeof($this->context)-1] != "JS_QUOTES_EXTERNAL")
            {
                if(preg_match('/(?<=var)\s+\w+\W$/', $minify, $matches))
                {
                    preg_match('/\w+/', $matches[0], $matches2);
                    $availible_name = $this->getAvailibleName();
                    preg_match('/\W$/', $minify, $match_symbol);
                    if(($this->context[sizeof($this->context)-1] == "GLOBAL" && $this->globals_only) ||
                    (!$this->globals_only && $this->context[sizeof($this->context)-1] != "GLOBAL"))
                    {
                        $minify = preg_replace('/\w+\W$/', $availible_name.$match_symbol[0], $minify);
                        $this->scope_vars[$this->getScopeNum()][$matches2[0]] = $availible_name;
                    }
                    if($this->globals_only && $this->context[sizeof($this->context)-1] == "GLOBAL")
                        $this->global[$matches2[0]] = $availible_name;
                }
                else if(!$this->globals_only && preg_match('/\W\w+\W$/', $minify, $matches) && !is_numeric(substr($matches[0], 1, strlen($matches[0])-2)) && $matches[0][0] != "." && $matches[0][strlen($matches[0])-1] != "(") // Got variable, not a array element and not a function and not an array key and previous declared
                {
                    $rest = substr($part, $i+1, strlen($part));
                    // It's an array key and not a variable
                    if(preg_match('/^\s*:/',$rest) || $matches[0][strlen($matches[0])-1] == ":")
                        continue;
                    if($name = $this->getName(substr($matches[0], 1, strlen($matches[0])-2)))
                        $minify = preg_replace('/\W\w+\W$/', $matches[0][0].$name.$matches[0][strlen($matches[0])-1], $minify);
                    if(preg_match('/\sin\s/', $matches[0]) && $this->context[sizeof($this->context)-1] == "LOOP")
                    {
                        preg_match('/\w+\s+in\s$/', $minify, $matches2);
                        preg_match('/\w+/', $matches2[0], $matches3);
                        $availible_name = $this->getAvailibleName();
                        $this->scope_vars[$this->getScopeNum()][$matches3[0]] = $availible_name;
                        $minify = preg_replace('/\w+\s+in\s$/', $availible_name." in ", $minify);
                    }
                    if($this->context[sizeof($this->context)-1] == "FUNCTION_PARAMS")
                    {
                        $availible_name = $this->getAvailibleName();
                        $cur_name = substr($matches[0], 1, strlen($matches[0])-2);
                        $this->scope_vars[$this->getScopeNum()][$cur_name] = $availible_name;
                        $minify = preg_replace('/\W\w+\W$/', $matches[0][0].$availible_name.$matches[0][strlen($matches[0])-1], $minify);
                    }
                }
            }
        }
        return $minify;
    }
    public function getAll()
    {
        return $this->parts;
    }
}
?>