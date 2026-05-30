<?php

declare(strict_types = 1);

namespace Tak\Asyncio\Promise;

final class DeferredFuture {
	private FutureState $state;
	private Future $future;

	public function __construct(){
		$this->state = new FutureState();
		$this->future = new Future($this->state);
	}
	public function complete(mixed $value = null) : void {
		$this->state->complete($value);
	}
	public function isComplete() : bool {
		return $this->state->isComplete;
	}
	public function error(\Throwable $throwable) : void {
		$this->state->error($throwable);
	}
	public function getFuture() : Future {
		return $this->future;
	}
}

?>