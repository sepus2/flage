{:include("default.tpl")}

\{:export()}

{$x=0}
{:block name="1" test=0}
\{$block|print_r:true}
{$x=$x+1}
{:if $x<10}
	==={$block->arguments.test}===
	{:print_block block=$block test=$block->arguments.test+1}
{/if}
{/block}


{:block   name="header-bar"     inline=true checked secondArg}
	<h2>test.tpl block $block_name</h2>
	Block 1: !!!{$block->arguments.test}!!!
	{:block name="header-bar-internal"}
		Block 2:
		<p>test.tpl block inner $block_name</p>
	{/block}
{/block}


{:block_body name="header-bar" test=1}
	Block body: !!!{$block->arguments.test}!!!
	{:print_block block=$block->parent test=$block->arguments.test+1 "testx"}
	{:block name="header-bar-internal"}
		Block 3:
	{/block}
{:/block_body}

{:block_body name="header-bar-internal"}
	Block body test 2: {:print_block block=$block->parent}
{/block_body}