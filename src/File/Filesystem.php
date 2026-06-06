<?php

declare(strict_types = 1);

namespace Tak\Asyncio\File;

use Tak\Asyncio\Loop;

use Tak\Asyncio\FileDriver;

use Tak\Asyncio\ByteStream\ResourceStream;

use function Tak\Asyncio\async;

use Swoole\Coroutine\System;

final class Filesystem implements FileDriver {
	public function open(string $path,string $mode) : object {
		if($fp = @fopen($path,$mode)){
			return new ResourceStream($fp);
		} else {
			throw new \Exception('Could not open file : '.$path);
		}
	}
	public function read(string $path,? int $length = null) : string {
		if(Loop::name() === 'Swoole'){
			$content = System::readFile($path);
			if($content === false){
				throw new \Exception('Could not read the file '.$path);
			}
			return is_null($length) ? $content : substr($content,0,$length);
		}
		$file = $this->open($path,'rb');
		$content = $file->read($length);
		if(is_null($content)){
			throw new \Exception('Could not read the file '.$path);
		}
		while(is_null($length) and is_null($chunk = $file->read($length)) === false){
			$content .= $chunk;
		}
		$file->close();
		return $content;
	}
	public function write(string $path,string $content) : int {
		if(Loop::name() === 'Swoole'){
			$result = System::writeFile($path,$content);
			if($result === false){
				throw new \Exception('Could not write to the file '.$path);
			}
			return strlen($content);
		}
		$file = $this->open($path,'wb');
		$bytes = $file->write($content);
		if(is_null($content)){
			throw new \Exception('Could not write to the file '.$path);
		}
		$file->close();
		return $bytes;
	}
	public function exists(string $path) : bool {
		return async(@file_exists(...),$path)->await();
	}
	public function delete(string $path) : bool {
		return async(@unlink(...),$path)->await();
	}
	public function mkdir(string $path,int $permissions = 0777,bool $recursive = true) : bool {
		return async(@mkdir(...),$path,$permissions,$recursive)->await();
	}
	public function rmdir(string $path) : bool {
		if($this->isDirectory($path)){
			$items = $this->listFiles($path);
			foreach($items as $item){
				$fullPath = realpath($path).DIRECTORY_SEPARATOR.$item;
				if($this->isDirectory($fullPath)){
					$this->rmdir($fullPath);
				} else {
					$this->delete($fullPath);
				}
			}
		}
		return async(@rmdir(...),$path)->await();
	}
	public function move(string $from,string $to) : bool {
		return async(@rename(...),$from,$to)->await();
	}
	public function size(string $path) : int {
		return async(@filesize(...),$path)->await();
	}
	public function touch(string $path,? int $mtime = null,? int $atime = null) : bool {
		return async(@touch(...),$path,$mtime,$atime)->await();
	}
	public function isFile(string $path) : bool {
		return async(@is_file(...),$path)->await();
	}
	public function isDirectory(string $path) : bool {
		return async(@is_dir(...),$path)->await();
	}
	public function listFiles(string $path,int $sorting_order = SCANDIR_SORT_ASCENDING) : array {
		return async(static function() use($path,$sorting_order) : array {
			if($files = @scandir($path,$sorting_order)){
				return array_values(array_diff($files,array('.','..')));
			} else {
				throw new \Exception('Failed to list directory : '.$path);
			}
		})->await();
	}
}

?>