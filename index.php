<?php
error_reporting(E_ALL);
//print 1;
header('Content-Type: text/plain');

if(function_exists('xdebug_disable'))
	xdebug_disable();
	
//print 'test: '.function_exists('function_exists')."\n";
/*
function test_print($a,$b){
	print "a=$a,b=$b\n";
}
$b=1;
$c=2;
test_print($a=&$b,$a=&$c);
$a='x';
test_print($a=&$b,$a=&$c);

$xx=@$x24;*/
//print "Memory usage: ".memory_get_usage()."\n";
//print "Memory usage(true): ".memory_get_usage(true)."\n";
//print "Memory peak usage: ".memory_get_peak_usage()."\n";
//print "Memory peak usage(true): ".memory_get_peak_usage(true)."\n";


$initial_memory_usage = memory_get_usage();
$initial_memory_usage_peak = memory_get_peak_usage();

require_once('libs/Flage.php');



//print "Original input:\n".$data."\n---------------------\n";
//$x['aa']='inst';
$inst = new Flage();
//print $inst->Parse($data);
//srand();
//$inst->_file_hash_salt = rand().'-';
//$inst->setForceRecompile(true);
//$inst->setDisableCache(true);
print $inst->Parse('test.tpl');

//print "Memory usage: ".memory_get_usage()."\n";
//print "Memory usage(true): ".memory_get_usage(true)."\n";
//print "Memory peak usage: ".memory_get_peak_usage()."\n";
//print "Memory peak usage(true): ".memory_get_peak_usage(true)."\n";
print "\nMemory usage:".(memory_get_usage()-$initial_memory_usage);
print "\nMax memory usage:".(memory_get_peak_usage()-$initial_memory_usage_peak);

?>
