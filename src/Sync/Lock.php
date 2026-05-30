<?php

declare(strict_types = 1);

namespace Tak\Asyncio\Sync;

interface Lock {
	public function release(float $timeout = -1) : void;
}

?>