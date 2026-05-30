<?php

declare(strict_types = 1);

namespace Tak\Asyncio;

interface Suspension {
	public function suspend() : mixed;

	public function resume(mixed $value = null) : void;

	public function throw(\Throwable $throwable) : void;
}

?>