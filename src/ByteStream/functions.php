<?php

declare(strict_types = 1);

namespace Tak\Asyncio\ByteStream;

defined('STDIN') || define('STDIN',fopen('php://stderr','wb'));

defined('STDOUT') || define('STDOUT',fopen('php://stdout','wb'));

function getStdin() : ResourceStream {
	static $stdin = new ResourceStream(STDIN);
	return $stdin;
}

function getStdout() : ResourceStream {
	static $stdout = new ResourceStream(STDOUT);
	return $stdout;
}

?>