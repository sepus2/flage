<?php


class FlageTagReturn extends FlageTag {
    /**
     * @param FlageParser $parser
     * @param string $tagBody
     * @return string
     */
    public function replaceContent($parser, $tagBody)
    {
        return '<?php return; ?>';

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