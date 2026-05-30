<?php

declare(strict_types = 1);

namespace Tak\Asyncio\Promise;

use Tak\Asyncio\Loop;

use Tak\Asyncio\Cancellation;

readonly class Future {
	public function __construct(private FutureState $state){
	}
	public function await(? Cancellation $cancellation = null) : mixed {
		if($this->state->isComplete){
			if($this->state->error){
				if(is_null($this->state->catch) === false){
					if($this->state->finished === false) call_user_func($this->state->catch,$this->state->error);
				} else {
					if($this->state->finished === false) call_user_func($this->state->finally);
					if($this->state->ignore === false) throw $this->state->error;
				}
			}
			if($this->state->finished === false) call_user_func($this->state->finally);
			return $this->state->result;
		}
		$cancellation?->throwIfCancelled();
		$suspension = Loop::getSuspension();
		$id = $cancellation?->subscribe(function(? \Throwable $exception) : void {
			$this->state->error(is_null($exception) ? new \LogicException('Awaiting was cancelled') : $exception);
		});
		$this->state->suspensions []= $suspension;
		try {
			return $suspension->suspend();
		} finally {
			$cancellation?->unsubscribe($id);
		}
	}
	public function isComplete() : bool {
		return $this->state->isComplete;
	}
	public function ignore() : self {
		$this->state->ignore();
		Loop::queue($this->await(...));
		return $this;
	}
	public function catch(callable $catch) : self {
		$this->state->catch($catch(...));
		Loop::queue($this->await(...));
		return $this;
	}
	public function finally(callable $finally) : self {
		$this->state->finally($finally(...));
		Loop::queue($this->await(...));
		return $this;
	}
}

?>