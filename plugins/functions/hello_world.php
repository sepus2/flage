<?php

function flage_function_hello_world($flage,$args){
	if(isset($args[0])) return $args[0];
	return 'hello world';
}

?>