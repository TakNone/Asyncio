<?php

declare(strict_types = 1);

namespace Tak\Asyncio;

use Tak\Asyncio\Promise\Future;

use Tak\Asyncio\Promise\DeferredFuture;

function async(callable $callback,mixed ...$arguments) : Future {
	$deferred = new DeferredFuture();
	Loop::queue(static function() use($callback,$arguments,$deferred) : void {
		try {
			$result = call_user_func_array($callback,$arguments);
			$deferred->complete($result);
		} catch(\Throwable $error){
			$deferred->error($error);
		}
	});
	return $deferred->getFuture();
}

function delay(float $seconds) : void {
	Loop::sleep($seconds);
}

function await(array $futures) : array {
	return array_map(fn(Future $future) : mixed => $future->await(),$futures);
}

?>