<?php

declare(strict_types = 1);

namespace Tak\Asyncio\Sync;

use Tak\Asyncio\Loop;

class Mutex {
	private Pipe $pipe;

	public function __construct(int $locks = 1){
		$this->pipe = new Pipe($locks);
		$this->pipe->push(true);
	}
	public function acquire(float $timeout = -1) : object {
		$this->pipe->pop($timeout);
		return new class($this->pipe) implements Lock {
			private bool $ready = true;

			public function __construct(private Pipe $pipe){
			}
			public function release(float $timeout = -1) : void {
				if($this->ready){
					$this->ready = false;
					$this->pipe->push(true,$timeout);
				}
			}
		};
	}
}

?>