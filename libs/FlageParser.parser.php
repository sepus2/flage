<?php

define('ESCAPE_REPLACEMENT_VAR','#__B_ESC__#');
define('PHP_ENDING_TAG_STRING','#__PHP_ENDING_TAG__#');

define('TAG_EXTRACT_REGEX','/#__TAG_EXTRACT_START__(\d+)#(.*)#__TAG_EXTRACT_END__\1#/s');
//define('PHP_ENDING_TAG_STRING','');

/**
 * Basic node
 *
 * This node is initialized for all entry points or for all nodes without special requirements
 *
 * TODO: change all public accesses to protected and add getters/setters
 */
class FlageNode {
	public static $_id_cnt = 0;
	public $_line=0;
	public $_col=0;
	public $_this_id = 0;
	public $data='';
	public $type='';
    public $parser;
    /**
     * @var FlageNode|FlageNodeFunction|null
     */
	public $next=null;
    /**
     * @var FlageNode|null
     */
	public $prev=null;
	public $is_var_start=false;

    /**
     * Constructor.
     *
     * @param string $data
     * @param int $line
     * @param int $col
     */
	function __construct($data='',$line,$col){
		$this->data = $data;
		$this->_this_id = FlageNode::$_id_cnt++;
		$this->_line=$line;
		$this->_col=$col;
	}

    /**
     * Function which will be overwritten by all node subclasses
     *
     * @param FlageParser $parser
     * @param bool $additional
     * @return string
     */
	function to_string(/** @noinspection PhpUnusedParameterInspection */
        $parser, $additional=false){

		return $this->data;
	}

    /**
     * Generate current node.
     *
     * @param FlageParser $parser
     * @param bool|false $additional
     * @return string
     */
	function generate($parser, $additional=false){
		$out = $this->to_string($parser, $additional);
		$t = $this->next;
		/*
		if($this->is_var_start){
			print "-$this->type $this->data";
			$cc=$t;
			$xxx=0;
			while($cc){
				print "\n   .$cc->type $cc->data.";
				$cc = $cc->next;
				$xxx++;
				if($xxx==10) break;
			}
			print"=\n";
		}
        //*/
		if($t)
			$out .= $t->generate($parser, $additional);
		return $out;
	}
	function __toString(){
		return $this->generate($this->parser);
	}
}

/**
 * Class FlageArguments
 *
 * Node used for functions and arrays.
 */
class FlageArguments extends FlageNode{
    /**
     * @var FlageNode[][]
     */
	public $nodes = array();
	function __construct(){
        parent::__construct('',0,0);
		//$this->nodes = array();
	}

    /**
     * @param $line
     * @param $col
     * @return FlageNode
     */
	function nextArgumentNode($line,$col){
		$node = new FlageNode('',$line,$col);
		array_push($this->nodes,array(null,$node));
		return $node;
	}

    /**
     * Generate string representation of nodes
     *
     * @param FlageParser $parser
     * @param bool|false $additional
     * @return string
     */
	function to_string($parser, $additional=false){
        /*
		$out = '';
		$add = '';
		for($i=0; $i<count($this->nodes); $i++){
			$name = '';
			if($this->nodes[$i][1]->next){
				if($additional){
					$name = $this->nodes[$i][0];
					if($name){
						$_name=$name->generate($parser);
						if(
							(!$name->next 
							&& (
								$name->type=='literal' 
								|| $name->type==''
							))
							|| (
								!$name->type && $name->next && $name->next->type=='literal'
							)
						){
							$_name = "'".$_name."'";
						}
						//print $name->next .':'.$name->next->type.'-->'.$_name."xxx\n";
						$name = $_name.'=>';//$name->generate().'=>';
					}
				}
				$out .= $add.$name.$this->nodes[$i][1]->generate($parser);
				$add = ',';
			}
		}
		return $out;
        */
        return $this->to_string_split($parser,$additional,0);
	}

    function to_string_split($parser, $additional=false,$parts=0){
        $out = '';
        $add = '';
        $currentPart = 0;
        $returnArgs = array();
        for($i=0; $i<count($this->nodes); $i++){
            $name = '';
            if($this->nodes[$i][1]->next){
                if($parts>0 && $currentPart+1<$parts){
                    $returnArgs[] = $this->nodes[$i][1]->generate($parser);
                    $currentPart++;
                } else {
                    if($additional){
                        $name = $this->nodes[$i][0];
                        if($name){
                            $_name=$name->generate($parser);
                            if(
                                (!$name->next
                                    && (
                                        $name->type=='literal'
                                        || $name->type==''
                                    ))
                                || (
                                    !$name->type && $name->next && $name->next->type=='literal'
                                )
                            ){
                                $_name = "'".$_name."'";
                            }
                            //print $name->next .':'.$name->next->type.'-->'.$_name."xxx\n";
                            $name = $_name.'=>';//$name->generate().'=>';
                        }
                    }
                    $out .= $add.$name.$this->nodes[$i][1]->generate($parser);
                    $add = ',';
                }
            }
        }
        $returnArgs[] = $out;
        return $parts <= 0 ? $out : $returnArgs;
    }

    /**
     * @return FlageNode
     */
	function getName(){
		return $this->nodes[count($this->nodes)-1][0];
	}

    /**
     * @param int $line
     * @param int $col
     * @return FlageNode
     */
	function nodeToName($line,$col){
		$index = count($this->nodes)-1;
		$this->nodes[$index][0] = $this->nodes[$index][1];
		$this->nodes[$index][1] = $node = new FlageNode('',$line,$col);
		return $node;
	}
}

/**
 * Class FlageNodeArray
 *
 * Array node
 */
class FlageNodeArray extends FlageNode{
    /**
     * @var FlageArguments
     */
	public $arguments;
	function __construct(){
        parent::__construct('',0,0);
		$this->arguments = new FlageArguments();
		//$this->_this_id = FlageNode::$_id_cnt++;
	}

    /**
     * @param FlageParser $parser
     * @param bool|false $additional
     * @return string
     */
	function to_string($parser, $additional=false){
		$out = 'array(' .$this->arguments->generate($parser, true) . ')';
		return $out;
	}
}

/**
 * Class FlageNodeFunction
 *
 * Function node
 */
class FlageNodeFunction extends FlageNode{
    /**
     * @var FlageNode
     */
	public $name;
    /**
     * @var FlageArguments
     */
	public $arguments;
	public $is_method = false;
	public $is_literal = false;
	public $is_modifier = false;

    /**
     * @param string $name_node
     * @param int $line
     * @param int $col
     * @param bool|false $is_method
     */
	function __construct($name_node,$line,$col,$is_method=false){
        parent::__construct('',$line,$col);
		//$this->_line=$line;
		//$this->_col=$col;
		$this->arguments = new FlageArguments();
		$this->name = $name_node;
		$this->is_method = $is_method;
		//$this->_this_id = FlageNode::$_id_cnt++;
		$this->is_modifier=!$name_node;
	}

    /**
     * @param FlageParser $parser
     * @param bool|false $additional
     * @return string
     * @throws FlageException
     */
	function to_string($parser, $additional = false){
		// TODO: When is_literal make inline accessibility check 
		//         and replace with direct function calls or throw an error
		$is_array = !$this->is_method ;
		$out = '';
        $h_name = '';
		if($this->name){
			if($this->is_method ){
				$out = $this->name->generate($parser);
			} else {
				$name = $this->name->generate($parser);
				if($this->name && !$this->name->next && ($this->name->type=='literal' || $this->name->type=='')){
                    //if($parser->getHelper())
                    $functionDefinition = $parser->getFlage()->getFunctionDefinition($name,$this->is_modifier ? 'modifier' : 'function',$this->_line,$this->_col);

                    if($functionDefinition['path']){
                        $parser->_used_functions[$functionDefinition['path']] = array(
                            'path'=>$functionDefinition['path']
                            ,'function'=>$name
                            ,'name'=>$functionDefinition['function_name']
                        );
                    }

                    if($functionDefinition['function_type']=='php'){
                        $arguments = $this->arguments->generate($parser, $is_array);
                        $out = $functionDefinition['function_name'].'('.$arguments.')';
                    } else {
                        if($functionDefinition['is_modifier']){
                            $arguments = $this->arguments->to_string_split($parser, $additional, 2);
                            $arg0 = $arguments[0];
                            $arguments = $arguments[1];

                            $buffer = array(
                                '$__h'
                                ,$arg0
                                ,'array('.$arguments.')'
                                ,$this->_line
                                ,$this->_col
                            );
                            $out = $functionDefinition['function_name'].'('.implode(',',$buffer).')';
                        } else {
                            $arguments = $this->arguments->generate($parser, $is_array);
                            $buffer = array(
                                '$__h'
                                ,'array('.$arguments.')'
                                ,$this->_line
                                ,$this->_col
                            );
                            $out = $functionDefinition['function_name'].'('.implode(',',$buffer).')';
                        }
                    }

                    return $out;

                    /*
                    if($functionDefinition['function_type']=='php')
                        $is_array = false;
                    $out = $functionDefinition['function_name'];
                    // Now we have to verify that this function exists
                    /*
                    if(!function_exists($name)){
                        //$filename = ;
                        $parser->getHelper()->getFlageFunctionDeclaration($name,$this->is_modifier ? 'modifier' : 'function');
                        //$parser->_used_functions
                    } else {
                        $is_array = false;
                    }
                    if(!$parser->getHelper()->verifyFunctionAllowed($name)){
                        throw new FlageException("Function '$name' is not allowed",$this->_line,$this->_col);
                    }
                    $out = $name;
                    */
					//$name = "'".$this->name->generate($parser)."'";
                } else if($name) {
                    // String cast is no longer required as we are calling "c" instead of __h->{}
					//$name = '(string)'.$name;
					//$name = $name;
                }
				//print "==$name $this->_line $this->_col\n";
                $h_name = $name;
                $out = '$__h->c';//{'.$name.'}';
			}
		}
		$out .= '(';
		//if(count($this->arguments->nodes)>1 || $this->arguments->nodes[0][1]->next)
			$args =
                ($is_array ? 'array(' : '')
                .$this->arguments->generate($parser, $is_array)
                . (
                    $is_array ?
                        '),'
                        .$this->_line
                        .','
                        .$this->_col
                        .','
                        .($this->is_modifier ? 'true' : 'false')
                    : ''
                );
            if($h_name){
                $out .= $h_name
                    . ', array(';
                    if($args){
                        $out .= $args;
                    }
                $out .= ')';
            } else {
                $out .= $args;
            }
		$out .= ')';
		return $out;
	}
}

/**
 * Class FlageNodeGroup
 *
 * Class for all groupings like [], (), ...
 */
class FlageNodeGroup extends FlageNode{
    /**
     * Contents inside this group
     *
     * @var FlageNode
     */
	public $nodes;
    /**
     * Group starting character, ex [
     *
     * @var string
     */
	public $begin;
    /**
     * Group ending character, ex )
     *
     * @var int
     */
	public $end;

    /**
     * @param string $begin
     * @param int $end
     * @param int $line
     * @param int $col
     */
	function __construct($begin,$end,$line,$col){
		parent::__construct('',$line,$col);
		$this->begin = $begin;
		$this->end = $end;
		$this->nodes = new FlageNode('',$line,$col);
		//$this->_this_id = FlageNode::$_id_cnt++;
	}

    /**
     * @param FlageParser $parser
     * @param bool|false $additional
     * @return string
     */
	function to_string($parser, $additional = false){
		$out = $this->begin;//'[';
		$out .= $this->nodes->generate($parser);
		$out .= $this->end;//']';
		
		return $out;
	}
}

/**
 * Class FlageState
 */
class FlageState {
    /**
     * @var FlageNode
     */
	public $entry_node;
    /**
     * @var FlageNode
     */
	public $node;
    /**
     * @var FlageNode
     */
	public $parent_node;
	public $group_type = '';
    /**
     * @var FlageNode|null
     */
	public $parent_state = null;
	public $prev_type = '';
	public $type = '';
	public $begin_state = '';
	public $is_method = 0;
	public $is_assignment = false;
	public $returned = false;
    /**
     * @var FlageState|null
     */
    public $for_state = null;

    /**
     * @param FlageNode|null $parent_state
     * @param int $line
     * @param int $col
     */
	function __construct($parent_state=null,$line,$col){
		$this->parent_state = $parent_state;
		$this->entry_node = $this->parent_node = $this->node = new FlageNode('',$line,$col);
	}
}

/**
 * Class FlageGroup
 *
 * class to hold information of a group, used for IDE
 */
class FlageGroup {
    /**
     * @var bool
     */
    public $is_comment = false;
    /**
     * @var int
     */
    public $new_lines;
    /**
     * @var string
     */
    public $data;
    /**
     * @var int
     */
    public $nl_size;
    /**
     * @var int
     */
    public $entry_size;
    /**
     * @var string
     */
    public $tag;
    /**
     * @var bool
     */
    public $is_var;
    /**
     * @var bool
     */
    public $suppress;
    /**
     * @var bool
     */
    public $is_close;
    /**
     * @var bool
     */
    public $is_nested;
    /**
     * @var string
     */
    public $tag_rest;


    public function __construct($arguments){
        foreach($arguments as $k=>$v){
            $this->{$k} = $v;
        }
    }
}

/**
 * Class FlageParser
 *
 * The parser
 */
class FlageParser {
    /**
     * @var Flage
     */
    protected $__h;
	protected $line = 0;
	protected $col = 0;
	protected $replacement = 0;
    /**
     * @var FlageGroup[]
     */
	protected $groups = array();
	protected $_opened = array();
	protected $_vars = array();
	protected $_stack = array();
	protected $_dir_callback = 'plugins/callbacks/';
	protected $_dir_tag = 'plugins/tags/';
    /**
     * @var string[]
     */
	public $_used_functions = array();
    /**
     * @var string[]
     */
    public $prependInjectedCode = array();
    /**
     * @var FlageParserBlockBody[]
     */
    public $definedBodyBlock = array();

    /**
     * @return Flage
     */
    public function getFlage(){
        return $this->__h;
    }

    /**
     * @param $__h
     */
    public function setFlage($__h){
        $this->__h = $__h;
    }

	function _printD($val){
		//print_r($val);
	}
	function Parse($data){
		$dataString = str_replace('\{',ESCAPE_REPLACEMENT_VAR,$data);

		$dataString=preg_replace_callback('/\{\*(.*?)\*\}/s', array($this, 'remove_comments'),$dataString);

        // We are parsing multiple conditions
		$dataString=preg_replace_callback(
            // Opening tag which starts with {
			'/{'
                // (1) Next tag open char is :
                .'(:\s*'
                    // (2) Tag close OR variable start, which is not necessary
                    .'([\/\$]?)'
                    // (3) Tag close OR variable start
                    .'|([\/\$])'
                .')'
                // (4) full tag declaration including everything what goes after tag definition
                .'('
                    // (5) tag
                    .'('
                        // (6)
                        .'([a-z_]|)'
                        .'[a-z_0-9\.]*|"[^"]*"|\'[^\']*\''
                    .')'
                    // (7) everything what is left in tag declaration, ex {:if 1==0}, it will contain " 1==0"
                    .'('
                        // (8) simple regrouping, nothing functional
                        .'('
                            // any character but string or {}, will be parsed later
                            .'[^\'"{}]+'
                            // (9) Strings : "string value" OR 'string value'. If one follows another
                            //     , then they are concatenated
                            .'|('
                                .'"[^"]*"'
                                .'|\'[^\']*\''
                            .')'
                        .')*+'
                    .')'
                .')'
                // (10) if it ends with {, then is_nested flag is set, otherwise this is tag end
                .'([{}])'
            .'/si'
			, array($this, 'replacing')
			,$dataString
		);

        if(preg_last_error()){
            // TODO: PHP support version 5.5!!! to be modified
            throw new FlageException('Error during initial parse: '. array_flip(get_defined_constants(true)['pcre'])[preg_last_error()],0);
        }
        //print preg_last_error();

        // extracting r_group_[ID] OR as many non hash characters OR a single character OR \{ esc pattern
        //print '/(#r_group_(\d+)#|'.ESCAPE_REPLACEMENT_VAR.'|[^#]+|.)/s';
		$out = preg_replace_callback('/(#r_group_(\d+)#|'.ESCAPE_REPLACEMENT_VAR.'|[^#]+|.)/s', array($this, 'replacing2'),$dataString);
		$fn_out=array();
		if(count($this->_used_functions)){
			$fn_out[]='<?php ';
			foreach($this->_used_functions as $path=>$fn){
				$fn_out[]='require_once(\''.$path.'\');';
			}
			$fn_out[] = '?>';
		}

        if(count($this->_opened)){
            $unclosed = array_pop($this->_opened);
            throw new FlageException("Unclosed tag '$unclosed'",$this->line,$this->col);
        }


        //$parser_beginning_out=array();

		$return =
			// this one can be easily removed because php removes newlines after the tag closure
			preg_replace(
				'/\?>'.PHP_ENDING_TAG_STRING.'\r?\n?<\?php/i'
				,"\n"
				,str_replace(ESCAPE_REPLACEMENT_VAR,'{',$out)
			);

        if(PHP_ENDING_TAG_STRING!='')
            $return = str_replace(
                array(PHP_ENDING_TAG_STRING."\r\n",PHP_ENDING_TAG_STRING."\n",PHP_ENDING_TAG_STRING."\r",PHP_ENDING_TAG_STRING)
                ,array("\r\n\r\n","\n\n","\r\r",'')
                ,$return
            );

        // And now we take all tags marked for replacement and process them
        $return = preg_replace_callback(TAG_EXTRACT_REGEX, array($this, 'tags_replacing'),$return);

        $return =
            implode('',$fn_out)
            .implode('',$this->prependInjectedCode)
            .$return;

		// Enable this part to strip all spaces between php closing tag and php open tag
		//$return = preg_replace('/\?'.'>\s+<\?php/is'," \n",$return);
		return $return;
	}
	function remove_comments($args){
		$id = $this->replacement;
		$ret = '#r_group_'.$id.'#';
		$new_lines = explode("\n",$args[0]);
		$this->groups[$id] = new FlageGroup(array(
			'is_comment' => true
			,'new_lines' => count($new_lines)-1
			,'data' => $args[1]
			,'nl_size' => strlen(array_pop($new_lines))
			,'entry_size' => 2
        ));
		$this->replacement++;
		return $ret;
	}

    // String/number/variable/literal tokenize, save it in variable array and return this variable index
	function statement_tokenize($args){
        /** @noinspection PhpUnusedLocalVariableInspection */
        $type = 0;
		$suppress = false;
		if(count($args)<=3){// string
			$data = isset($args[2]) ? $args[2] : $args[1];
			$type = 'string';
		} else if(count($args)<6) { // number
			$data = $args[3];
			$type = 'number';
        } else if(count($args)==6) { // scientific number
            $data = $args[3];
            $type = 'number';
		} else { // variable or
			$data = $args[7];
			$type = $args[6]=='$' || $args[6]=='@' ? 'var' : 'literal';
			$suppress = $args[6]=='@';

            // Detect booleans and null
			if($type == 'literal'){
				switch(strtolower($data)){
					case 'true':
					case 'false':
					case 'null':
						$type='type_value';
						$data = strtolower($data);
						break;
				}
			}
		}
		$id = count($this->_vars);
		$nlTmp = explode("\n",$args[0]);
		$this->_vars[$id] = array(
			'type' => $type
			,'suppress'=>$suppress
			,'data' => $data
				,'size'=>strlen($args[0])
				,'nl'=>count($nlTmp)-1
				,'nl_size'=>strlen(array_pop($nlTmp))
		);
		return '$var'.$id.'$';
	}
	function statement_tokenize_op($args){
		$tmpNl = explode("\n",$args[0]);
		array_push($this->_stack
			,count($args)>2 ? array(
				'type'=>isset($args[4]) ? 'other' : ( isset($args[3]) ? 'space' : 'op')
				,'data'=>count($args)==2 ? $args[2] : $args[0]
				,'size'=>strlen($args[0])
				,'nl'=>count($tmpNl)-1
				,'nl_size'=>strlen(array_pop($tmpNl))
			) : $this->_vars[$args[1]]
		);
		$var = &$this->_stack[count($this->_stack)-1];
		//print_r($var);
		$var['line'] = $this->line;
		$var['col'] = $this->col;
		if($var['nl']){
			$this->line += $var['nl'];
			$this->col = 0;
		}
		$this->col += $var['nl_size'];
	}
                static $_expected_ending = array(
                    'function'=>')'
                    ,'index'=>']'
                    ,'array'=>']'
                    ,'conditional'=>':'
                );
                static $_context_transitions = array(
                    ''=>array(
                        ''=>'_end'
                    )
                    ,'block'=>array(
                        ''=>array(
                            ''=>'_end'
                            ,'literal'=>'_block_arg'
                        )
                        ,'argument_name'=>array(
                            '='=>'_function_arg_name'
                            ,''=>'_end'
                            ,'literal'=>'_block_arg'
                        )
                        ,'argument_value'=>array(
                            'string'=>'_op_value'
                            ,'number'=>'_op_value'
                            ,'type_value'=>'_op_value'
                                //,'literal'=>'_block_arg'
                        )
                        ,'string'=>array(
                            ''=>'_end'
                            ,'.+'=>'_op_concat'
                            //,'string'=>'_string_string'
                            ,'literal'=>'_block_arg'
                            ,'string'=>'_block_arg'
                        )
                        ,'number'=>array(
                            ''=>'_end'
                            ,'.+'=>'_op_concat'
                            ,'literal'=>'_block_arg'
                            ,'string'=>'_block_arg'
                        )
                        ,'type_value'=>array(
                            ''=>'_end'
                            ,'.+'=>'_op_concat'
                            ,'literal'=>'_block_arg'
                        )
                        ,'var'=>array(
                            'literal'=>'_block_arg'
                            ,'string'=>'_block_arg'
                        )
                    )
                    ,'function'=>array(
                        ''=>'_end_expecting'
                        ,')'=>'_end_function'
                        ,','=>'_function_arg'
                        ,'type_value'=>array(
                            '='=>'_function_arg_name'
                        )
                        ,'number'=>array(
                            '='=>'_function_arg_name'
                        )
                        ,'string'=>array(
                            '='=>'_function_arg_name'
                        )
                        ,'literal'=>array(
                            '='=>'_function_arg_name'
                        )
                    )
                    ,'array'=>array(
                        ''=>'_end_expecting'
                        ,']'=>'_end_array'
                        ,','=>'_function_arg'
                        ,'type_value'=>array(
                            '='=>'_function_arg_name'
                        )
                        ,'number'=>array(
                            '='=>'_function_arg_name'
                        )
                        ,'string'=>array(
                            '='=>'_function_arg_name'
                        )
                        ,'literal'=>array(
                            '='=>'_function_arg_name'
                        )
                    )
                    ,'index'=>array(
                        ''=>'_end_expecting'
                        ,']'=>'_end_index'
                    )
                    ,'group'=>array(
                        ''=>'_end_expecting'
                        ,')'=>'_end_group'
                    )
                    ,'for'=>array(
                        '..'=>'_for_next'
                    )
                    ,'for_next'=>array(
                        '..'=>'_for_next_unexpected'
                    )
                    ,'conditional'=>array(
                        ''=>'_end_expecting'
                        ,':'=>'_op_cond_else'
                    )
                    ,'conditional_else'=>array(
                        ''=>'_end_check_conditional'
                        ,'_end_optional'=>true
                        ,'_ending_chars'=>array(
                            ')'=>'_end_check_conditional'
                            ,']'=>'_end_check_conditional'
                            ,':'=>'_end_check_conditional'
                        )
                    )
                    ,'modifier'=>array(
                        ''=>'_check_ending_modifier'
                        ,'_end_optional'=>true
                        ,':'=>'_function_arg'
                        ,'type_value'=>array(
                            '='=>'_function_arg_name'
                        )
                        ,'number'=>array(
                            '='=>'_function_arg_name'
                        )
                        ,'string'=>array(
                            '='=>'_function_arg_name'
                        )
                        ,'literal'=>array(
                            '='=>'_function_arg_name'
                        )
                        ,'_ending_chars'=>array(
                            ')'=>'_check_ending_modifier'
                            ,','=>'_check_ending_modifier'
                            ,']'=>'_check_ending_modifier'
                            ,'+'=>'_check_ending_modifier'
                            ,'-'=>'_check_ending_modifier'
                            ,'*'=>'_check_ending_modifier'
                            ,'/'=>'_check_ending_modifier'
                            //,'='=>'_check_ending_modifier'
                            ,'+='=>'_check_ending_modifier'
                            ,'-='=>'_check_ending_modifier'
                            ,'=='=>'_check_ending_modifier'
                            ,'==='=>'_check_ending_modifier'
                            ,'!='=>'_check_ending_modifier'
                            ,'!=='=>'_check_ending_modifier'
                            ,'<'=>'_check_ending_modifier'
                            ,'<='=>'_check_ending_modifier'
                            ,'<=='=>'_check_ending_modifier'
                            ,'>'=>'_check_ending_modifier'
                            ,'>='=>'_check_ending_modifier'
                            ,'>=='=>'_check_ending_modifier'
                        )
                    )
                );
                static $_transitions = array(
                    ''=>array(
                        ''=>'_end'
                        ,'string'=>'_begin_string'
                        ,'number'=>'_begin_number'
                        ,'type_value'=>'_begin_number'
                        ,'literal'=>'_begin_literal'
                        ,'var'=>'_begin_var'
                        ,'('=>'_group_start'
                        ,'['=>'_begin_array'
                        ,')'=>'_end_empty_function'
                    )
                    ,'argument_name'=>array(
                        ''=>'_end'
                    )
                    ,'array'=>array(
                        ''=>'_end'
                        ,'|'=>'_op_modifier'
                    )
                    ,'.'=>array(
                        'string'=>'_op_offset_string'
                        ,'literal'=>'_op_offset_string'
                        ,'type_value'=>'_op_offset_number'
                        ,'number'=>'_op_offset_number'
                        ,'var'=>'_op_offset_var'
                    )
                    ,'function'=>array(
                        ''=>'_end'
                        ,'+'=>'_op'
                        ,'-'=>'_op'
                        ,'*'=>'_op'
                        ,'/'=>'_op'
                        ,'%'=>'_op'
                        ,'|'=>'_op_modifier'
                        ,'?'=>'_op_cond'
                        ,'('=>'_var_func'
                        ,'->'=>'_op_obj_offset'
                        ,'.+'=>'_op_concat'
                        ,'=='=>'_op'
                        ,'==='=>'_op'
                        ,'!='=>'_op'
                        ,'!=='=>'_op'
                        ,'>'=>'_op'
                        ,'>='=>'_op'
                        ,'>=='=>'_op'
                        ,'<'=>'_op'
                        ,'<='=>'_op'
                        ,'<=='=>'_op'
                    )
                    ,'type_value'=>array(
                        ''=>'_end'
                        ,'+'=>'_op'
                        ,'-'=>'_op'
                        ,'/'=>'_op'
                        ,'*'=>'_op'
                        //,'..'=>'_op'
                        ,'%'=>'_op'
                        ,'|'=>'_op_modifier'
                        ,'?'=>'_op_cond'
                        ,'.+'=>'_op_concat'
                        ,'=='=>'_op'
                        ,'==='=>'_op'
                        ,'!='=>'_op'
                        ,'!=='=>'_op'
                        ,'>'=>'_op'
                        ,'>='=>'_op'
                        ,'>=='=>'_op'
                        ,'<'=>'_op'
                        ,'<='=>'_op'
                        ,'<=='=>'_op'
                    )
                    ,'number'=>array(
                        ''=>'_end'
                        ,'+'=>'_op'
                        ,'-'=>'_op'
                        ,'/'=>'_op'
                        ,'*'=>'_op'
                        //,'..'=>'_op'
                        ,'%'=>'_op'
                        ,'|'=>'_op_modifier'
                        ,'?'=>'_op_cond'
                        ,'.+'=>'_op_concat'
                        ,'=='=>'_op'
                        ,'==='=>'_op'
                        ,'!='=>'_op'
                        ,'!=='=>'_op'
                        ,'>'=>'_op'
                        ,'>='=>'_op'
                        ,'>=='=>'_op'
                        ,'<'=>'_op'
                        ,'<='=>'_op'
                        ,'<=='=>'_op'
                    )
                    ,'string'=>array(
                        ''=>'_end'
                        ,'+'=>'_op'
                        ,'-'=>'_op'
                        ,'/'=>'_op'
                        ,'*'=>'_op'
                        ,'%'=>'_op'
                        ,'|'=>'_op_modifier'
                        ,'?'=>'_op_cond'
                        ,'.+'=>'_op_concat'
                        ,'string'=>'_string_string'
                        ,'=='=>'_op'
                        ,'==='=>'_op'
                        ,'!='=>'_op'
                        ,'!=='=>'_op'
                        ,'>'=>'_op'
                        ,'>='=>'_op'
                        ,'>=='=>'_op'
                        ,'<'=>'_op'
                        ,'<='=>'_op'
                        ,'<=='=>'_op'
                    )
                    ,'var'=>array(
                        ''=>'_end'
                        ,'+'=>'_op'
                        ,'-'=>'_op'
                        ,'*'=>'_op'
                        ,'/'=>'_op'
                        ,'.'=>'_op_offset'
                        ,'['=>'_op_index_start'
                        ,'%'=>'_op'
                        ,'|'=>'_op_modifier'
                        ,'?'=>'_op_cond'
                        ,'('=>'_var_func'
                        ,'++'=>'_op_inc_dec'
                        ,'--'=>'_op_inc_dec'
                        ,'->'=>'_op_obj_offset'
                        ,'='=>'_op_var_assign'
                        ,'+='=>'_op_var_assign'
                        ,'-='=>'_op_var_assign'
                        ,'*='=>'_op_var_assign'
                        ,'/='=>'_op_var_assign'
                        ,'.+'=>'_op_concat'
                        ,'=='=>'_op'
                        ,'==='=>'_op'
                        ,'!='=>'_op'
                        ,'!=='=>'_op'
                        ,'>'=>'_op'
                        ,'>='=>'_op'
                        ,'>=='=>'_op'
                        ,'<'=>'_op'
                        ,'<='=>'_op'
                        ,'<=='=>'_op'
                    )
                    ,'index_assignment_required'=>array(
                        '='=>'_op_var_assign'
                    )
                    ,'literal'=>array(
                        '('=>'_literal_func'
                    )
                    ,'%'=>array(
                        'var'=>'_op_var'
                        ,'literal'=>'_op_value'
                        ,'type_value'=>'_op_value'
                        ,'number'=>'_op_value'
                        ,'string'=>'_op_value'
                        ,'('=>'_group_start'
                    )
                    ,'+'=>array(
                        'var'=>'_op_var'
                        ,'literal'=>'_op_value'
                        ,'type_value'=>'_op_value'
                        ,'number'=>'_op_value'
                        ,'string'=>'_op_value'
                        ,'('=>'_group_start'
                    )
                    ,'+='=>array(
                        'var'=>'_op_var'
                        ,'literal'=>'_op_value'
                        ,'type_value'=>'_op_value'
                        ,'number'=>'_op_value'
                        ,'string'=>'_op_value'
                        ,'('=>'_group_start'
                    )
                    ,'-'=>array(
                        'var'=>'_op_var'
                        ,'literal'=>'_op_value'
                        ,'type_value'=>'_op_value'
                        ,'number'=>'_op_value'
                        ,'string'=>'_op_value'
                        ,'('=>'_group_start'
                    )
                    ,'*'=>array(
                        'var'=>'_op_var'
                        ,'literal'=>'_op_value'
                        ,'type_value'=>'_op_value'
                        ,'number'=>'_op_value'
                        ,'string'=>'_op_value'
                        ,'('=>'_group_start'
                    )
                    ,'/'=>array(
                        'var'=>'_op_var'
                        ,'literal'=>'_op_value'
                        ,'type_value'=>'_op_value'
                        ,'number'=>'_op_value'
                        ,'string'=>'_op_value'
                        ,'('=>'_group_start'
                    )
                    ,'.+'=>array(
                        'var'=>'_op_var'
                        ,'literal'=>'_op_value'
                        ,'type_value'=>'_op_value'
                        ,'number'=>'_op_value'
                        ,'string'=>'_op_value'
                        ,'('=>'_group_start'
                    )
                    ,'->'=>array(
                        'literal'=>'_op_obj_literal'
                        ,'var'=>'_op_obj_var'
                        ,'('=>'_group_start'
                    )
                    ,'='=>array(
                        'var'=>'_op_var'
                        ,'literal'=>'_op_value'
                        ,'type_value'=>'_op_value'
                        ,'number'=>'_op_value'
                        ,'string'=>'_op_value'
                        ,'('=>'_group_start'
                        ,'['=>'_begin_array'
                    )
                    ,'=='=>array(
                        'var'=>'_op_var'
                        ,'literal'=>'_op_value'
                        ,'type_value'=>'_op_value'
                        ,'number'=>'_op_value'
                        ,'string'=>'_op_value'
                        ,'('=>'_group_start'
                    )
                    ,'==='=>array(
                        'var'=>'_op_var'
                        ,'literal'=>'_op_value'
                        ,'type_value'=>'_op_value'
                        ,'number'=>'_op_value'
                        ,'string'=>'_op_value'
                        ,'('=>'_group_start'
                    )
                    ,'!='=>array(
                        'var'=>'_op_var'
                        ,'literal'=>'_op_value'
                        ,'type_value'=>'_op_value'
                        ,'number'=>'_op_value'
                        ,'string'=>'_op_value'
                        ,'('=>'_group_start'
                    )
                    ,'!=='=>array(
                        'var'=>'_op_var'
                        ,'literal'=>'_op_value'
                        ,'type_value'=>'_op_value'
                        ,'number'=>'_op_value'
                        ,'string'=>'_op_value'
                        ,'('=>'_group_start'
                    )
                    ,'>'=>array(
                        'var'=>'_op_var'
                        ,'literal'=>'_op_value'
                        ,'type_value'=>'_op_value'
                        ,'number'=>'_op_value'
                        ,'string'=>'_op_value'
                        ,'('=>'_group_start'
                    )
                    ,'>='=>array(
                        'var'=>'_op_var'
                        ,'literal'=>'_op_value'
                        ,'type_value'=>'_op_value'
                        ,'number'=>'_op_value'
                        ,'string'=>'_op_value'
                        ,'('=>'_group_start'
                    )
                    ,'>=='=>array(
                        'var'=>'_op_var'
                        ,'literal'=>'_op_value'
                        ,'type_value'=>'_op_value'
                        ,'number'=>'_op_value'
                        ,'string'=>'_op_value'
                        ,'('=>'_group_start'
                    )
                    ,'<'=>array(
                        'var'=>'_op_var'
                        ,'literal'=>'_op_value'
                        ,'type_value'=>'_op_value'
                        ,'number'=>'_op_value'
                        ,'string'=>'_op_value'
                        ,'('=>'_group_start'
                    )
                    ,'<='=>array(
                        'var'=>'_op_var'
                        ,'literal'=>'_op_value'
                        ,'type_value'=>'_op_value'
                        ,'number'=>'_op_value'
                        ,'string'=>'_op_value'
                        ,'('=>'_group_start'
                    )
                    ,'<=='=>array(
                        'var'=>'_op_var'
                        ,'literal'=>'_op_value'
                        ,'type_value'=>'_op_value'
                        ,'number'=>'_op_value'
                        ,'string'=>'_op_value'
                        ,'('=>'_group_start'
                    )
                    ,'|'=>array(
                        'literal'=>'_op_modifier_declaration'
                    )
                );


    static $_context_transitions_arguments = array(
        ''=>array(
            ''=>'_end'
        )
        ,'block'=>array(
            //''=>'_end_expecting'
            //'literal'=>'_block_arg_name'
            //,'type_value' => array(
            //    '='=>'_block_arg_name'
            //)
        )

    );
    static $_transitions_arguments = array(
        ''=>array(
            ''=>'_end'
            ,'literal'=>'_block_arg'
        )
        ,'argument_name'=>array(
            '='=>'_block_arg_name'
            ,''=>'_end'
            ,'literal'=>'_block_arg'
        )
        ,'argument_value'=>array(
            'string'=>'_op_value'
            ,'number'=>'_op_value'
            ,'type_value'=>'_op_value'
            //,'literal'=>'_block_arg'
        )
        ,'string'=>array(
            ''=>'_end'
            ,'.+'=>'_op_concat'
            ,'string'=>'_string_string'
            ,'literal'=>'_block_arg'
        )
        ,'number'=>array(
            ''=>'_end'
            ,'.+'=>'_op_concat'
            ,'literal'=>'_block_arg'
        )
        ,'type_value'=>array(
            ''=>'_end'
            ,'.+'=>'_op_concat'
            ,'literal'=>'_block_arg'
        )
    );

	function _print_node($state,$offset=""){
		if($state){
			print $offset.get_class($state)."(\n";
            /** @noinspection SpellCheckingInspection */
            print $offset."\tdata: $state->data\n";
				$this->_print_node($state->next,$offset."\t");
			print $offset.")\n";
		}
	}
	function _string_escape($str){
		return str_replace(
			array("\\n","\\r","\\t","\\","'")
			,array("\n","\r","\t","\\\\","\\'")
			,$str
		);
	}

    /**
     * @param FlageState $state
     * @param string[] $stack
     * @return FlageState
     */
	function _for_next($state,$stack){
		$state2 = new FlageState(null,$stack['line'],$stack['col']);
		$state2->group_type='for_next';
		$state2->for_state = $state;

		return $state2;
	}
	function _begin_array($state,$stack,$line,$col){
		$state2 = new FlageState(null,$line,$col);
		$state2->parent_state = $state;
		$state2->node->next = new FlageNodeArray();
		
		$state2->node->prev = $state->node;
		$state->node->next = $state2->node;
		
		$state->parent_node = $state->node = $state2->node->next;

		$state2->node->next->prev = $state2->node;
		$state2->parent_node = $state2->node = $state2->node->next->arguments->nextArgumentNode($stack['line'],$stack['col']);
		
		$state2->type = '';
		$state2->group_type = 'array';
		
		return $state2;
	}
	function _op_var_assign($state,$stack){
		$state->is_assignment = $state->entry_node->next == $state->parent_node;
		return $this->_op($state,$stack);
	}
	function _op_var($state,$stack){
        if($this->getFlage()->verifyVariableBlacklisted($stack['data']))
            throw new FlageException("Variable '".$stack['data']."' is not allowed",$stack['line'],$stack['col']);
		$state->node->next = new FlageNode(($stack['suppress']?'@':'').'$'.$stack['data'],$stack['line'],$stack['col']);
		$state->var_start=$state->node->next;
		$state->node->next->type = $state->type;
		$state->node->next->prev = $state->node;
		$state->node = $state->parent_node = $state->node->next;
		// V2
		$state->node->is_var_start=true;
		$state->node->type='var';
		$state->type='var';
		return $state;
	}
	function _op_inc_dec($state,$stack){
		$state->node->next = new FlageNode($stack['data'],$stack['line'],$stack['col']);
		$state->node->next->type = $state->type;
		$state->node->next->prev = $state->node;
		$state->node = $state->node->next;
		$state->type = 'var';
		return $state;
	}
	function _op_value($state,$stack){
		$state->node->next = new FlageNode(
			$state->type=='string' ? "'".$this->_string_escape($stack['data'])."'" : $stack['data']
			,$stack['line'],$stack['col']
		);
		$state->node->next->type = $state->type;
		$state->node->next->prev = $state->node;
		$state->node = $state->parent_node = $state->node->next;
		return $state;
	}
	function _string_string($state,$stack){
		$state->node->next = new FlageNode('.',$stack['line'],$stack['col']);
		$state->node->next->type = $state->type;
		$state->node->next->prev = $state->node;
		$state->node = $state->node->next;

		return $this->_op_value($state,$stack);
	}
	function _op_concat($state,$stack){
		$state->node->next = new FlageNode('.',$stack['line'],$stack['col']);
		$state->node->next->type = $state->type;
		$state->node->next->prev = $state->node;
		$state->node = $state->node->next;
		return $state;
	}
	function _op($state,$stack){
		$state->node->next = new FlageNode($stack['data'],$stack['line'],$stack['col']);
		$state->node->next->type = $state->type;
		$state->node->next->prev = $state->node;
		$state->node = $state->node->next;
		return $state;
	}
	function _end(
        $state,
        /** @noinspection PhpUnusedParameterInspection */
        $stack
    ){
		return $state;
	}
	function _op_cond_else($state,$stack){
		if(
			!isset(FlageParser::$_transitions[$state->prev_type][''])
		){
			throw(new FlageException("Unexpected '$state->type' after '$state->prev_type'",$stack['line'],$stack['col']));
		}
		if(!$state->prev_type)
			throw(new FlageException("Conditional else statement is required",$stack['line'],$stack['col']));
		$state->node->next = new FlageNode(':',$stack['line'],$stack['col']);
		$state->node->next->prev = $state->node;
		$state->node = $state->node->next;
		$state->type = '';
		$state->group_type = 'conditional_else';
		
		return $state;
	}
	function _op_cond($state,$stack){
		$state2 = new FlageState(null,$stack['line'],$stack['col']);
		$state2->parent_state = $state;
		$state->node->next = new FlageNodeGroup('?','',$stack['line'],$stack['col']);
		$state->node->next->prev = $state->node;
		$state->node = $state->node->next;
		$state2->type = '';
		$state2->group_type = 'conditional';
		$state2->parent_node = $state2->node = $state->node->nodes;
		
		return $state2;
	}
	function _begin_literal($state,$stack){
		$state->node->next = new FlageNode($stack['data'],$stack['line'],$stack['col']);
		$state->node->next->type = $state->type;
		$state->node->next->prev = $state->node;
		$state->node = $state->parent_node = $state->node->next;
		return $state;
	}

    function _block_arg_name($state,$stack){
        $newState = $this->_function_arg_name($state,$stack);
        $newState->type = 'argument_value';
        return $newState;
    }
    /**
     * @param FlageState $state
     * @param $stack
     * @return FlageState
     * @throws FlageException
     */
	function _function_arg_name($state,$stack){
		$args = $state->entry_node->next->arguments;
		if($args->getName()){
			throw(new FlageException("Unexpected '$state->type'",$stack['line'],$stack['col']));
		}
		$node = $args->nodeToName($stack['line'],$stack['col']);
		$state->node = $state->parent_node = $node;
		$state->type = '';
		return $state;
	}
	function _end_array($state,$stack){
		if( !isset(FlageParser::$_transitions[$state->prev_type]['']) ){
			throw(new FlageException("Unexpected '$state->type' after '$state->prev_type'",$stack['line'],$stack['col']));
		}
		if($state->prev_type=='' && $state->entry_node->next->arguments->nodes[count($state->entry_node->next->arguments->nodes)-1][0]){
			throw(new FlageException("Array value is required",$stack['line'],$stack['col']));
		}
		$state->parent_state->type = 'array';
		$state->parent_state->node = $state->entry_node->next;
		return $state->parent_state;
	}
	function _check_ending_modifier(
        $state//,$stack
    ){
		$state->parent_state->returned = true;
		$state->parent_state->type = 'function';
		return $state->parent_state;
	}
	function _end_check_conditional($state,$stack){
		if($state->prev_type==''){
			throw(new FlageException("Unexpected empty conditional statement",$stack ? $stack['line'] : $this->line,$stack ? $stack['col'] : $this->col));
		}
		$found = false;
		foreach(FlageParser::$_expected_ending as $end)
			if($end==$state->type){
				$found = true;
				break;
			}
		if(
			!$found && 
			!isset(FlageParser::$_transitions[$state->type][''])
		){
			throw(new FlageException("Unexpected condition ending with '$state->type'",$stack ? $stack['line'] : $this->line,$stack ? $stack['col'] : $this->col));
		}
		$state->parent_state->type = $state->prev_type;
		$state->parent_state->returned = true;
		return $state->parent_state;
	}
	function _end_function($state,$stack){
		if(
			!isset(FlageParser::$_transitions[$state->prev_type][''])
			&& (
				$state->prev_type!='' 
				|| count($state->entry_node->next->arguments->nodes)>1
			)
		){
			throw(new FlageException("Unexpected '$state->type' after '$state->prev_type'",$stack['line'],$stack['col']));
		}
		$state->parent_state->type = 'function';
		$state->parent_state->node = $state->entry_node->next;
		return $state->parent_state;
	}
    function _block_arg($state,$stack){
        $newState = $this->_function_arg($state,$stack);
        $newState->node->data = $stack['data'];
        $newState->type='argument_name';
        return $newState;
    }

    /**
     * @param FlageState $state
     * @param string[] $stack
     * @return FlageState
     * @throws FlageException
     */
	function _function_arg($state,$stack){
		if(!isset(FlageParser::$_transitions[$state->prev_type][''])){
			throw(new FlageException("Unexpected '$state->type' after '$state->prev_type'",$stack['line'],$stack['col']));
		}
		$state->node = $state->parent_node = $state->entry_node->next->arguments->nextArgumentNode($stack['line'],$stack['col']);
		$state->type = '';
		return $state;
	}
	function _begin_string($state,$stack){
		$state->node->next = new FlageNode("'".$this->_string_escape($stack['data'])."'",$stack['line'],$stack['col']);
		$state->node->next->type = 'string';
		$state->node->next->prev = $state->node;
		$state->node = $state->parent_node = $state->node->next;
		return $state;
	}
	function _end_empty_function($state,$stack){
		if($state->group_type != 'function'){
			throw(new FlageException("Unexpected ".$stack['data'],$stack['line'],$stack['col']));
		}
		$state->parent_state->type = 'function';
		$state->parent_state->node = $state->node;
		return $state->parent_state;
	}
	function _op_modifier_declaration($state,$stack){
		$state->entry_node->next->name = new FlageNode($stack['data'],$stack['line'],$stack['col']);
		$state->type='';
		$state->group_type = 'modifier';
		return $state;
	}
	function _op_modifier($state,$stack){
		$state2 = new FlageState(null,$stack['line'],$stack['col']);
		$state2->parent_state = $state;
		$state2->node->next = new FlageNodeFunction(null,$stack['line'],$stack['col'],false);
		
		$pNode = $state->parent_node;
		
		$state->parent_node->prev->next = $state2->node;
		$state2->node->prev = $state->parent_node->prev;
		
		
		$state->parent_node = $state->node = $state2->node;

		$state2->node->next->prev = $state2->node;
		$arguments = $state2->node->next->arguments;
		$state2->parent_node 
			= $state2->node 
			= $arguments->nextArgumentNode($stack['line'],$stack['col']);
		
		$state2->node->next = $pNode;
		
		$state2->type = '|';
		$state2->group_type = 'modifier_declaration';
		
		return $state2;
	}
	function _literal_func($state,$stack){
		$new_state = $this->_var_func($state,$stack);
		$new_state->entry_node->next->is_literal = true;
		return $new_state;
	}
	function _var_func($state,$stack){
		$state2 = new FlageState(null,$stack['line'],$stack['col']);
		$state2->parent_state = $state;
		$state2->node->next
			= new FlageNodeFunction(
				$state->parent_node
				,$state->parent_node->_line
                ,$state->parent_node->_col
				,$state->is_method==1
			);
		
		$state->parent_node->prev->next = $state2->node;
		$state2->node->prev = $state->parent_node->prev;
		
		
		$state->parent_node = $state->node = $state2->node;

		$state2->node->next->prev = $state2->node;
		$state2->parent_node = $state2->node = $state2->node->next->arguments->nextArgumentNode($stack['line'],$stack['col']);
		
		$state2->type = '';
		$state2->group_type = 'function';
		
		return $state2;
	}
	function _op_obj_literal($state,$stack){
		$state->node->next = new FlageNode($stack['data'],$stack['line'],$stack['col']);
		$state->node->next->prev = $state->node;
		$state->node = $state->node->next;
		
		$state->is_method = 2;

		$state->type = 'var';
		
		return $state;

	}
	function _op_obj_offset($state,$stack){
		$state->node->next = new FlageNode($stack['data'],$stack['line'],$stack['col']);
		$state->node->next->prev = $state->node;
		$state->node = $state->node->next;
		
		return $state;
	}
	function _end_index($state,$stack){
		if($state->group_type!='index'){
			throw(new FlageException('Unexpected ]',$stack['line'],$stack['col']));
		}
		if(!isset(FlageParser::$_transitions[$state->prev_type][''])){
			throw(new FlageException("Unexpected '$state->type' after '$state->prev_type'",$stack['line'],$stack['col']));
		}
		$state->parent_state->type = $state->prev_type=='' ? 'index_assignment_required' : 'var';
		return $state->parent_state;
	}
	function _end_group($state,$stack){
		if(
			!isset(FlageParser::$_transitions[$state->prev_type][''])
			|| $state->prev_type==''
		){
			throw(new FlageException("Unexpected '$state->type' after '$state->prev_type'",$stack['line'],$stack['col']));
		}
		
		$state->parent_state->type = 'var';
		return $state->parent_state;
	}
	function _op_b_end($state,$stack){
		if($state->group_type!='index'){
			throw(new FlageException('Unexpected ]',$stack['line'],$stack['col']));
		}
		
		$state->parent_state->type = 'var';
		return $state->parent_state;
	}
	function _begin_number($state,$stack){
		$state->node->next = new FlageNode($stack['data'],$stack['line'],$stack['col']);
		$state->node->next->prev = $state->node;
		$state->node = $state->node->next;
		$state->parent_node = $state->node;
		$state->type = 'number';
		
		return $state;
	}
	function _op_offset_string($state,$stack){
		$state->node->next = new FlageNodeGroup('[',']',$stack['line'],$stack['col']);
		$state->node->next->prev = $state->node;
		$state->node = $state->node->next;
		$state->node->nodes->next = new FlageNode("'".$this->_string_escape($stack['data'])."'",$stack['line'],$stack['col']);
		$state->type = 'var';
		return $state;
	}
	function _op_offset_number($state,$stack){
		$state->node->next = new FlageNodeGroup('[',']',$stack['line'],$stack['col']);
		$state->node->next->prev = $state->node;
		$state->node = $state->node->next;
		$state->node->nodes->next = new FlageNode($stack['data'],$stack['line'],$stack['col']);
		$state->type = 'var';
		return $state;
	}
	function _op_offset_var($state,$stack){
		$state->node->next = new FlageNodeGroup('[',']',$stack['line'],$stack['col']);
		$state->node->next->prev = $state->node;
		$state->node = $state->node->next;
        if($this->getFlage()->verifyVariableBlacklisted($stack['data']))
            throw new FlageException("Variable '".$stack['data']."' is not allowed",$stack['line'],$stack['col']);
		$state->node->nodes->next = new FlageNode(($stack['suppress']?'@':'').'$'.$stack['data'],$stack['line'],$stack['col']);
		// V2
		$state->node->nodes->next->is_var_start=true;
		$state->node->nodes->next->type = 'var';
		$state->type = 'var';
		return $state;
	}
	function _op_offset(
        $state//,$stack
    ){
		
		return $state;
	}
	function _group_start($state,$stack){
		$state2 = new FlageState(null,$stack['line'],$stack['col']);
		$state2->parent_state = $state;
		$state->node->next = new FlageNodeGroup('(',')',$stack['line'],$stack['col']);
		$state->node->next->prev = $state->node;
		$state->node = $state->parent_node = $state->node->next;
		$state2->type = '';
		$state2->group_type = 'group';
		$state2->parent_node = $state2->node = $state->node->nodes;
		
		return $state2;
	}
	function _op_index_start($state,$stack){
		$state2 = new FlageState(null,$stack['line'],$stack['col']);
		$state2->parent_state = $state;
		$state->node->next = new FlageNodeGroup('[',']',$stack['line'],$stack['col']);
		$state->node->next->prev = $state->node;
		$state->node = $state->node->next;
		$state2->type = '';
		$state2->group_type = 'index';
		$state2->parent_node = $state2->node = $state->node->nodes;
		
		return $state2;
	}

    /**
     * @param FlageState $state
     * @param $stack
     * @return mixed
     * @throws FlageException
     */
	function _begin_var($state,$stack){
        if($this->getFlage()->verifyVariableBlacklisted($stack['data']))
            throw new FlageException("Variable '".$stack['data']."' is not allowed",$stack['line'],$stack['col']);
		$state->node->next = new FlageNode(($stack['suppress']?'@':'').'$'.$stack['data'],$stack['line'],$stack['col']);
		$state->node->next->prev = $state->node;
		$state->node = $state->node->next;
		$state->parent_node = $state->node;
		$state->type = 'var';
		// V2
		$state->node->type = 'var';
		$state->node->is_var_start=true;
		
		return $state;
	}

    /**
     * Function called when end is expected but the statement ended, ex {:a(''}
     *
     * @param FlageState $state
     * @throws FlageException
     */
	function _end_expecting($state){
		throw(new FlageException("Statement expects to be finished with ".FlageParser::$_expected_ending[$state->group_type],$this->line,$this->col));
	}

    function _generate_tokens_stack($statement){
        $this->_stack = array();
        // Tokenize string, number, variable, literal (used for function names), boolean, null
        $res = preg_replace_callback(
        // This one is catching the numbers with +|-, but it causes problems later
        //'/"([^"]*)"|\'([^\']*)\'|([-+]?([0-9]+\.)?[0-9]+([eE][-+]?[0-9]+)?)|(\$)?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/si'
        // (1) - Double quotes string representation
            '/"([^"]*)"'
            // (2) - Single quotes string representation
            .'|\'([^\']*)\''
            // (3) - Number representation
            .'|('
            // (4) - int/double
            .'([0-9]+\.)?[0-9]+'
            // (5) - scientific representation
            .'([eE][-+]?[0-9]+)?'
            .')'
            // (6) - variable normal or suppressed
            .'|(\$|@)?'
            // (7) - variable name
            .'([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/si'
            , array($this, 'statement_tokenize')
            ,$statement
        );
        // Tokenize ops
        /** @noinspection PhpUnusedLocalVariableInspection */
        $res = preg_replace_callback(
            '#'
            // (1) - extract variable index
            .'\$var(\d+)\$'
            // (2) - All possible operations unary or binary, assignments, conditions, (, ), ...
            .'|(\+=|\-=|\*=|\/=|\+\+|\-\-|\|\||&&|\||&|\*|/|\+|\.\+|\.|\->|\-|!==|===|!=|==|>==|<==|>=|<=|>|<|\[|\]|\(|\)|=|,|%|\?|:)'
            // (3) - All extra whitespaces, keep them for lines increment
            .'|(\s+)'
            // (4) - other... If it is not defined by previous pattern, then we have to catch it and throw an exception
            .'|(.)#si'
            , array($this, 'statement_tokenize_op')
            ,$res
        );
    }

    function _tokenize_named_arguments($statement, /** @noinspection PhpUnusedParameterInspection */
                                       $type='', $static_vars=true){
        //$this->_generate_tokens_stack($statement);

        $_context_transitions = FlageParser::$_context_transitions;
        $_transitions = FlageParser::$_transitions;

        if($static_vars) {
            FlageParser::$_context_transitions = FlageParser::$_context_transitions_arguments;
            FlageParser::$_transitions = FlageParser::$_transitions_arguments;
        }

        // Generate new state, which is counted as a function
        $state2 = new FlageState(null,$this->line,$this->col,false);
        // New function node
        $state2->node->next
            = new FlageNodeFunction(null,$this->line,$this->col,false);

        // Previous node of the function is the created state
        $state2->node->next->prev = $state2->node;
        // parent node is argument node, as we do not need function name
        $state2->parent_node = $state2->node = $state2->node->next->arguments->nextArgumentNode($this->line,$this->col);

        $state2->type = '';
        // Group type is a function
        $state2->group_type = 'block';
        // We need to tokenize only arguments
        $state = $this->_tokenize($statement,$state2);

        $nodes = $state->entry_node->next->arguments->nodes;
        $arguments = array();
        for($i=1; $i<count($nodes); $i++){
            //$argument->
            if($nodes[$i][0]){
                $name = $nodes[$i][0]->generate($this);
                $value = $nodes[$i][1]->generate($this);
            } else {
                $name = $nodes[$i][1]->generate($this);
                $value = "'$name'";
            }

            //print "$name=$value\n";
            $arguments[$name] = $value;
        }



        //$retState = $state;
        //$retState = $this->_tokenize($statement,$type);

        if($static_vars) {
            FlageParser::$_context_transitions = $_context_transitions;
            FlageParser::$_transitions = $_transitions;
        }


        return $arguments;
    }
    /**
     * Tokenize process transforms String into FlageState
     *
     * @param string $statement
     * @param string $type
     * @return FlageState
     * @throws FlageException
     */
	function _tokenize($statement,$type=''){
        /*
		$this->_stack = array();
        // Tokenize string, number, variable, literal (used for function names), boolean, null
		$res = preg_replace_callback(
			// This one is catching the numbers with +|-, but it causes problems later
			//'/"([^"]*)"|\'([^\']*)\'|([-+]?([0-9]+\.)?[0-9]+([eE][-+]?[0-9]+)?)|(\$)?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/si'
			// (1) - Double quotes string representation
            '/"([^"]*)"'
            // (2) - Single quotes string representation
            .'|\'([^\']*)\''
            // (3) - Number representation
            .'|('
                // (4) - int/double
                .'([0-9]+\.)?[0-9]+'
                // (5) - scientific representation
                .'([eE][-+]?[0-9]+)?'
            .')'
            // (6) - variable normal or suppressed
            .'|(\$|@)?'
            // (7) - variable name
              .'([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/si'
			, array($this, 'statement_tokenize')
			,$statement
		);
        // Tokenize ops
        $res = preg_replace_callback(
			'#'
            // (1) - extract variable index
            .'\$var(\d+)\$'
            // (2) - All possible operations unary or binary, assignments, conditions, (, ), ...
            .'|(\+=|\-=|\*=|\/=|\+\+|\-\-|\|\||&&|\||&|\*|/|\+|\.\+|\.|\->|\-|!==|===|!=|==|>==|<==|>=|<=|>|<|\[|\]|\(|\)|=|,|%|\?|:)'
            // (3) - All extra whitespaces, keep them for lines increment
            .'|(\s+)'
            // (4) - other... If it is not defined by previous pattern, then we have to catch it and throw an exception
            .'|(.)#si'
			, array($this, 'statement_tokenize_op')
			,$res
		);
        */

        $this->_generate_tokens_stack($statement);
		

        // If type is not an object, then we create an empty state
		if(gettype($type)!='object'){
			$state = new FlageState(null,$this->line,$this->col);
			$state->group_type = $type;
		} else {
			$state = $type;
		}

        // $this->stack will contain all processed tokens, now we will execute conversion to php code
		for($i=0; $i<count($this->_stack); $i++){
			$state->returned = false;
			//$data = $this->_stack[$i]['data'];
			$stack = &$this->_stack[$i];
            // If this is an unknown element found during op tokenize process, then throw an exception
			if($stack['type']=='other'){
				throw(new FlageException("Unexpected character $stack[data]",$stack['line'],$stack['col']));
			} else if($stack['type']=='space') {
                // Ignore pure whitespaces
				continue;
			}

            // nextStmt will contain token type. If it is an op, it will contain op symbol, ex +, -, .+.
			if($stack['type']=='op'){
				$nextStmt = $stack['data'];
			} else {
				$nextStmt = $stack['type'];
			}
            // Store current state type as it will be required during further processing
			$prevStmt = $state->type;
			$state->prev_type = $state->type;
            // Save new state type
			$state->type = $nextStmt;
            // If previous type was a literal, then is_method==2, so to determine if this is a method we simply have to
            //   verify value==1
			$state->is_method--;

            // Verify if an assignment is required but current state type is not an assignment
			if($state->prev_type=='index_assignment_required' && ($state->type!='=')){
				//print "should be crashing\n";
				throw(new FlageException("Only assignment allowed after empty index []",$stack['line'],$stack['col']));
			}
			$this->_printD( "$state->group_type : $prevStmt => $nextStmt\n");
			
			$call_fn = '';
            // Example of next verification {:a("<br />"|escape..'1')} , or {:$a=(true?"true":"false")}
			if(
                // Verify if statement ending is conditional and if so, ...
				isset(FlageParser::$_context_transitions[$state->group_type]['_end_optional'])
				&&  FlageParser::$_context_transitions[$state->group_type]['_end_optional']
                // test current state type if it just hit ending character
				&&  isset(FlageParser::$_context_transitions[$state->group_type]['_ending_chars'][$nextStmt])
			){
                // Finalizing function
				$call_fn = FlageParser::$_context_transitions[$state->group_type]['_ending_chars'][$nextStmt];
			} else
			// If this group has transition state
			if(isset(FlageParser::$_context_transitions[$state->group_type])){
				$bt = &FlageParser::$_context_transitions[$state->group_type];
                // It can be a transition from prev state to new state (example {:function(prev_state=new_state)}
                //    or simple transition to new state {:fn()}, closing parenthesis is the transition
				if(!isset($bt[$nextStmt]) || is_array($bt[$nextStmt])){
					if(isset($bt[$prevStmt][$nextStmt])){
						$call_fn = $bt[$prevStmt][$nextStmt];
					}
				} else {
					if(isset($bt[$nextStmt])){
						$call_fn = $bt[$nextStmt];
					}
				}
			}
            // If there is no transition defined (function close, modifier, ...)
			if(!$call_fn){
                // Now we search for a transition from previous state to next state
                //   ex. 1+$a (number -> + -> var)
				if(!isset(FlageParser::$_transitions[$prevStmt][$nextStmt])){
					$s = $state;
					while($s){
                        /** @noinspection PhpUnusedLocalVariableInspection */
                        $state = $s;
						$s=$s->parent_state;
					}
					throw(new FlageException("Unexpected $nextStmt ".($prevStmt ? "after $prevStmt" : 'at the beginning'),$stack['line'],$stack['col']));
				}
                // Transition function
				$call_fn = FlageParser::$_transitions[$prevStmt][$nextStmt];
			}
            // if method doesn't exists debugging info will be shown
			if(! method_exists($this,$call_fn))
				$this->_printD( "-----".$state->entry_node->generate($this)."=====\n");

            // And finally call transition function
			$state = $this->{$call_fn}($state,$stack,$this->line,$this->col);
            // Returned means this state was used as ending for another group, so we process it again
			if($state->returned)
				$i--;
		}

        // We finished processing all states and now we should check if:
		$top = $state;
		$s = $state;
        // Current state has ending transaction
		if(!isset(FlageParser::$_transitions[$state->type][''])){
			throw(new FlageException("Unexpected end with '".$state->type."'",$this->line,$this->col));
		}
        // For all parent groups call finalizing transactions
		if($state->parent_state){
			do{
				$top = $s;
				
				$this->_printD( "Ending: ".$s->group_type." $s->type\n");
				$s->prev_type = $s->type;
                // If this group has no ending transaction then we should stop execution
				if(!isset(FlageParser::$_context_transitions[$state->group_type]['']))
					throw(new FlageException("Unknown group ending transition",$this->line,$this->col));
				$this->{FlageParser::$_context_transitions[$s->group_type]['']}($s,null);
				
				$s = $s->parent_state;
			} while($s);
		}
		
		return $top;
	}

    /**
     * During this replace we transform back all groups or process constant strings
     *
     * @param string[] $args
     * @return string
     * @throws FlageException
     */
	function replacing2($args){
        // If count < 3, then it is a constant string
		if(count($args)<3){
			$nlTmp = explode("\n",$args[0]);
			$nl = count($nlTmp) - 1;
            // Increment current position counter
			if($nl){
                // if newline then count from beginning
				$this->col = strlen(str_replace(ESCAPE_REPLACEMENT_VAR,'\{',array_pop($nlTmp)));
			} else {
                //print $args[0]."+++\n";
				$this->col += strlen(str_replace(ESCAPE_REPLACEMENT_VAR,'\{',$args[0]));
			}
            // Increment newline counter
			$this->line += $nl;
            // unmodified search string
			$ret = $args[0];
			// return preg_replace('/<\?(php|\s|=)/i','&lt;?\1',$ret);
            // we replace all php opening tags with print statements
			return preg_replace('/<\?(php|\s|=)/i','<?php print \'<?\';?>\1',$ret);
		}
        // end of line between two groups
		if($args[1]=="\n"){
			$this->line++;
			$this->col = 0;
			return $args[0];
		}
        // Groups processing
        // == Group id
		$id = $args[2];
        // Shortcut for verification if opening tag is required
        /** @noinspection SpellCheckingInspection */
        static $open_required = array(
			'else'=>'if'
			,'elseif'=>'if'
			,'elsefor'=>'for'
			,'elsewhile'=>'while'
			,'elseloop'=>'loop'
		);
        // Statements/EndStatements and what begin statement is required for them
        /** @noinspection SpellCheckingInspection */
        static $statements = array(
			'if'=>'if'
			,'for'=>'for'
			,'while'=>'while'
			,'loop'=>'loop'
			,'block'=>'block'
			,'block_body'=>'block_body'
			,'else'=>'if'
			,'elseif'=>'if'
			,'elsefor'=>'for'
			,'elsewhile'=>'while'
			,'elseloop'=>'loop'
		);
        static $pluggedInTags = array();
        // Group reference
		$group = &$this->groups[$id];

        // Line position backup
		$lineBk = $this->line;
        // Column position backup
		$colBk = $this->col;
        // Parent node is always empty, but children nodes will contain all the information
        /** @noinspection PhpUnusedLocalVariableInspection */
        $node = $parent_node = new FlageNode('',$this->line,$this->col);
        // Output string variable
		$out = '';
        // We should verify if this is not a comment group, and do nothing if it is a comment
		if(!$group->is_comment){
            // Entry size is the size of the beginning of the group. Ex. { OR {:
            // So incrementing column accordingly
			$this->col += $group->entry_size;
            // Nested groups are not allowed, so we throw an exception (ex {$s={:""}} )
			if($group->is_nested)
				throw(new FlageException('Nested brackets are not allowed for '.($group->is_var?'$':($group->is_close ? '/' : '')).$group->tag,$this->line));
            // group start tag {:if===tag<-if OR {:for===tag<-for
			$tag = $group->tag;
            if(!$group->is_var && !isset($statements[$tag])){
                // We try to include the tag from plugins
                $filename = $this->_dir_tag.$tag.'.php';
                if(file_exists($filename)){
                    /** @noinspection PhpIncludeInspection */
                    include($filename);
                    $tagClass = 'FlageTag'.ucfirst($tag);
                    if(!class_exists($tagClass,false)){
                        throw new FlageException("Tag file exists but tag class '$tagClass' not defined",$this->line,$this->col);
                    }
                    //eval('$tagObject=new '.$tagClass.'()');
                    $statements[$tag] = $tag;
                    $pluggedInTags[$tag] = $tagClass;
                    if(method_exists($tagClass,'openRequired')){
                        /** @noinspection PhpUndefinedMethodInspection */
                        $openTag = $tagClass::openRequired($tag);
                        if($openTag)
                            $open_required[$tag] = $openTag;
                    }
                }
            }

            // We verify that this is not a variable and this is a supported statement (if, for, while, elseif, loop, else, otherwise)
			if(!$group->is_var && isset($statements[$tag])){
                // Perform action if this is a group closure
				if($group->is_close){
                    // extract current opened tag
					$opened_tag = array_pop($this->_opened);
                    // if opened tag != tag, them we have a problem
					if($opened_tag != $tag){
						if($opened_tag)
							throw(new FlageException("Unexpected '/$tag', expecting '/$opened_tag'",$this->line,$this->col));
						else
							throw(new FlageException("Unexpected '/$tag', no matching starting tag found",$this->line,$this->col));
					}
                    // Display closing tag php representation
					switch($tag){
						case 'if':
							$out = '<?php endif; ?>'.PHP_ENDING_TAG_STRING;
							break;
						case 'while':
							$out = '<?php endwhile; ?>'.PHP_ENDING_TAG_STRING;
							break;
						case 'for':
							$out = '<?php endfor; ?>'.PHP_ENDING_TAG_STRING;
							break;
						case 'loop':
							$out = '<?php endforeach; ?>'.PHP_ENDING_TAG_STRING;
							break;
                        case 'block':
                        case 'block_body':
                        default:
                            $out = "#__TAG_EXTRACT_END__".$this->block->getBlockId()."#";
                            $this->block = array_pop($this->blocksStack);
                            break;
                    }
				} else { // Not a closing tag
                    // Verify if current tag expects an opened tag, ex. elseif, otherwise
                    //    And define current opened tag
					if(isset($open_required[$tag])){
						$opened_tag = array_pop($this->_opened);
                        // Open required is an array containing association of tag->opened tag
						if($opened_tag!=$open_required[$tag])
							throw(new FlageException("Unexpected '$tag', no matching starting tag found, expecting '".$open_required[$tag]."'",$this->line,$this->col));
						array_push($this->_opened,$opened_tag);
					} else {
						array_push($this->_opened,$tag);
                    }

                    // Increment by tag length
					$this->col += strlen($tag);
                    // Tag decision and tokenize process
					switch($tag){
						case 'else':
							$out .= '<?php else: ?>'.PHP_ENDING_TAG_STRING;
							break;
						case 'if':
						case 'elseif':
						case 'while':
                            // tag_rest will contain all left-overs ex. {if 1==2}
                            // if it is empty then throw an error, as it is required
							if(!trim($group->tag_rest))
								throw(new FlageException("Statement is required for tag '$tag'",$this->line,$this->col));
                            // Display php tag
							$out = '<?php '.$tag.'(';
                            // Begin tokenize process, which will transform string representation to a parsed one
							$state = $this->_tokenize($group->tag_rest);
                            // Now generate php string
							$out .= $state->entry_node->generate($this);
                            // And close this tag
							$out .= '): ?>'.PHP_ENDING_TAG_STRING;
							break;
						case 'for':
							$out = '<?php for(';
                            // There are 2 possible "for" statements
                            //    statement;statement;statement
                            //    statement..statement
							if(preg_match('/^([^;]*);([^;]+);([^;]*)$/si',$group->tag_rest,$matches)){
								$line_bk = $this->line;
								$col_bk = $this->col;

								$add = '';
                                // process all 3 statements
								for($i=0; $i<3; $i++){
									$data = $matches[$i+1];

									$out .= $add.$this->_tokenize($data)->entry_node->generate($this);

									$nl_data = explode("\n",$data);
									$nl = count($nl_data)-1;
										$this->col = $col_bk;
									if($nl){
										$this->col = 0;
									}
									$this->line = $line_bk + $nl + ($i>0);
									$this->col += strlen(array_pop($nl_data));
									$add = ';';
								}
							} else if(preg_match('/^(\s*\$@?([a-zA-Z_][a-zA-Z0-9_]*)\s*=)(.+)$/si',$group->tag_rest,$matches)){
                                // matches
                                // (1) - variable definition part (including whitespaces and =)
                                // (2) - variable name
                                // (3) - variable definition
								$var = $matches[2];

                                // newline/columns increment
									$nl_data = explode("\n",$matches[1]);
									$nl = count($nl_data)-1;
									if($nl){
										$this->col = 0;
									}
									$this->line += $nl;
									$this->col += strlen(array_pop($nl_data));

                                // Now we initialize new State, which is responsible for "for" group
								$state = new FlageState(null,$this->line,$this->col);
								$state->group_type="for";
								$state->for_state=null;
                                // Now we tokenize declaration
								$state2 = $this->_tokenize($matches[3],$state);

                                // And we expect that it will return 2 parts. for_next means that we are in second part of ..
								if($state2->group_type != 'for_next' || !$state2->entry_node->next){
									throw(new FlageException("'$tag' is expecting next (..) statement",$this->line,$this->col));
								}
                                // And finally build for statements
								$out .= '$'.$var.'='.$state2->for_state->entry_node->generate($this);
								$out .= ';$'.$var.'<='.$state2->entry_node->generate($this);
								$out .= ';$'.$var.'++';
							} else {
                                // no idea how to process for statement
								throw(new FlageException("Unknown '$tag' format",$this->line,$this->col));
							}
							$out .= '): ?>'.PHP_ENDING_TAG_STRING;
							break;
						case 'loop':
                            // Loop is advanced version of foreach
							if(preg_match(
                                '/^'
                                // (1) - callback definition "$k,$v callback_function("
                                .'('
                                    // (2) - key/value variables
                                    .'(\s*\$?'
                                        // (3) - key OR value variable
                                        .'([a-zA-Z_][a-zA-Z0-9_]*)'
                                        .'\s*'
                                        // (4) - value variable which is optional and if not defined then (3) is value
                                        .'(,\s*\$?'
                                            // (5) - variable name
                                            .'([a-zA-Z_][a-zA-Z0-9_]*)'
                                        .')?'
                                    .')'
                                    // (6) - callback group
                                    .'(\s*'
                                        // (7) - callback function name
                                        .'([a-zA-Z_][a-zA-Z0-9_]*)'
                                        .'\s*\('
                                    .')'
                                .')'
                                // (8) - callback arguments
                                .'(.*?)'
                                // (9) - closing loop and removing all whitespaces
                                .'(\)?)\s*$/si'
                                ,$group->tag_rest
                                ,$matches
                            )){
                                // There is an error, loop was never closed, append all newlines and throw the error
								if($matches[9]!=')'){
									$nl_data = explode("\n",$group->tag_rest);
									$nl = count($nl_data)-1;
									if($nl){
										$this->col = 0;
									}
									$this->line += $nl;
									$this->col += strlen(array_pop($nl_data));
									throw(new FlageException("'$tag' is expecting to be ending with ')'",$this->line,$this->col));
								}

                                // Generate new state, which is counted as a function
								$state2 = new FlageState(null,$this->line,$this->col,false);
                                // New function node
								$state2->node->next
									= new FlageNodeFunction(null,$this->line,$this->col,false);

                                // Previous node of the function is the created state
								$state2->node->next->prev = $state2->node;
                                // parent node is argument node, as we do not need function name
								$state2->parent_node = $state2->node = $state2->node->next->arguments->nextArgumentNode($this->line,$this->col);
								
								$state2->type = '';
                                // Group type is a function
								$state2->group_type = 'function';
                                // We need to tokenize only arguments
								$state = $this->_tokenize($matches[8],$state2);

                                $cbName = $matches[7];

                                if($cbName=='each'){
                                    //$fn_name = $cbName;
                                    //print_r($state->entry_node->next->arguments->generate($this,true));
                                    //print("====___".$state->entry_node->generate($this)."___===");
                                    $out = '<?php foreach('.$state->entry_node->next->arguments->generate($this,true).' as $'.$matches[3].($matches[5] ? '=>$'.$matches[5] : '').'): ?>'.PHP_ENDING_TAG_STRING;
                                } else {
                                    // expected callback function name
                                    $fn_name = 'flage_cb_'.$cbName;
                                    // Filename to load
                                    $filename = $this->_dir_callback.$cbName.'.php';
                                    // This function wasn't required yet
                                    if(!function_exists($fn_name)){
                                        if(!file_exists($filename)){
                                            throw(new FlageException("Callback '".$cbName."' doesn't exists in ".$filename,$this->line,$this->col));
                                        }
                                        // Include the file containing this function declaration
                                        /** @noinspection PhpIncludeInspection */
                                        include_once($filename);
                                        // make sure that function file contains function declaration
                                        if(!function_exists($fn_name)){
                                            throw(new FlageException("Callback '".$fn_name."' is not initialized in ".$filename,$this->line,$this->col));
                                        }
                                        // Add this file to the list of required files
                                        $this->_used_functions[$filename] = array('path'=>$this->_dir_callback,'function'=>$fn_name,'name'=>$cbName);
                                    }

                                    // And now generate foreach loop
                                    $out = '<?php foreach('.$fn_name.$state->entry_node->generate($this).' as $'.$matches[3].($matches[5] ? '=>$'.$matches[5] : '').'): ?>'.PHP_ENDING_TAG_STRING;
                                }
							} else {
								throw(new FlageException("Unknown '$tag' format",$this->line,$this->col));
							}
							break;
                        case 'block':
                        case 'block_body':
                            if($this->block)
                                array_push($this->blocksStack,$this->block);
                            if($tag=='block_body'){
                                if(count( $this->_opened )>1)
                                    throw new FlageException("'$tag' is not allowed to be nested inside another tag",$lineBk,$colBk);
                                $this->block = new FlageParserBlockBody($this->getNextBlockId(),$lineBk,$colBk);
                            } else
                                $this->block = new FlageParserBlock($this->getNextBlockId(),$lineBk,$colBk);
                            //$this->block->line = $lineBk;//$this->line;
                            //$this->block->col = $colBk;//$this->col;
                            $this->blocks[$this->block->getBlockId()] = $this->block;

                            $this->block->tagInit($this, $tag,$group->tag_rest);

                            /*
                            $this->block->arguments = $this->_tokenize_named_arguments($group->tag_rest);

                            $block_name = $this->block->getName();
                            if($tag=='block_body'){
                                if(isset($this->definedBodyBlock[$block_name]))
                                    throw new FlageException("Not allowed multiple '$tag' declarations in same file",$this->definedBodyBlock[$block_name]->line,$this->definedBodyBlock[$block_name]->col);
                                $this->definedBodyBlock[$block_name] = $this->block;
                            }

                            $block_name_prefix = '';
                            do{
                                $block_filename = $block_name_prefix.$block_name;
                                if(isset($this->existingBlockNames[$block_filename]))
                                    $block_filename='';
                                $block_name_prefix++;
                            } while(!$block_filename);
                            $info = $this->getFlage()->getInfo('block:'.$block_filename);
                            $this->block->setBlockCompiledName($info['compiled_filename']);
                            $this->existingBlockNames[$block_filename] = $block_filename;
                            */

                            $out = "#__TAG_EXTRACT_START__".$this->block->getBlockId()."#";
                            break;
						default:
                            if(isset($pluggedInTags[$tag])){
                                if($this->block)
                                    array_push($this->blocksStack,$this->block);
                                /** @var $tagObject FlageTag */
                                $this->block = $tagObject = new $pluggedInTags[$tag]($this->getNextBlockId(),$lineBk,$colBk);
                                $this->blocks[$this->block->getBlockId()] = $this->block;
                                $tagObject->tagInit($this, $tag, $group->tag_rest);
                                $out = "#__TAG_EXTRACT_START__".$this->block->getBlockId()."#";

                                if($tagObject->isSelfClosing()){
                                    // extract current opened tag
                                    /** @noinspection PhpUnusedLocalVariableInspection */
                                    $opened_tag = array_pop($this->_opened);
                                    $out .= "#__TAG_EXTRACT_END__".$this->block->getBlockId()."#";
                                    $this->block = array_pop($this->blocksStack);
                                }
                            } else
							    throw(new FlageException("Unknown tag '$tag'",$this->line,$this->col));
							break;
					}
				}
				
			} else {
                // This is a variable or an unregistered tag, so we proceed with tokenize process
				$state = $this->_tokenize($group->data);

                // Output string should begin with php opening tag
				$out = '<?php ';
                // If not an assignment, then we should print this result
                // TODO: with new version, once confirmed <?= usage, replace "<?php print" with <?=
				if(!$state->is_assignment)
					$out .= 'print ';
                // Generate string from generated tokens
				$out .= $state->entry_node->generate($this).';';
                // And ofc close php statement
				$out .= ' ?>';
                $out .= PHP_ENDING_TAG_STRING;
			}
		}

        // The best way to set the line is restoring original values and adding them group size
        //   This way if one of the calculation processes went wrong during tokenize process
        //   , it will not break line pointer and always will remain correct

		// Restore line count variable
        $this->line = $lineBk;
        // Restore column count variable
		$this->col = $colBk;
        // If new lines are added, then nullify column counter and add newlines
		if($group->new_lines){
			$this->line+=$group->new_lines;
			$this->col=0;
		}
        // Add to column counter last line size
		$this->col += $group->nl_size;

		return $out;
	}

    /**
     *  Function which receives as input list of arguments extracted from regex and will return group id
     *     while saving all group information into an array
     *
     * @param string[] $args
     * @return string
     */
	function replacing($args){
		$id = $this->replacement;
		$ret = '#r_group_'.$id.'#';
		$nlTmp=explode("\n",$args[0]);
		$this->groups[$id] = new FlageGroup(array(
			'data' => ($args[1][0]==':' ? substr($args[1],1) : $args[1] ) . $args[4]
			,'tag' => $args[5]
			,'is_var' => $args[2]=='$' || $args[1]=='$' || $args[2]=='@' || $args[1]=='@'
			,'suppress' => $args[2]=='@' || $args[1]=='@'
			,'is_close' => $args[2]=='/' || $args[1]=='/'
			,'new_lines' => count($nlTmp)-1//count(explode("\n",$args[4]))-1
			,'is_nested' => $args[10]=='{'
			,'nl_size' => strlen(array_pop($nlTmp))
			,'entry_size' => 1 + (int)($args[1][0]==':')
			,'tag_rest' => $args[7]
		));

		$this->replacement++;
		return $ret;
	}

    function tags_replacing($args){
        $this->block = $block = $this->blocks[$args[1]];
        array_push($this->blocksStack,$this->block);
        //print_r($this->blocks[$args[1]]);
        $blockBody = preg_replace_callback(TAG_EXTRACT_REGEX, array($this, 'tags_replacing'),$args[2]);
        //print "===".print_r($args,true)."---\n";
        $this->block = array_pop($this->blocksStack);

        return $block->replaceContent($this, $blockBody);
    }

    public $existingBlockNames = array();
    protected $blocksStack = array();
    /**
     * @var FlageParserBlock
     */
    protected $block = null;
    /**
     * @var FlageParserBlock[]
     */
    protected $blocks = array();
    private $currentBlockId = 0;
    function getNextBlockId(){
        $currentBlockId = $this->currentBlockId;
        $this->currentBlockId++;
        return $currentBlockId;
    }
};

abstract class FlageTag {
    public $arguments = array();
    protected $blockId;// = null;

    /**
     * @var null|int
     */
    public $line = null;
    /**
     * @var null|int
     */
    public $col = null;

    /**
     * @param string|int $blockId
     * @param int $line
     * @param int $col
     */
    public function __construct($blockId,$line,$col){
        $this->blockId = $blockId;
        $this->line = $line;
        $this->col = $col;
    }

    /**
     * @return string
     */
    public function getBlockId(){
        return $this->blockId;
    }

    /**
     * @param FlageParser $parser
     * @param string $tagBody
     * @return string
     */
    public abstract function replaceContent($parser, $tagBody);

    /**
     * @param FlageParser $parser
     * @param string $tag
     * @param string $tagRest
     */
    public abstract function tagInit($parser, $tag, $tagRest);
    //public abstract function onTagStart();

    public function isSelfClosing(){return false;}
}
class FlageParserBlock extends FlageTag {
    protected $blockCompiledName = '';
    protected $blockName = null;

    /**
     * @param string $name
     */
    public function setName($name){
        $this->blockName = $name;
    }

    /**
     * @return string
     * @throws FlageException
     */
    public function getName(){
        if($this->blockName) return $this->blockName;
        if(isset($this->arguments['name'])) {
            $blockName = $this->arguments['name'];
            eval('$blockName='.$blockName.';');
            $this->blockName = $blockName;
            return $this->blockName;
        }
        throw new FlageException('name is required for block tag',$this->line, $this->col);
    }

    /**
     * @return string
     */
    public function getBlockCompiledName()
    {
        return $this->blockCompiledName;
    }

    /**
     * @param string $blockCompiledName
     */
    public function setBlockCompiledName($blockCompiledName)
    {
        $this->blockCompiledName = $blockCompiledName;
    }

    public function replaceContent($parser, $blockBody)
    {
        //$this->getFlage()->storeCompiled('block:'.$block->arguments['name']);
        $parser->getFlage()->storeCompiled($this->getBlockCompiledName(), $blockBody);
        $blockArguments = array();
        foreach($this->arguments as $argName=>$argValue)
            $blockArguments[] = "'".str_replace(array("'",'\\'),array("\\'",'\\\\'),$argName)."'" . '=>' . $argValue;
        //return '<?php $block=$__h->_startBlock(\''.$block->getBlockCompiledName().'\',array('.implode(',',$blockArguments).')); include(\''.( $block->getBlockCompiledName() ).'\'); $block=$__h->_endBlock(); ?'.'>';
        $out = '<?php $block=$__h->_startBlock(\''.$this->getName().'\',\''.$this->getBlockCompiledName().'\',array('.implode(',',$blockArguments).'),isset($block) ? $block : null,\''.$this->line.'\',\''.$this->col.'\'); '
        //.'include( $block->getBlockCompiledName() ); '
        . (
        !$parser->getFlage()->isDisableCache() ?
            'include( $block->getBlockCompiledName() ); ' :
            'eval("?".">". $__h->loadCompiled($block->getBlockCompiledName()) ."<?php;"); '
        )
        .'$block=$__h->_endBlock(); ?>';
        return $out;
    }

    public function tagInit($parser, $tag, $tagRest)
    {
        $this->arguments = $parser->_tokenize_named_arguments($tagRest);

        $block_name = $parser->getFlage()->getCurrentInfo()['compiled_filename'].'-'.$this->getName();

        $block_name_prefix = '';
        do{
            $block_filename = $block_name_prefix.$block_name;
            if(isset($parser->existingBlockNames[$block_filename]))
                $block_filename='';
            $block_name_prefix++;
        } while(!$block_filename);
        $info = $parser->getFlage()->getInfo('block:'.$block_filename);
        $this->setBlockCompiledName($info['compiled_filename']);
        $parser->existingBlockNames[$block_filename] = $block_filename;
    }
}

class FlageParserBlockBody extends FlageParserBlock {
    public function replaceContent($parser, $blockBody)
    {
        $parser->getFlage()->storeCompiled($this->getBlockCompiledName(), $blockBody);
        $blockArguments = array();
        foreach($this->arguments as $argName=>$argValue)
            $blockArguments[] = "'".str_replace(array("'",'\\'),array("\\'",'\\\\'),$argName)."'" . '=>' . $argValue;
        $parser->prependInjectedCode[] = '<?php $__h->_addBlockBody(\''.$this->getName().'\',\''.$this->getBlockCompiledName().'\',array('.implode(',',$blockArguments).'),\''.$this->line.'\',\''.$this->col.'\'); ?>';

        return '';
    }
    public function tagInit($parser, $tag, $tagRest){
        parent::tagInit($parser, $tag, $tagRest);
        $block_name = $this->getName();
        if(isset($parser->definedBodyBlock[$block_name]))
            throw new FlageException("Not allowed multiple '$tag' declarations in same file",$parser->definedBodyBlock[$block_name]->line,$parser->definedBodyBlock[$block_name]->col);
        $parser->definedBodyBlock[$block_name] = $this;
    }
}