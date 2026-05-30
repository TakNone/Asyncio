<?php

declare(strict_types = 1);

namespace Tak\Asyncio\ByteStream;

use Tak\Asyncio\Loop;

use Tak\Asyncio\Resource;

use Tak\Asyncio\Cancellation;

use function Tak\Asyncio\async;

final class ResourceStream implements Resource {
	public const int CHUNK_SIZE = 1024 * 1024;

	public function __construct(protected mixed $fp){
		@stream_set_blocking($fp,false);
	}
	public function read(? int $length = null,? Cancellation $cancellation = null) : string {
		$buffer = strval(null);
		$cancellation?->throwIfCancelled();
		$suspension = Loop::getSuspension();
		Loop::onReadable($this->fp,static function(string $id,mixed $fp) use($suspension,&$buffer,$length) : void {
			$limit = is_null($length) ? self::CHUNK_SIZE : min(self::CHUNK_SIZE,$length - strlen($buffer));
			$chunk = ($limit > 0 ? fread($fp,$limit) : false);
			if($chunk === false || empty($chunk)){
				Loop::cancel($id);
				$suspension->resume($buffer);
			} else {
				$buffer .= $chunk;
			}
		});
		$id = $cancellation?->subscribe(function(? \Throwable $exception) use($suspension) : void {
			$suspension->throw(is_null($exception) ? new \LogicException('Awaiting was cancelled') : $exception);
		});
		try {
			return $suspension->suspend();
		} finally {
			$cancellation?->unsubscribe($id);
		}
	}
	public function write(string $content,? Cancellation $cancellation = null) : int {
		$buffer = strval(null);
		$written = 0;
		$length = strlen($content);
		$cancellation?->throwIfCancelled();
		$suspension = Loop::getSuspension();
		Loop::onWritable($this->fp,static function(string $id,mixed $fp) use($suspension,$content,&$written,$length) : void {
			$result = fwrite($fp,substr($content,$written,self::CHUNK_SIZE));
			$written += intval($result);
			if($result === false || $written >= $length){
				Loop::cancel($id);
				$suspension->resume($written);
			}
		});
		$id = $cancellation?->subscribe(function(? \Throwable $exception) use($suspension) : void {
			$suspension->throw(is_null($exception) ? new \LogicException('Awaiting was cancelled') : $exception);
		});
		try {
			return $suspension->suspend();
		} finally {
			$cancellation?->unsubscribe($id);
		}
	}
	public function tell() : int | false {
		return async(fn() : int | false => ftell($this->fp))->await();
	}
	public function seek(int $offset,int $whence = SEEK_SET) : int {
		return async(fn() : int => fseek($this->fp,$offset,$whence))->await();
	}
	public function lock(int $operation = LOCK_SH | LOCK_EX) : bool {
		return async(fn() : bool => flock($this->fp,$operation))->await();
	}
	public function truncate(int $size) : bool {
		return async(fn() : bool => ftruncate($this->fp,$size))->await();
	}
	public function flush() : bool {
		return async(fn() : bool => flush($this->fp))->await();
	}
	public function close() : bool {
		return async(fn() : bool => fclose($this->fp))->await();
	}
}

?>