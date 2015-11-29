<?php


class FlageTagPrint_block extends FlageTag {
    /**
     * @param FlageParser $parser
     * @param string $tagBody
     * @return string
     */
    public function replaceContent($parser, $tagBody)
    {
        /*
        return '<?php $blockObject = '.$this->arguments['block'].'; '
            .'if(!($blockObject instanceof FlageBlock)) '
                .'throw new FlageException(\'Passed block argument is not a FlageBlock object\','.$this->line.','.$this->col.');'
            .'include($blockObject->getBlockCompiledName()); '
            .'?'.'>';
        */

        $blockArguments = array();
        foreach($this->arguments as $argName=>$argValue)
            $blockArguments[] = "'".str_replace(array("'",'\\'),array("\\'",'\\\\'),$argName)."'" . '=>' . $argValue;

        return '<?php $blockObject = '.$this->arguments['block'].'; '
            .'if(!($blockObject instanceof FlageBlock)) '
                .'throw new FlageException(\'Passed block argument is not a FlageBlock object\','.$this->line.','.$this->col.');'
            .' $block=$__h->_pushBlock($blockObject,array('.implode(',',$blockArguments).')); '
            . (
                !$parser->getFlage()->isDisableCache() ?
                    'include( $block->getBlockCompiledName() ); ' :
                    'eval("?".">". $__h->loadCompiled($block->getBlockCompiledName()) ."<?php;"); '
            )
            .'$block=$__h->_endBlock(); '
            .'?'.'>';

    }


    /**
     * @param FlageParser $parser
     * @param string $tag
     * @param string $tagRest
     */
    public function tagInit($parser, $tag, $tagRest)
    {
        $this->arguments = $parser->_tokenize_named_arguments($tagRest,'',false);
    }

    public function isSelfClosing(){
        return true;
    }
}