<?php

declare(strict_types = 1);

namespace Tak\Asyncio;

interface Cancellation {
	public function cancel(? \Throwable $exception = null) : void;
	public function subscribe(callable $callback) : string;
	public function unsubscribe(string $id) : void;
	public function isRequested() : bool;
	public function throwIfCancelled() : void;
}

?>