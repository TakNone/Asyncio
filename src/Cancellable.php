<?php

declare(strict_types = 1);

namespace Tak\Asyncio;

abstract class Cancellable implements Cancellation {
	private string $next = 'a';
	private array $callbacks = array();
	private ? \Throwable $exception = null;
	private bool $requested = false;

	public function cancel(? \Throwable $exception = null) : void {
		if($this->requested === false){
			$this->exception = $exception;
			$this->requested = true;
			if($callbacks = $this->callbacks){
				array_map($this->subscribe(...),$callbacks);
				$this->callbacks = [];
			}
		}
	}
	public function subscribe(callable $callback) : string {
		$id = strval($this->next = str_increment($this->next));
		if($this->requested){
			Loop::queue($callback,$this->exception);
		} else {
			$this->callbacks[$id] = $callback;
		}
		return $id;
	}
	public function unsubscribe(string $id) : void {
		unset($this->callbacks[$id]);
	}
	public function isRequested() : bool {
		return boolval($this->requested);
	}
	public function throwIfCancelled() : void {
		if($this->requested){
			throw new \RuntimeException('Task was cancelled before awaiting');
		}
	}
}

?>