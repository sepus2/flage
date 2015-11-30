<?php

if(!defined('__DIR__')) define('__DIR__',dirname(__FILE__));

define('__PARENT_DIR__',dirname(dirname(__FILE__)));

class Flage {
	protected $_current_dir = '';
	protected $_dir_templates = 'templates';
	protected $_absolute_dir_templates = __PARENT_DIR__.'/templates';
    protected $_dir_compiled = 'templates_c';
    protected $_dir_plugins = 'plugins';
    protected $_dir_callback = 'plugins/callbacks';
    protected $_dir_modifier = 'plugins/modifiers';
    protected $_dir_function = 'plugins/functions';
    protected $_file_hash_salt = '';
    protected $forceRecompile = false;
    protected $disableCache = false;

    protected $rootPath = __PARENT_DIR__;

    protected $data = array();

    protected static $globalData = array();
    /**
     * This is a variable responsible for white listing function
     *    - it can be a string '*' allowing all functions or '' allowing none
     *        ex *
     *    - it can be string array containing allowed functions
     *        ex array('print_r')
     *    - it can be array of string array containing allowed functions for each source
     *        ex array('php'=>array('print_r'))111
     *    - it can be callable (function, string)
     *        ex function($functionName,$source){ return $functionName=='print_r' && $source=='php'; }
     *    - it can be callable array for each source
     *        ex array('php'=>function($functionName){ return $functionName=='print_r'; })
     *
     * @var string|string[]|string[][]|closure
     */
    public $allowedFunctions = '*';
    /**
     * @var string|string[]|closure
     */
    public $blacklistedVariables = array('__h','flage');

	function Flage(){
	}
	function _file_read($file){
		return file_get_contents($this->getFilePath($file));
	}
	function _get_data($data){
		return $data;
	}
    function getTemplatePath(){
        return $this->_dir_templates;
    }
    function setTemplatePath($path){
        $this->_dir_templates = $path;
        if($path[0]!='/' && $path[0]!='\\' && $path[1]!=':'){
            $this->_absolute_dir_templates = __DIR__.DIRECTORY_SEPARATOR.$path;
        } else {
            $this->_absolute_dir_templates = $path;
        }
        $this->_absolute_dir_templates = '/'.$this->_normalize_path($this->_absolute_dir_templates);
        return $this;
    }
    function getAbsoluteTemplatePath(){
        return $this->_absolute_dir_templates;//_dir_templates;
    }
    function getFilePath($path){
        return $this->getAbsoluteTemplatePath().$this->_normalize_path($path);
    }
    function getCurrentDir(){
        return $this->_current_dir;// ? $this->_current_dir : $this->_dir_templates;
    }
    function getAbsoluteCurrentDir(){
        return $this->_current_dir ? __DIR__.$this->_current_dir : $this->getAbsoluteTemplatePath();
    }
    function _normalize_path($path,$allowRelativeOffset=false){
        $path2 = str_replace('\\','\\\\',$path);
        if(!($path2[0]=='/' || ($path2[1]==':' && $path2[2]=='/'))){
            $path2 = $this->getCurrentDir().'/'.$path2;
        }
        $path_array = explode('/',$path2);
        $return_array = array();
        $root_array = array();
        //if($path[0]=='/' || $path[1]==':')
        //    $return_path = $this->_dir_templates;
        //else
        //    $return_path = $this->getCurrentDir();

        foreach($path_array as $path_section){
            if($path_section == '..') {
                if(array_pop($return_array)==null)
                    array_push($root_array,$path_section);
            }
            if($path_section != '.' && $path_section != '')
                array_push($return_array,$path_section);
        }
        $return_path = DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR,$return_array);
        if($allowRelativeOffset)
            $return_path = DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR,$root_array).$return_path;
        return $return_path;
    }
	function _file_get_info($file){
        $filename = $file;
		//if($file[0]!='/' && $file[1]!=':')
		//	$filename = $this->_dir_templates.'/'.$file;
		//$fName = $this->_file_hash_salt.md5($filename).'.php';
        $fName = $this->generateCacheName($file);
		$template = __PARENT_DIR__.'/'.$this->_dir_compiled.'/'.$fName;
		if(file_exists($template)){
			return array(
				'modified'=>filemtime($this->getFilePath($filename)) > filemtime($template)
				,'filename'=>$filename
				,'compiled_filename'=>$template
			);
		}
		return array(
			'original'=>0
			,'filename'=>$filename
			,'compiled_filename'=>$template
			,'modified'=>true
		);
	}
    function generateCacheName($file){
        //$filename = $file;
        //if($file[0]!='/' && $file[1]!=':')
        //    $filename = $this->_dir_templates.'/'.$file;
        //$fName = $this->_file_hash_salt.md5($filename).'.php';
        return $this->_file_hash_salt.md5($file).'.php';
    }

    private $cachedSourceInfo = array();

    function _block_get_info($blockName){
        $info = $this->getCurrentInfo();
        $filename = $blockName.'-'.$info['compiled_filename'];
        //if($file[0]!='/' && $file[1]!=':')
        //	$filename = $this->_dir_templates.'/'.$file;
        //$fName = $this->_file_hash_salt.md5($filename).'.php';
        $fName = $this->generateCacheName($filename);
        $template = __PARENT_DIR__.'/'.$this->_dir_compiled.'/'.$fName;
        return array(
            'original'=>0
            ,'filename'=>$filename
            ,'compiled_filename'=>$template
            ,'modified'=>true
        );

    }
    function getSourceInfo($source){
        if(!isset($this->cachedSourceInfo[$source])) {
            static $protocols = array(
                'file' => array(
                    'info' => '_file_get_info'
                , 'get_data' => '_file_read'
                )
            , 'data' => array(
                    'info' => '_data_get_info'
                , 'get_data' => '_get_data'
                )
            , 'block' => array(
                    'info' => '_block_get_info'
                , 'get_data' => '_block_get_data'
                )
            );

            $pos = strpos($source, ':');
            $protocol = '';
            $file = '';
            if ($pos) {
                $protocol = substr($source, 0, $pos);
                if (isset($protocols[$protocol])) {
                    $file = substr($source, $pos + 1);
                } else {
                    $protocol = '';
                }
            }
            if (!$protocol) {
                $protocol = 'file';
                $file = $source;
            }
            //$tmp = explode(':',$source);
            //$protocol = array_pop($tmp);
            //$file = implode(':',$tmp);

            //if(!isset($protocols[$protocol])){
            //    $file = $source;
            //    $protocol = 'file';
            //}

            $sourceInfo = $protocols[$protocol];
            $sourceInfo['source_data'] = $file;
            $sourceInfo['source_protocol'] = $protocol;

            $this->cachedSourceInfo[$source] = $sourceInfo;
        }
        return $this->cachedSourceInfo[$source];
    }
    private $cachedInfo = array();
    function getInfo($source){
        if(!isset($this->cachedInfo[$source])){
            $source_info = $this->getSourceInfo($source);
            $this->cachedInfo[$source] = $this->{$source_info['info']}($source_info['source_data'],$source);;
        }
        return $this->cachedInfo[$source];
    }
    protected $currentInfo;
    protected function compile($source){
        /** @noinspection PhpUnusedLocalVariableInspection */
        $__h = $this;

        $source_info = $this->getSourceInfo($source);

        $info = $this->{$source_info['info']}($source_info['source_data'],$source);

        if($info['modified'] || $this->forceRecompile || $this->disableCache){
            $this->setCurrentInfo($info);
            $_data = $this->{$source_info['get_data']}($info['filename']);

            require_once("FlageParser.parser.php");
            $parser = new FlageParser();
            $parser->setFlage($this);

            try{
                $__data = $parser->Parse($_data);
            } catch(FlageException $e) {
                if(!$e->isFileDefined())
                    $e->setFile($source);
                throw $e;
            }

            $this->storeCompiled($info['compiled_filename'], $__data);

        }
    }
    function storeCompiledSource($source,$content){
        $info = $this->getInfo($source);

        $this->storeCompiled($info['compiled_filename'],$content);
    }
    function storeCompiled($filename,$content){
        if(!$this->disableCache){
            if(!file_exists(__PARENT_DIR__.'/'.$this->_dir_compiled)){
                mkdir(__PARENT_DIR__.'/'.$this->_dir_compiled);
                chmod(__PARENT_DIR__.'/'.$this->_dir_compiled,0777);
            }

            //$source_info = $this->getInfo($source);
            //print_r($source_info);
            file_put_contents($filename,$content);
            chmod($filename,0666);
        } else {
            $this->data[$filename] = $content;
        }
    }
    public function getCachedData($compiled_filename){
        return $this->data[$compiled_filename];
    }
    protected function loadCompiled($source){
        $info = $this->getInfo($source);
        /** @noinspection PhpUnusedLocalVariableInspection */
        $__h = $this;
        ob_start();
        try {
            if(!$this->disableCache) {
                /** @noinspection PhpIncludeInspection */
                include($info['compiled_filename']);
            } else {
                eval('?'.'>'.$this->getCachedData($info['compiled_filename']).'<?php;');
            }
        } catch(FlageException $e) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $__data = ob_get_clean();
            //$data = $__data;
            if(!$e->isFileDefined())
                $e->setFile($source);
            throw($e);
        }
        $data = ob_get_clean();
        return $data;
    }
	public function Parse($source){
        $this->compile($source);

        $data = $this->loadCompiled($source);
		//$__h = new FlageHelper();
        /** @noinspection PhpUnusedLocalVariableInspection */
        /*$__h = $this;
		$data = null;
		try {

			//include($info['template']);
			
			ob_start();
			try {
                $stdObject = $this;
                include($info['template']);
			} catch(FlageException $e) {
				$__data = ob_get_clean();
				$data = $__data;
                if(!$e->isFileDefined())
                    $e->setFile($source);
				throw($e);
			}
			$data = ob_get_clean();
		} catch(FlageException $e) {
			print $e;
		} catch(Exception $e) {
            print $e;
        }
        */

		
		return $data;
	}





    public $usedFn = array();


    /**
     * @param string $functionName
     * @param string $functionType modifier|function|callback
     * @return string[]|null
     */
    public function getFlageFunctionDeclaration($functionName,$functionType){
        $pathName = '';
        $fn_mod = '';
        switch($functionType){
            case 'modifier':
                $fn_mod = 'modifier_';
                $pathName='modifiers';
                break;
            case 'function':
                $fn_mod = 'function_';
                $pathName='functions';
                break;
            case 'callback':
                $fn_mod = 'cb_';
                $pathName='callbacks';
                break;
        }

        $callableFunctionName = 'flage_'.$fn_mod.$functionName;

        $filePath = 'plugins/'.$pathName.'/'.$functionName.'.php';
        if(!file_exists($filePath)){
            if($functionType=='modifier')
                return $this->getFlageFunctionDeclaration($functionName,'function');
            return null;
        }
        return array($filePath,$callableFunctionName,$functionType=='modifier');
    }

    /**
     * @param string $functionName
     * @param string $functionType
     * @param int $line
     * @param int $col
     * @return string[]
     * @throws FlageException
     */
    public function getFunctionDefinition($functionName,$functionType,$line,$col){
        $lastUsedFn=&$usedFn[$functionName];
        if(!$lastUsedFn){
            $flageFunctionDeclaration = $this->getFlageFunctionDeclaration($functionName,$functionType);
            $callerType = 'flage';
            if($flageFunctionDeclaration){
                $lastUsedFn = array(
                    'function_name'=>$flageFunctionDeclaration[1]
                ,'function_type'=>$callerType
                ,'is_modifier'=>$flageFunctionDeclaration[2]
                ,'path'=>$flageFunctionDeclaration[0]
                );
                /** @noinspection PhpIncludeInspection */
                require_once($lastUsedFn['path']);
                if(!function_exists($lastUsedFn['function_name'])){
                    throw new FlageException('Plugin for '.$functionName.' is defined, but function \''.$lastUsedFn['function_name'].'\' is missing',$line,$col);
                }
            } elseif(function_exists($functionName)){
                $callerType = 'php';
                $lastUsedFn = array(
                    'function_name'=>$functionName
                ,'function_type'=>$callerType
                ,'is_modifier'=>false
                ,'path'=>null
                );
            }

            if($lastUsedFn){
                if(!$this->verifyFunctionAllowed($functionName,$callerType)){
                    throw new FlageException("Function '$functionName' is not allowed'",$line,$col);
                }
            } else{
                throw new FlageException("Function '$functionName' doesn't exists",$line,$col);
            }
        }

        return $lastUsedFn;
    }
    public function verifyFunctionAllowed($functionName,$functionSource){
        if($this->allowedFunctions=='*')
            return true;
        if(is_callable( $this->allowedFunctions))
            return call_user_func($this->allowedFunctions,$functionName,$functionSource);
        if(is_array($this->allowedFunctions)){
            if(isset($this->allowedFunctions[$functionSource])){
                if(is_callable( $this->allowedFunctions[$functionSource]))
                    return call_user_func($this->allowedFunctions[$functionSource],$functionName,$functionSource);
                return $this->allowedFunctions[$functionSource]=='*' || in_array($functionName, $this->allowedFunctions[$functionSource]);
            }
            return in_array($functionName, $this->allowedFunctions);
        }
        return false;
    }
    public function verifyVariableBlacklisted($variable){
        if(is_callable( $this->blacklistedVariables))
            return call_user_func($this->blacklistedVariables,$variable);
        if(is_array($this->blacklistedVariables)){
            return in_array($variable, $this->blacklistedVariables);
        }
        return false;
    }

    public function test($arg0){
        return $arg0;
    }

    /**
     * @param string $fn
     * @param mixed[] $arguments
     * @return mixed
     * @throws FlageException
     */
    public function c($fn,$arguments)
    {
        $line = $arguments[1];
        $col = $arguments[2];
        $modifier = $arguments[3];
        $args = $arguments[0];

        if (!$fn)
            throw new FlageException("Function name is required", $line, $col);

        // we detect cached function
        $_lUsedFn =& $usedFn[$fn];
        //print "Arguments: "; print_r($arguments);
        $fn_mod = $modifier ? 'modifier' : 'function';
        if (!$_lUsedFn) {
            $function_name = 'flage_' . $fn_mod . '_' . $fn;
            $function_type = 'flage';
            if (!function_exists($function_name)) {
                $fName = 'plugins/functions/' . $fn . '.php';
                if ($modifier)
                    $fName = 'plugins/modifiers/' . $fn . '.php';
                if (file_exists($fName)) {
                    /** @noinspection PhpIncludeInspection */
                    require_once($fName);
                    if (!function_exists($function_name)) {
                        throw new FlageException('Plugin for ' . $fn . ' is defined, but function \'' . $function_name . '\' is missing', $line, $col);
                    }

                } else if (function_exists($fn)) {
                    // TODO: perform security checks for allowed functions and throw an error if is not in list
                    $function_name = $fn;
                    $function_type = 'php';
                } else {
                    throw new FlageException($fn_mod . ' \'' . $fn . '\' was not found in plugins nor in php defined functions', $line, $col);
                }
            }
            $_lUsedFn = array('function_name' => $function_name, 'function_type' => $function_type, 'modifier' => $modifier);
        }
        if ($_lUsedFn['function_type'] == 'php') {
            return call_user_func_array($_lUsedFn['function_name'], $args);
        } else {
            if ($_lUsedFn['modifier']) {
                $arg0 = array_shift($args);
                //print 'Function: '.$_lUsedFn['function_name']."\n";
                //print_r(array($arg0,$args));
                return call_user_func($_lUsedFn['function_name'], $this, $arg0, $args, $line, $col);
            } else
                return call_user_func($_lUsedFn['function_name'], $this, $args, $line, $col);
        }
    }

    /**
     * @return boolean
     */
    public function isForceRecompile()
    {
        return $this->forceRecompile;
    }

    /**
     * @param boolean $forceRecompile
     */
    public function setForceRecompile($forceRecompile)
    {
        $this->forceRecompile = $forceRecompile;
    }

    /**
     * @return mixed
     */
    public function getCurrentInfo()
    {
        return $this->currentInfo;
    }

    /**
     * @param mixed $currentInfo
     */
    public function setCurrentInfo($currentInfo)
    {
        $this->currentInfo = $currentInfo;
    }

    protected $blocksStack = array();
    protected $runningBlocks = array();
    protected $runningBlockName = array();
    public function _startBlock($name,$generatedName,$arguments, /** @noinspection PhpUnusedParameterInspection */
                                $currentBlock,$line,$col){
        $blockToRun = $block = new FlageBlock($name,$generatedName,$arguments);
        $block->line = $line;
        $block->col = $col;

        $blockName = $block->getName();
        if(isset($this->firstBlockBody[$blockName])){
            $blockToRun = $this->firstBlockBody[$blockName];
            $this->blockBodies[$blockName]->parent = $block;
        }
        return $this->_pushBlock($blockToRun);
    }

    /**
     * @param FlageBlock $block
     * @param array $arguments
     * @return FlageBlock
     */
    public function _pushBlock($block,$arguments=array()){//},$line,$col){
        array_push($block->argumentsStack, $block->arguments);

        /** @var FlageBlock $currentRunningBlock */
        $currentRunningBlock = count($this->runningBlocks) ? $this->runningBlocks[count($this->runningBlocks)-1] : null;

        if(isset($arguments['arguments'])){
            $args = $arguments['arguments'];
            unset($arguments['arguments']);
            $arguments = array_merge($arguments,$args);
        }
        if($currentRunningBlock && $currentRunningBlock->getName()==$block->getName()){
            $arguments = array_merge($currentRunningBlock->arguments,$arguments);
        }
        unset($arguments['name']);
        unset($arguments['block']);
        unset($arguments['id']);

        $newBlock = clone $block;
        $newBlock->currentInstance++;

        if($arguments)
            $newBlock->arguments = array_merge($newBlock->arguments, $arguments);

        array_push($this->runningBlocks,$newBlock);
        if($currentRunningBlock)
            array_push($this->blocksStack,$currentRunningBlock);
        return $newBlock;
    }

    public function _endBlock(){
        array_pop($this->runningBlocks);
        $block = array_pop($this->blocksStack);
        return $block;
    }

    protected $blockBodies = array();
    protected $firstBlockBody = array();
    public function _addBlockBody($name,$generatedName,$arguments){
        $block = new FlageBlockBody($name,$generatedName,$arguments);

        $blockName = $block->getName();
        if(!isset($this->firstBlockBody[$blockName]))
            $this->firstBlockBody[$blockName] = $block;
        if(!isset($this->blockBodies[$blockName])) $this->blockBodies[$blockName] = array();
        //array_push($this->runningBlocks,$block);
        if(count($this->blockBodies[$blockName])){
            $this->blockBodies[$blockName][0]->parent = $block;
            array_unshift($this->blockBodies[$blockName],$block);
        } else
            $this->blockBodies[$blockName] = $block;
    }

    /**
     * @return boolean
     */
    public function isDisableCache()
    {
        return $this->disableCache;
    }

    /**
     * @param boolean $disableCache
     */
    public function setDisableCache($disableCache)
    {
        $this->disableCache = $disableCache;
    }
}


class FlageBlock {
    public $name;
    public $generatedName;
    public $arguments;

    public $argumentsStack = array();

    public $parent = null;

    public $currentInstance = 0;

    public $line;
    public $col;

    public function __construct($name,$generatedName,$arguments){
        $this->name = $name;
        $this->generatedName = $generatedName;
        $this->arguments = $arguments;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getBlockCompiledName()
    {
        return $this->generatedName;
    }

    /**
     * @param string $generatedName
     */
    public function setBlockCompiledName($generatedName)
    {
        $this->generatedName = $generatedName;
    }

    /**
     * @return mixed
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @param mixed $arguments
     */
    public function setArguments($arguments)
    {
        $this->arguments = $arguments;
    }
}
class FlageBlockBody extends FlageBlock {

}
class FlageException extends Exception {
    /**
     * @var int
     */
    protected $_line;
    /**
     * @var int|null
     */
    protected $_col=null;
    /**
     * @var string
     */
    protected $message;
    /**
     * @var bool
     */
    protected $fileDefined=false;

    /**
     * @param string $message
     * @param int $line
     * @param int|null $col
     */
    function __construct($message,$line,$col=null){
        $this->message = $message;
        $this->_line = $line;
        $this->_col = $col;
        $this->line = $line+1;
        $this->col = $col;
    }
    function __toString(){
        return $this->message."\non ".$this->getFile()." (".($this->_line + 1).($this->_col!==null ? ':'.($this->_col + 1) :'').')';
        //return $this->message."\non template line ".($this->_line + 1).($this->_col!==null ? ' column '.($this->_col + 1) :'');
    }

    public function setFile($file){
        $this->file = $file;
        $this->setFileDefined(true);
    }

    /**
     * @return boolean
     */
    public function isFileDefined()
    {
        return $this->fileDefined;
    }

    /**
     * @param boolean $fileDefined
     */
    public function setFileDefined($fileDefined)
    {
        $this->fileDefined = $fileDefined;
    }
}


function flage_generate_args($args_names,$args,$line,$col){
    $args_out = array();
    $offset = 0;
    $indexed_args = array();
    foreach($args as $k=>$v){
        if(is_int($k))
            $indexed_args[]=$v;
    }
    foreach($args_names as $k=>$v){
        $argName = $v;
        /** @noinspection PhpUnusedLocalVariableInspection */
        $required = false;
        $value = null;
        if(!is_int($k)){
            $argName=$k;
            if(is_array($v)){
                $required = isset($v['required']) ? $v['required'] : false;
                if(isset($v['default']))
                    $value = $v['default'];
            } else
                $required = $v;
        } else {
            $required = true;
        }
        if(isset($args[$argName])){
            $value = $args[$argName];
        } else if(isset($indexed_args[$offset])) {
            $value = $indexed_args[$offset];
            $offset++;
        } else if($required) {
            // throw an error required argument is missing
            throw new FlageException("Required argument '$argName' is missing",$line,$col);
        }
        $args_out[$argName] = $value;
    }
    return $args_out;
}

