{:include("default.tpl")}

{:block_body name="header-bar" test=1}
	Block body: !!!{$block->arguments.test}!!!
	{:$block->parent|print_r:true}
	{:print_block block=$block->parent test=$block->arguments.test+1 "testx"}
	{:block name="header-bar-internal"}
		Block 3:
	{/block}
{:/block_body}

{:block_body name="header-bar-internal"}
	Block body test 2: {:print_block block=$block->parent}
{/block_body}