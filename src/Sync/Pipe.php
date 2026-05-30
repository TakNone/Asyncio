<?php

declare(strict_types = 1);

namespace Tak\Asyncio\Sync;

use Tak\Asyncio\Loop;

use Tak\Asyncio\TimeoutCancellation;

use Swoole\Coroutine\Channel;

use SplQueue;

class Pipe {
	private ? SplQueue $buffer = null;
	private ? SplQueue $consumers = null;
	private ? SplQueue $producers = null;
	private ? Channel $channel = null;
	private bool $closed = false;

	public function __construct(private readonly int $capacity = -1){
		switch(Loop::name()){
			case 'Swoole':
				$this->channel = new Channel($capacity > 0 ? $capacity : 1);
				break;
			case 'Revolt':
				$this->buffer = new SplQueue();
				$this->consumers = new SplQueue();
				$this->producers = new SplQueue();
				break;
		}
	}
	public function push(mixed $data,float $timeout = -1) : void {
		if($this->closed){
			throw new \RuntimeException('Cannot push to a closed channel');
		}
		switch(Loop::name()){
			case 'Swoole':
				$this->channel->push($data,$timeout);
				break;
			case 'Revolt':
				if($this->consumers->isEmpty() === false){
					$suspension = $this->consumers->dequeue();
					Loop::queue($suspension->resume(...),$data);
				} else {
					if($this->capacity > 0 and $this->buffer->count() >= $this->capacity){
						$cancellation = $timeout > 0 ? new TimeoutCancellation($timeout) : null;
						$cancellation?->throwIfCancelled();
						$suspension = Loop::getSuspension();
						$cancel = $cancellation?->subscribe(static function(? \Throwable $exception) use($suspension) : void {
							$suspension->throw(is_null($exception) ? new \LogicException('Awaiting was cancelled') : $exception);
						});
						$this->producers->enqueue($suspension);
						try {
							$suspension->suspend();
						} finally {
							$cancellation?->unsubscribe($cancel);
						}
					}
					$this->buffer->enqueue($data);
				}
				break;
		}
	}
	public function pop(float $timeout = -1) : mixed {
		switch(Loop::name()){
			case 'Swoole':
				return $this->channel->pop($timeout);
			case 'Revolt':
				if($this->buffer->isEmpty() === false){
					$data = $this->buffer->dequeue();
					if($this->producers->isEmpty() === false){
						$suspension = $this->producers->dequeue();
						Loop::queue($suspension->resume(...));
					}
					return $data;
				}
				if($this->closed){
					return null;
				}
				$cancellation = $timeout > 0 ? new TimeoutCancellation($timeout) : null;
				$cancellation?->throwIfCancelled();
				$suspension = Loop::getSuspension();
				$cancel = $cancellation?->subscribe(static function(? \Throwable $exception) use($suspension) : void {
					$suspension->throw(is_null($exception) ? new \LogicException('Awaiting was cancelled') : $exception);
				});
				$this->consumers->enqueue($suspension);
				try {
					return $suspension->suspend();
				} finally {
					$cancellation?->unsubscribe($cancel);
				}
		}
	}
	public function close() : void {
		switch(Loop::name()){
			case 'Swoole':
				$this->channel->close();
				break;
			case 'Revolt':
				while($this->consumers->isEmpty() === false){
					$this->consumers->dequeue()->resume();
				}
				while($this->producers->isEmpty() === false){
					$this->producers->dequeue()->resume();
				}
				break;
		}
		$this->closed = true;
	}
}

?>