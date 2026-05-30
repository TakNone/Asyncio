<?php

declare(strict_types = 1);

namespace Tak\Asyncio\File;

function getFileDriver() : FileDriver {
	static $driver = null;
	if($driver){
		return $driver;
	} else {
		return $driver = new Filesystem;
	}
}

function openFile(string $path,string $mode) : object {
	return getFileDriver()->open($path,$mode);
}

function read(string $path) : string {
	return getFileDriver()->read($path);
}

function write(string $path,string $content) : int {
	return getFileDriver()->write($path,$content);
}

function exists(string $path) : bool {
	return getFileDriver()->exists($path);
}

function deleteFile(string $path) : bool {
	return getFileDriver()->delete($path);
}

function createDirectory(string $path,int $permissions = 0777,bool $recursive = true) : bool {
	return getFileDriver()->mkdir($path,$permissions,$recursive);
}

function deleteDirectory(string $path) : bool {
	return getFileDriver()->rmdir($path);
}

function move(string $from,string $to) : bool {
	return getFileDriver()->move($from,$to);
}

function getSize(string $path) : int {
	return getFileDriver()->size($path);
}

function touch(string $path,? int $mtime = null,? int $atime = null) : bool {
	return getFileDriver()->touch($path,$mtime,$atime);
}

function isFile(string $path) : bool {
	return getFileDriver()->isFile($path);
}

function isDirectory(string $path) : bool {
	return getFileDriver()->isDirectory($path);
}

function listFiles(string $path,int $sorting_order = SCANDIR_SORT_ASCENDING) : array {
	return getFileDriver()->listFiles($path,$sorting_order);
}

?>