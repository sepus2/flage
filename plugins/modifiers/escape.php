<?php

function flage_modifier_escape($flage,$arg0,$arguments,$line,$col){
	$parsedArgs=flage_generate_args(array('filter'=>false,'firstword'=>false),$arguments,$line,$col);
	$filters = $parsedArgs['filter'];
	if(!$filters)
		//throw new FlageException("Unknown filter is required",$line,$col);
        $filters = 'html';

	foreach(explode(',',$filters) as $filter){
        switch($filter){
            case "html":
                $arg0 = htmlspecialchars($arg0);
                break;
            case "capitalize":
                /** @noinspection SpellCheckingInspection */
                $firstWord = $parsedArgs['firstword'];
                $items = preg_split('/(\s+)/',$arg0,-1,PREG_SPLIT_DELIM_CAPTURE);
                foreach($items as &$v){
                    if($v){
                        $v = ucfirst($v);
                        if($firstWord)
                            break;
                    }
                }
                $arg0 = implode('',$items);
                break;
            default:
                throw new FlageException("Unknown escape filter $filter",$line,$col);
        }
    }

    return $arg0;
}

?>