<?php
$base = dirname(__DIR__);
$dir = $base.'/include';
define('SPACE_4', str_repeat(' ', 4));
define('SPACE_8', SPACE_4 . SPACE_4);
define('DELIMITER', '/*generator*/');

$src = file_get_contents($dir . '/phpx.h');
$r = preg_match('/\#define\s+PHPX_MAX_ARGC\s+(\d+)/', $src, $match);
if (!$r)
{
    exit("no PHPX_MAX_ARGC\n");
}

$maxArgc = $match[1];

//生成函数执行代码
$out = '';
$code = '';

for ($i = 1; $i <= $maxArgc; $i++)
{
	$func_define = 'Variant exec(const char *func, ';
    $list = [];
    for ($j = 1; $j <= $i; $j++)
    {
        $list[] = 'const Variant &v' . $j;
    }
    $func_define.= implode(', ', $list);
    $func_define.= ")";
    
    $out .= "\nextern " . $func_define .";";
    
	$code .= "\n" . $func_define;
	$code.="\n{\n";
	$code.= SPACE_4 . "Variant _func(func);\n" . SPACE_4 . "Args args;\n";
    for ($j = 1; $j <= $i; $j++)
    {
    	$code.= SPACE_4 . "args.append(const_cast<Variant &>(v" . ($j) . ").ptr());\n";
    }
    $code.= SPACE_4 . "return _call(NULL, _func.ptr(), args);\n}\n";
}

$parts = explode(DELIMITER, file_get_contents($base. '/src/exec.cc'));
file_put_contents($base.'/src/exec.cc', implode(DELIMITER, [
	$parts[0],
	$code,
	$parts[2],
]));

$exec_function_code = $out."\n";

//生成对象方法执行代码
$out = '';
$code = '';

for ($i = 1; $i <= $maxArgc; $i++)
{
	$out .= "\n".SPACE_4 . 'Variant exec(const char *func, ';
    $list = [];
    for ($j = 1; $j <= $i; $j++)
    {
        $list[] = 'const Variant &v' . $j;
    }
    $out.= implode(', ', $list).");";
    
    $code .= "\nVariant Object::exec(const char *func, ";
    $list = [];
    for ($j = 1; $j <= $i; $j++)
    {
    	$list[] = 'const Variant &v' . $j;
    }
    $code.= implode(', ', $list).")\n{\n";
    $code.= SPACE_4 . "Variant _func(func);\n" . SPACE_4. "Args args;\n";
    for ($j = 1; $j <= $i; $j++)
    {
    	$code.= SPACE_4. "args.append(const_cast<Variant &>(v" . ($j) . ").ptr());\n";
    }
    $code.= SPACE_4. "return _call(ptr(), _func.ptr(), args);\n}\n";
}

$parts = explode('/*generator-1*/', file_get_contents($base. '/src/object.cc'));
file_put_contents($base.'/src/object.cc', implode('/*generator-1*/', [
		$parts[0],
		$code,
		$parts[2],
]));
$exec_method_code = $out."\n".SPACE_4;

//生成对象创建代码
$out = '';
$code = '';

for ($i = 1; $i <= $maxArgc; $i++)
{
	$func_define = "Object newObject(const char *name, ";
    $list = [];
    for ($j = 1; $j <= $i; $j++)
    {
        $list[] = 'const Variant &v' . $j;
    }
    $func_define .= implode(', ', $list).")";
    
    $out .= "\nextern ".$func_define.";";

    $code.= "\n".$func_define."\n{\n";
    $code.= <<<CODE
    Object object;
    zend_class_entry *ce = getClassEntry(name);
    if (ce == NULL)
    {
        error(E_WARNING, "class '%s' is undefined.", name);
        return object;
    }
    if (object_init_ex(object.ptr(), ce) == FAILURE)
    {
        return object;
    }
    Args args;\n
CODE;
    for ($j = 1; $j <= $i; $j++)
    {
    	//$code.= SPACE_4 . "v" . ($j) . ".addRef();\n";
    	$code.= SPACE_4 . "args.append(const_cast<Variant &>(v" . ($j) . ").ptr());\n";
    }
    //$out .= SPACE_4 . "object.addRef();\n";
    $code.= SPACE_4 . "object.call(\"__construct\", args);\n";
    $code.= SPACE_4 . "return object;\n}\n";
}
$parts = explode(DELIMITER, file_get_contents($base. '/src/object.cc'));
file_put_contents($base.'/src/object.cc', implode(DELIMITER, [
		$parts[0],
		$code,
		$parts[2],
]));
$new_object_code = $out."\n";

$parts = explode(DELIMITER, $src);

$src = implode(DELIMITER, [
        $parts[0],
        $exec_function_code,
        $parts[2],
        $exec_method_code,
        $parts[4],
        $new_object_code,
        $parts[6],
    ]);
file_put_contents($dir . '/phpx.h', $src);