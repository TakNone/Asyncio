<?php

declare(strict_types = 1);

namespace Tak\Asyncio\Promise;

final class FutureState {
	public bool $isComplete = false;
	public bool $finished = false;
	public bool $ignore = false;
	public mixed $result = null;
	public ? \Closure $catch = null;
	public ? \Closure $finally = null;
	public ? \Throwable $error = null;
	public array $suspensions = [];

	public function complete(mixed $value = null) : void {
		if($this->isComplete === false){
			$this->result = $value;
			$this->isComplete = true;
			foreach($this->suspensions as $suspension){
				$suspension->resume($value);
			}
			$this->finished = true;
			$this->suspensions = [];
			if(is_null($this->finally) === false){
				call_user_func($this->finally);
			}
		}
	}
	public function ignore() : void {
		$this->ignore = true;
	}
	public function catch(\Closure $catch) : void {
		$this->catch = $catch;
	}
	public function finally(\Closure $finally) : void {
		$this->finally = $finally;
	}
	public function error(\Throwable $throwable) : void {
		if($this->isComplete === false){
			$this->error = $throwable;
			$this->isComplete = true;
			if(is_null($this->catch) === false){
				foreach($this->suspensions as $suspension){
					$suspension->resume();
				}
				call_user_func($this->catch,$throwable);
			} else {
				foreach($this->suspensions as $suspension){
					if($this->ignore === false){
						$suspension->throw($throwable);
					} else {
						$suspension->resume();
					}
				}
			}
			$this->finished = true;
			$this->suspensions = [];
			if(is_null($this->finally) === false){
				call_user_func($this->finally);
			}
		}
	}
}

?>