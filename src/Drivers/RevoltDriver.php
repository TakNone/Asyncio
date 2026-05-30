<?php

declare(strict_types = 1);

namespace Tak\Asyncio\Drivers;

use Tak\Asyncio\Driver;

use Tak\Asyncio\Suspension;

use Revolt\EventLoop;

final class RevoltDriver implements Driver {
	public static function sleep(float $seconds) : void {
		$suspension = EventLoop::getSuspension();
		EventLoop::delay($seconds,static fn() : null => $suspension->resume());
		$suspension->suspend();
	}
	public static function delay(float $seconds,callable $callback) : string {
		return EventLoop::delay($seconds,fn(string $callbackId) => call_user_func($callback));
	}
	public static function repeat(float $seconds,callable $callback) : string {
		return EventLoop::repeat($seconds,$callback(...));
	}
	public static function queue(callable $callback,mixed ...$args) : void {
		EventLoop::queue($callback(...),...$args);
	}
	public static function defer(callable $callback) : string {
		return EventLoop::defer(fn(string $callbackId) => call_user_func($callback));
	}
	public static function onReadable(mixed $resource,callable $callback) : string {
		return EventLoop::onReadable($resource,static fn(string $id,mixed $stream) : mixed => $callback($id,$stream));
	}
	public static function onWritable(mixed $resource,callable $callback) : string {
		return EventLoop::onWritable($resource,static fn(string $id,mixed $stream) : mixed => $callback($id,$stream));
	}
	public static function onSignal(int $signal,callable $callback) : string {
		return EventLoop::onSignal($signal,$callback(...));
	}
	public static function cancel(string $id) : void {
		EventLoop::cancel($id);
	}
	public static function reference(string $id) : void {
		EventLoop::reference($id);
	}
	public static function unreference(string $id) : void {
		EventLoop::unreference($id);
	}
	public static function isReferenced(string $id) : bool {
		return EventLoop::isReferenced($id);
	}
	public static function getSuspension() : Suspension {
		$suspension = EventLoop::getSuspension();
		return new class($suspension) implements Suspension {
			protected bool $resolved = false;

			public function __construct(private $s){
			}
			public function suspend() : mixed {
				$this->resolved = false;
				return $this->s->suspend();
			}
			public function resume(mixed $value = null) : void {
				if($this->resolved === false){
					$this->resolved = true;
					$this->s->resume($value);
				}
			}
			public function throw(\Throwable $throwable) : void {
				if($this->resolved === false){
					$this->resolved = true;
					$this->s->throw($throwable);
				}
			}
		};
	}
	public static function setErrorHandler(callable $callback) : void {
		EventLoop::setErrorHandler($callback(...));
	}
	public static function run() : void {
		EventLoop::run();
	}
}

?>