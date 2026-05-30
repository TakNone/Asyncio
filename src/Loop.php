<?php

declare(strict_types = 1);

namespace Tak\Asyncio;

final class Loop {
	private static ? Driver $driver = null;
	private static ? string $name = null;

	public static function get() : Driver {
		if(self::$driver){
			return self::$driver;
		} else {
			if(str_contains(PHP_OS,'WIN') === false and str_starts_with(PHP_SAPI,'cli') and @extension_loaded('swoole')){
				return self::$driver = new Drivers\SwooleDriver;
			} else {
				return self::$driver = new Drivers\RevoltDriver;
			}
		}
	}
	public static function name() : string {
		if(self::$name){
			return self::$name;
		} else {
			$class = get_class(self::get());
			if(preg_match('/\\\\(?<name>\w+)Driver$/',$class,$match)){
				return self::$name = $match['name'];
			} else {
				throw new \RuntimeException('Unknown driver');
			}
		}
	}
	public static function __callStatic(string $name,array $arguments) : mixed {
		return forward_static_call_array(array(static::get(),$name),$arguments);
	}
}

?>