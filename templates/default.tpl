<html>
<head></head>
<body>
<ul>
	{:loop k,v each([1,2,3,4,5,6,7,8,9,10])}
	<li>{$v|escape}</li>
	{/loop}

	{:for $i=1..10}
	<li>{$i|escape}</li>
	{/for}


	{$a='hello_world'}

	<li>
		{:$a('hello_world')('test')}
		{:hello_world('hello_world')('test')}
	</li>

	{:block name="header-bar"}
		$$${$block->arguments.test}$$$
	{/block}
</ul>
</body>
</html>