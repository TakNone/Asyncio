<?php

declare(strict_types = 1);

namespace Tak\Asyncio\ByteStream;

function getStdin() : ResourceStream {
	static $stdin = new ResourceStream(STDIN);
	return $stdin;
}

function getStdout() : ResourceStream {
	static $stdout = new ResourceStream(STDOUT);
	return $stdout;
}

?>