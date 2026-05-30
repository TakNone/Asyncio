<?php

declare(strict_types = 1);

namespace Tak\Asyncio\File;

interface FileDriver {
	public function open(string $path,string $mode) : object;

	public function read(string $path) : string;

	public function write(string $path,string $content) : int;

	public function exists(string $path) : bool;

	public function delete(string $path) : bool;

	public function mkdir(string $path,int $permissions = 0777,bool $recursive = true) : bool;

	public function rmdir(string $path) : bool;

	public function move(string $from,string $to) : bool;

	public function size(string $path) : int;

	public function touch(string $path,? int $mtime = null,? int $atime = null) : bool;
	
	public function isFile(string $path) : bool;

	public function isDirectory(string $path) : bool;

	public function listFiles(string $path,int $sorting_order = SCANDIR_SORT_ASCENDING) : array;
}

?>