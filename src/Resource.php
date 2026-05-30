<?php

declare(strict_types = 1);

namespace Tak\Asyncio;

interface Resource {
	public function read(? int $length = null,? Cancellation $cancellation = null) : string;
	public function write(string $content,? Cancellation $cancellation = null) : int;
	public function tell() : int | false;
	public function seek(int $offset,int $whence = SEEK_SET) : int;
	public function lock(int $operation = LOCK_SH | LOCK_EX) : bool;
	public function truncate(int $size) : bool;
	public function flush() : bool;
	public function close() : bool;
}

?>