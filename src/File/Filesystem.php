<?php

declare(strict_types = 1);

namespace Tak\Asyncio\File;

use Tak\Asyncio\Loop;

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
			$content = strval(System::readFile($path));
			return is_null($length) ? $content : substr($content,0,$length);
		}
		$file = $this->open($path,'r+');
		$content = $file->read($length);
		$file->close();
		return $content;
	}
	public function write(string $path,string $content) : int {
		if(Loop::name() === 'Swoole'){
			$bytes = intval(System::writeFile($path,$content));
			return $bytes;
		}
		$file = $this->open($path,'w+');
		$bytes = $file->write($content);
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
		return $this->delete($path);
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