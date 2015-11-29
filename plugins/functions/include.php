<?php

/**
 * @param Flage $flage
 * @param mixed $arguments
 * @param int $line
 * @param int $col
 * @return null|string
 */
function flage_function_include($flage, $arguments, $line, $col){
    $parsedArgs=flage_generate_args(array('filename','inline'=>array('default'=>false)),$arguments,$line,$col);
    $filename = $parsedArgs['filename'];
    $inline = $parsedArgs['inline'];

    $path = array();
    foreach(explode('/',str_replace('\\','/',$filename)) as $segment){
        if($segment=='' || $segment=='.') continue;
        if($segment=='..'){
            array_pop($path);
        } else {
            array_push($path, $segment);
        }
    }
    $filename = implode('/',$path);

    $inst = clone $flage;//new Flage();
    return $inst->Parse($filename);
}