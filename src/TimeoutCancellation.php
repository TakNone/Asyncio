<?php

declare(strict_types = 1);

namespace Tak\Asyncio;

final class TimeoutCancellation extends Cancellable implements Cancellation {
	protected readonly string $id;

	public function __construct(float $timeout){
		$this->id = Loop::delay($timeout,fn() => $this->cancel(new \Error('Operation timed out')));
		Loop::unreference($this->id);
	}
	public function __destruct(){
		Loop::cancel($this->id);
	}
}

?>