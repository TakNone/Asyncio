<?php

declare(strict_types = 1);

namespace Tak\Asyncio\Drivers;

use Tak\Asyncio\Driver;

use Tak\Asyncio\Suspension;

use Swoole\Coroutine;

use Swoole\Timer;

use Swoole\Process;

use Swoole\Event;

use Swoole\Runtime;

final class SwooleDriver implements Driver {
	private static array $registry = array();
	private static array $events = array();

	public function __construct(){
		Event::cycle(self::sweep(...));
		Coroutine::set([
			'log_level'=>SWOOLE_LOG_TRACE,
			'hook_flags'=>SWOOLE_HOOK_ALL,
			'exit_condition'=>static function(){
				$cids = Coroutine::list();
				foreach($cids as $cid){
					if(Coroutine::exists($cid)){
						Coroutine::cancel($cid);
					}
				}
				$timerIds = Timer::list();
				foreach($timerIds as $timerId){
					Timer::clear($timerId);
				}
				return boolval(Coroutine::stats()['coroutine_num'] === 0);
			},
			'enable_deadlock_check'=>false
		]);
		Runtime::enableCoroutine();
	}
	public static function sleep(float $seconds) : void {
		Coroutine::sleep(max($seconds,0.001));
	}
	public static function delay(float $seconds,callable $callback) : string {
		$id = Timer::after(intval($seconds * 1000),function() use($callback,&$id) : void {
			try {
				call_user_func($callback,strval($id));
			} finally {
				unset(self::$registry[$id]);
			}
		});
		self::$registry[$id] = true;
		return strval($id);
	}
	public static function repeat(float $seconds,callable $callback) : string {
		$id = Timer::tick(intval($seconds * 1000),fn(int $timer_id) => call_user_func($callback,strval($timer_id)));
		self::$registry[$id] = true;
		return strval($id);
	}
	public static function queue(callable $callback,mixed ...$args) : void {
		$id = Coroutine::create(function() use($callback,$args) : void {
			$cid = Coroutine::getcid();
			try {
				call_user_func_array($callback,$args);
			} finally {
				unset(self::$registry[$cid]);
			}
		});
		self::$registry[$id] = true;
	}
	public static function defer(callable $callback) : string {
		$id = uniqid('defer_');
		Event::defer(function() use($id,$callback) : void {
			if(array_key_exists($id,self::$registry)){
				call_user_func($callback);
			}
			unset(self::$registry[$id]);
		});
		self::$registry[$id] = true;
		return $id;
	}
	public static function onReadable(mixed $resource,callable $callback) : string {
		/*
		if(is_resource($resource)){
			$stat = fstat($resource);
			if(0100000 === ($stat['mode'] & 0100000)){
				$id = uniqid('defer_');
				$tick = function() use($callback,$id,$resource,&$tick) : void {
					if(is_resource($resource) and array_key_exists($id,self::$registry)){
						$callback($id,$resource);
						Event::defer($tick);
					} else {
						unset(self::$registry[$id]);
					}
				};
				Event::defer($tick);
				self::$registry[$id] = true;
				return $id;
			}
		}
		*/
		$id = uniqid('event_');
		Event::add($resource,static fn(mixed $stream) : mixed => $callback($id,$stream),null,SWOOLE_EVENT_READ);
		self::$registry[$id] = true;
		self::$events[$id] = $resource;
		return $id;
	}
	public static function onWritable(mixed $resource,callable $callback) : string {
		/*
		if(is_resource($resource)){
			$stat = fstat($resource);
			if(0100000 === ($stat['mode'] & 0100000)){
				$id = uniqid('defer_');
				$tick = function() use($callback,$id,$resource,&$tick) : void {
					if(is_resource($resource) and array_key_exists($id,self::$registry)){
						$callback($id,$resource);
						Event::defer($tick);
					} else {
						unset(self::$registry[$id]);
					}
				};
				Event::defer($tick);
				self::$registry[$id] = true;
				return $id;
			}
		}
		*/
		$id = uniqid('event_');
		Event::add($resource,null,static fn(mixed $stream) : mixed => $callback($id,$stream),SWOOLE_EVENT_WRITE);
		self::$registry[$id] = true;
		self::$events[$id] = $resource;
		return $id;
	}
	public static function onSignal(int $signal,callable $callback) : string {
		Process::signal($signal,$callback);
		return uniqid('signal_');
	}
	public static function cancel(string $id) : void {
		if(is_string($id) and str_starts_with($id,'defer_')){
			unset(self::$registry[$id]);
		} else if(is_string($id) and str_starts_with($id,'event_')){
			if(array_key_exists($id,self::$events)){
				unset(self::$registry[$id]);
				Event::del(self::$events[$id]);
				unset(self::$events[$id]);
			}
		} else if(ctype_digit($id) and Timer::exists(intval($id))){
			Timer::clear(intval($id));
		} else if(ctype_digit($id) and Coroutine::exists(intval($id))){
			Coroutine::cancel(intval($id));
		}
	}
	public static function reference(string $id) : string {
		if(array_key_exists($id,self::$registry)){
			self::$registry[$id] = true;
		}
		return $id;
	}
	public static function unreference(string $id) : string {
		if(array_key_exists($id,self::$registry)){
			self::$registry[$id] = false;
		}
		return $id;
	}
	public static function isReferenced(string $id) : bool {
		if(array_key_exists($id,self::$registry)){
			return boolval(self::$registry[$id]);
		} else {
			throw new \Error('Invalid callback identifier '.$id);
		}
	}
	public static function getSuspension() : Suspension {
		$cid = Coroutine::getCid();
		return new class($cid) implements Suspension {
			protected bool $throwBack;
			private mixed $value;

			public function __construct(private int $cid){
			}
			public function suspend() : mixed {
				unset($this->throwBack);
				Coroutine::suspend();
				if(isset($this->throwBack) === false){
					throw new \RuntimeException('Coroutine resumed without a value or exception');
				} else if($this->throwBack){
					throw $this->value;
				} else {
					return $this->value;
				}
			}
			public function resume(mixed $value = null) : void {
				if(isset($this->throwBack) === false){
					$this->value = $value;
					$this->throwBack = false;
					Coroutine::resume($this->cid);
				}
			}
			public function throw(\Throwable $throwable) : void {
				if(isset($this->throwBack) === false){
					$this->value = $throwable;
					$this->throwBack = true;
					Coroutine::resume($this->cid);
				}
			}
		};
	}
	public static function setErrorHandler(callable $callback) : void {
		@set_exception_handler($callback);
	}
	public static function run() : void {
		self::sweep();
		Event::wait();
	}
	public static function sweep() : void {
		if(in_array(true,self::$registry,true) === false){
			array_map(self::cancel(...),array_keys(self::$registry));
			Event::exit();
		}
	}
}

?>