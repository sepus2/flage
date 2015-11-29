<?php

function flage_function_die($flage,$args,$line,$col){
	throw new FlageException("Template requested termination of execution",$line,$col);
}

?>