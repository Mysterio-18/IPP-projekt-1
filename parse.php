<?php
ini_set('display_errors', 'stderr');
$xml = new DOMDocument('1.0', "UTF-8");
$xml->formatOutput = true;

$comments = 0;
$ret = main($argc, $argv);
exit($ret);

/**
 * Základní fce programu, obstarává hlavní smyčku konečního automatu
 */
function main($argc, $argv)
{
    $order = 1;

    $statp = check_args($argc, $argv, $pars, $file);
    $loc = 0;
    $new_label = false;
    $labels = array();
    $jumps = 0;
    $xml_root = init_xml();
    global $xml;

    $instr_elem = null;
    $string = "";
    $state = 0;
    $new_line = false;
    scan_first_line();
    while(1)
    {
        switch ($state)
        {
            case 0:
                $state = read_new_line($string);
                break;
            case 1:
                $new_line = read_word($string);
                $state = find_instruction($string, $new_label, $jumps);
                $loc++;
                if($state != 3 && $new_line)
                {
                    fwrite(STDERR, "Špatný počet operandů");
                    exit(23);
                }
                $instr_elem = xml_add_instr($xml_root, $order, $string);
                break;
            case 2:
                $new_line = parse_var($instr_elem, 1);
                if($new_line)
                {
                    fwrite(STDERR, "Špatný počet operandů");
                    exit(23);
                }
                $new_line = parse_symb($instr_elem, 2);
                if($new_line)
                    $state = 0;
                else
                    $state = read_rest_of_line();
                break;
            case 3:
                if($new_line)
                    $state = 0;
                else
                    $state = read_rest_of_line();
                break;
            case 4:
                $new_line = parse_label($instr_elem, 1, $new_label, $labels);
                $new_label = false;
                if($new_line)
                    $state = 0;
                else
                    $state = read_rest_of_line();
                break;
            case 5:
                $new_line = parse_var($instr_elem, 1);
                if($new_line)
                    $state = 0;
                else
                    $state = read_rest_of_line();
                break;
            case 6:
                $new_line = parse_symb($instr_elem, 1);
                if($new_line)
                    $state = 0;
                else
                    $state = read_rest_of_line();
                break;
            case 7:
                $new_line = parse_var($instr_elem, 1);
                if($new_line)
                {
                    fwrite(STDERR, "Špatný počet operandů");
                    exit(23);
                }
                $new_line = parse_symb($instr_elem, 2);
                if($new_line)
                {
                    fwrite(STDERR, "Špatný počet operandů");
                    exit(23);
                }
                $new_line = parse_symb($instr_elem, 3);
                if($new_line)
                    $state = 0;
                else
                    $state = read_rest_of_line();
                break;
            case 8:
                $new_line = parse_var($instr_elem, 1);
                if($new_line)
                {
                    fwrite(STDERR, "Špatný počet operandů");
                    exit(23);
                }
                $new_line = parse_type($instr_elem, 2);
                if($new_line)
                    $state = 0;
                else
                    $state = read_rest_of_line();
                break;
            case 9:
                $new_line = parse_label($instr_elem, 1, false, $labels);
                if($new_line)
                {
                    fwrite(STDERR, "Špatný počet operandů");
                    exit(23);
                }
                $new_line = parse_symb($instr_elem, 2);
                if($new_line)
                {
                    fwrite(STDERR, "Špatný počet operandů");
                    exit(23);
                }
                $new_line = parse_symb($instr_elem, 3);
                if($new_line)
                    $state = 0;
                else
                    $state = read_rest_of_line();
                break;
            case -1:
                echo $xml->saveXML();
                if($statp)
                    write_statistics($file, $pars, $loc, $labels, $jumps);
                return 0;
        }
    }

    return 0;
}

/**
 * Funkce kontroluje přítomnost a správnost povinné hlavičky
 */
function scan_first_line()
{
    global $comments;
    $line = fgets(STDIN);
    $line_array = str_split($line);


    foreach ($line_array as $char)
    {
        if(strcmp($char, " ") == 0 || strcmp($char, "\t") == 0)
            continue;
        elseif(strcmp($char, "#") == 0)
        {
            $comments++;
            scan_first_line();
            return;
        }
        else
            break;
    }
    if(preg_match('/^\s*(.IPPcode20)\s*(#.*)?$/',$line) == 1)
        return;
    else
    {
        fwrite(STDERR, "Chybějící/chybná hlavička");
        exit(21);
    }

}

/**
 * Funkce k přeskočení bílých znaků mezi jednotlivými částmi instrukce
 */
function eat_white_space()
{
    while(($char = fgetc(STDIN)) !== false)
    {
        if(strcmp($char, " ") == 0 || strcmp($char, "\t") == 0)
            continue;
        elseif(strcmp($char, "#") == 0 || strcmp($char, "\n") == 0)
        {
            fwrite(STDERR, "Neočekávaný komentář/konec řádku");
            exit(23);
        }
        else
            return $char;
    }
    exit(23);
}

/**
 * Funkce čte nové řádky dokud nenarazí na 1. písmeno
 * $instr nese nalezené písmeno
 * vrací -1 - pokud narazí na konec vstupu, 1 - pokud narazí na písmeno
 */
function read_new_line(&$instr)
{
    global $comments;
    while(($char = fgetc(STDIN)) !== false)
    {
        if(strcmp($char, " ") == 0 || strcmp($char, "\t") == 0 || strcmp($char, "\n") == 0)
            continue;
        elseif(strcmp($char, "#") == 0)
        {
            $comments++;
            fgets(STDIN);
            continue;
        }
        else
        {
            $instr = $char;
            return 1;
        }
    }
   return -1;
}

/**
 * Funkce přečte zbylé bílé znaky/komentáře na konci řádku
 * vrací -1 - pokud narazí na konec vstupu, 0 - pokud narazí na konec řádku
 */
function read_rest_of_line()
{
    global $comments;
    while(($char = fgetc(STDIN)) !== false)
    {
        if(strcmp($char, " ") == 0 || strcmp($char, "\t") == 0)
           continue;
        elseif(strcmp($char, "#") == 0)
        {
            $comments++;
            fgets(STDIN);
            return 0;
        }
        elseif(strcmp($char, "\n") == 0)
            return 0;
        else
        {
            fwrite(STDERR, "Chybný počet operanů");
            exit(23);
        }
    }
    return -1;
}

/**
 * Funkce čte znaky, dokud nenarazí na konec slova (bílý znak/komentář)
 *  $instr přečtené slovo
 * návratová hodnota značí nalezení konce řádku
 */
function read_word(&$instr)
{
    global $comments;
    while(($char = fgetc(STDIN)) !== false)
    {
        if(strcmp($char, " ") == 0 || strcmp($char, "\t") == 0)
            return false;
        else if(strcmp($char, "\n") == 0)
            return true;
        else if(strcmp($char, "#") == 0)
        {
            $comments++;
            fgets(STDIN);
            return true;
        }
        else
            $instr .= $char;
    }
    return true;
}

/**
 * Funkce kontroluje správnost operandu VAR
 */
function check_var($var)
{
    if(preg_match('/^[GLT]F@[a-zA-Z_\-$&%*!?][\w\-$&%*!?]*$/', $var) != 1)
    {
        fwrite(STDERR, "Neplatná proměnná");
        exit(23);
    }
}

/**
 * Funkce kontroluje správnost operandu TYPE
 */
function check_type($type)
{
    if(strcmp($type, "int") == 0 || strcmp($type, "string") == 0 || strcmp($type, "bool") == 0)
        return;
    else
    {
        fwrite(STDERR, "Neplatný typ");
        exit(23);
    }
}

/**
 * Funkce kontroluje správnost operandu LABEL
 */
function check_label($label)
{
    if(preg_match('/^[a-zA-Z_\-$&%*!?][\w\-$&%*!?]*$/', $label) != 1)
    {
        fwrite(STDERR, "Neplatné návěští");
        exit(23);
    }
}

/**
 * Funkce kontroluje správnost operandu SYMB
 * symb - operand (pokud je symb konstanta -> samotná konstanta)
 * návratová hodnota - null pokud je SYMB proměnná, typ konstanty, pokud je SYMB konstanta
 */
function check_symb(&$symb)
{
    $first = substr($symb, 0 ,1);
    if(preg_match('/^[GLT]$/', $first) == 1)
    {
        check_var($symb);
        return null;
    }
    else
    {
        $array = preg_split('/@/', $symb, 2);
        if(strcmp($array[0], "nil") == 0)
        {
            if(strcmp($array[1],"nil") != 0)
            {
                fwrite(STDERR, "Neplatná konstanta typu nil");
                exit(23);
            }
        }
        elseif(strcmp($array[0], "bool") == 0)
        {
            if(strcmp($array[1], "true") != 0 && strcmp($array[1], "false") != 0)
            {
                fwrite(STDERR, "Neplatná konstanta typu bool");
                exit(23);
            }
        }
        elseif(strcmp($array[0], "string") == 0)
        {
            $str_arr = str_split($array[1]);
            for($i = 0; $i<count($str_arr); $i++)
            {
                if(strcmp($str_arr[$i], "\\") == 0)
                {
                    if(preg_match('/^\d{3}$/',$str_arr[$i+1] . $str_arr[$i+2] . $str_arr[$i+3]) != 1)
                    {
                        fwrite(STDERR, "Neplatný znak v typu string");
                        exit(23);
                    }
                    $i +=3;
                }
            }
        }
        elseif(strcmp($array[0], "int") == 0)
        {
            if(preg_match('/^[+\-]?\d+$/', $array[1]) != 1)
            {
                fwrite(STDERR, "Neplatný integer");
                exit(23);
            }
        }
        else
        {
            fwrite(STDERR, "Neznámý datový typ");
            exit(23);
        }
    }

    $symb = $array[1];
    return $array[0];
}

/**
 * Funkce hledá instrukci
 * návratová hodnota - dle počtu a druhu operandů nalezeného operačního kódu
 */
function find_instruction($string, &$new_label, &$jumps)
{
    if(strcasecmp($string, "move") == 0) //VAR SYMB
        return 2;
    elseif(strcasecmp($string, "int2char") == 0)
        return 2;
    elseif(strcasecmp($string, "strlen") == 0)
        return 2;
    elseif(strcasecmp($string, "type") == 0)
        return 2;
    elseif(strcasecmp($string, "not") == 0)
        return 2;

    elseif(strcasecmp($string, "createframe") == 0) //NIC
        return 3;
    elseif(strcasecmp($string, "pushframe") == 0)
        return 3;
    elseif(strcasecmp($string, "popframe") == 0)
        return 3;
    elseif(strcasecmp($string, "break") == 0)
        return 3;
    elseif(strcasecmp($string, "return") == 0)
    {
        $jumps++;
        return 3;
    }


    elseif(strcasecmp($string, "call") == 0) //LABEL
    {
        $jumps++;
        return 4;
    }
    elseif(strcasecmp($string, "label") == 0)
    {
        $new_label = true;
        return 4;
    }
    elseif(strcasecmp($string, "jump") == 0)
    {
        $jumps++;
        return 4;
    }


    elseif(strcasecmp($string, "defvar") == 0) //VAR
        return 5;
    elseif(strcasecmp($string, "pops") == 0)
        return 5;

    elseif(strcasecmp($string, "pushs") == 0) //SYMB
        return 6;
    elseif(strcasecmp($string, "write") == 0)
        return 6;
    elseif(strcasecmp($string, "exit") == 0)
        return 6;
    elseif(strcasecmp($string, "dprint") == 0)
        return 6;

    elseif(strcasecmp($string, "add") == 0) //VAR SYMB SYMB
        return 7;
    elseif(strcasecmp($string, "sub") == 0)
        return 7;
    elseif(strcasecmp($string, "mul") == 0)
        return 7;
    elseif(strcasecmp($string, "idiv") == 0)
        return 7;
    elseif(strcasecmp($string, "lt") == 0)
        return 7;
    elseif(strcasecmp($string, "gt") == 0)
        return 7;
    elseif(strcasecmp($string, "eq") == 0)
        return 7;
    elseif(strcasecmp($string, "and") == 0)
        return 7;
    elseif(strcasecmp($string, "or") == 0)
        return 7;
    elseif(strcasecmp($string, "stri2int") == 0)
        return 7;
    elseif(strcasecmp($string, "concat") == 0)
        return 7;
    elseif(strcasecmp($string, "getchar") == 0)
        return 7;
    elseif(strcasecmp($string, "setchar") == 0)
        return 7;


    elseif(strcasecmp($string, "read") == 0) //VAR TYPE
        return 8;

    elseif(strcasecmp($string, "jumpifeq") == 0) //LABEL SYMB SYMB
    {
        $jumps++;
        return 9;
    }
    elseif(strcasecmp($string, "jumpifneq") == 0)
    {
        $jumps++;
        return 9;
    }
    else
    {
        fwrite(STDERR, "Neznámý operační kód");
        exit(22);
    }
}

/**
 * Funkce kontroluje parametry
 */
function check_args($argc, $argv, &$pars, &$file)
{
    $found = false;
    $pars = array();
    if($argc == 1)
        return $found;
    foreach ($argv as $arr)
    {
        if(strcmp("--help", $arr) == 0)
        {
            if($argc > 2)
            {
                fwrite(STDERR, "Neplatná kombinace parametrů skriptu");
                exit(10);
            }
            else
            {
                echo "Skript typu filtr (parse.php v jazyce PHP 7.4) načte ze standardního vstupu zdrojový kód ";
                echo "v IPP-code20, zkontroluje lexikální a syntaktickou správnost kódu ";
                echo "a vypíše na standardní výstup XML reprezentaci programu.\n";
                exit(0);
            }
        }
        elseif(preg_match('/^--stats=.+$/', $arr) == 1)
        {
            if($found)
            {
                fwrite(STDERR, "Neplatná kombinace parametrů skriptu");
                exit(10);
            }
            else
                $found = true;

            $split = preg_split('/=/',$arr, 2);
            $file = $split[1];
            if(strcmp($file, "") == 0)
            {
                fwrite(STDERR, "Chybí file");
                exit(10);
            }
        }
        elseif(strcmp($arr, "--loc") == 0)
            $pars[] = $arr;
        elseif(strcmp($arr, "--comments") == 0)
            $pars[] = $arr;
        elseif(strcmp($arr, "--labels") == 0)
            $pars[] = $arr;
        elseif(strcmp($arr, "--jumps") == 0)
            $pars[] = $arr;
    }

    if(count($pars) > 0 && !$found)
    {
        fwrite(STDERR, "Neplatná kombinace parametrů skriptu");
        exit(10);
    }
    return $found;
}

/**
 * Funkce se zbaví možných bílých znaků a za nimi očekává VAR, kterou pomocí dalších fcí zpracuje
 */
function parse_var($instr_elem, $arg_order)
{
    $string = eat_white_space();
    $new_line = read_word($string);
    check_var($string);
    $string = htmlspecialchars($string, ENT_QUOTES);
    xml_add_arg($instr_elem, $arg_order, "var", $string);

    return $new_line;
}

/**
 * Funkce se zbaví možných bílých znaků a za nimi očekává SYMB, který pomocí dalších fcí zpracuje
 */
function parse_symb($instr_elem, $arg_order)
{
    $string = eat_white_space();
    $new_line = read_word($string);
    $type = check_symb($string);
    $string = htmlspecialchars($string, ENT_QUOTES);
    if($type == null)
        xml_add_arg($instr_elem, $arg_order, "var", $string);
    else
        xml_add_arg($instr_elem, $arg_order, $type, $string);

    return $new_line;
}

/**
 * Funkce se zbaví možných bílých znaků a za nimi očekává LABEL, který pomocí dalších fcí zpracuje
 */
function parse_label($instr_elem, $arg_order, $new_label, &$labels)
{
    $string = eat_white_space();
    $new_line = read_word($string);
    check_label($string);
    if($new_label)
        $labels = try_add_new_label($string, $labels);

    $string = htmlspecialchars($string, ENT_QUOTES);
    xml_add_arg($instr_elem, $arg_order, "label", $string);

    return $new_line;
}

/**
 * Funkce se zbaví možných bílých znaků a za nimi očekává TYPE, který pomocí dalších fcí zpracuje
 */
function parse_type($instr_elem, $arg_order)
{
    $string = eat_white_space();
    $new_line = read_word($string);
    check_type($string);
    xml_add_arg($instr_elem, $arg_order, "type", $string);

    return $new_line;
}

function try_add_new_label($string, $labels)
{
    foreach ($labels as $label) {
        if(strcmp($label, $string) == 0)
            return $labels;
    }
    $labels[] = $string;
    return $labels;
}

function write_statistics($file, $pars, $loc, $labels, $jumps)
{
    global $comments;
    $labels_count = count($labels);
    $handle = fopen($file, "w");
    if($handle === false)
        exit(12);

    foreach ($pars as $par) {
        echo $par;
        if(strcmp($par, "--loc") == 0)
            fwrite($handle, $loc . "\n");
        elseif(strcmp($par, "--comments") == 0)
            fwrite($handle, $comments . "\n");
        elseif(strcmp($par, "--labels") == 0)
            fwrite($handle, $labels_count . "\n");
        else
            fwrite($handle, $jumps . "\n");
    }
    fclose($handle);
}


/**
 * Funkce inicializuje hlavičku a kořen XML
 * návratová hodnota - kořen XML
 */
function init_xml()
{
    global $xml;
    $xml_root =  $xml->createElement("program");
    $root_attr = $xml->createAttribute("language");
    $root_attr->value = "IPPcode20";

    $xml_root->appendChild($root_attr);
    $xml->appendChild($xml_root);

    return $xml_root;

}

/**
 * Funkce inicializuje přidá další instrukci do XML
 * xml_root - kořen XML
 * order - pořadí intrukce
 * op_code - operační kód instrukce
 * návratová hodnota - element dané instrukce
 */
function xml_add_instr(DOMElement $xml_root, &$order, $op_code)
{
    $op_code = strtoupper($op_code);
    global $xml;

    $elem = $xml->createElement("instruction");


    $elem->setAttribute("order", $order);
    $elem->setAttribute("opcode", $op_code);

    $xml_root->appendChild($elem);

    $order++;

    return $elem;
}

/**
 * Funkce inicializuje a přidá element pro operand instrukce
 * instr - daná instrukce
 * order - pořadí operandu
 * op_code - typ operandu
 * arg_name - hodnota operandu
 */
function xml_add_arg(DOMElement $instr, $arg_order, $arg_type, $arg_name)
{
    if($instr == null)
        exit(23);
    global $xml;

    $elem = $xml->createElement("arg" . $arg_order);


    $elem->setAttribute("type", $arg_type);

    $elem->nodeValue = $arg_name;
    $instr->appendChild($elem);
}