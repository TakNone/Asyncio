<?php

declare(strict_types = 1);

namespace Tak\Asyncio;

interface Driver {
	public static function sleep(float $seconds) : void;
	public static function delay(float $seconds,callable $callback) : string;
	public static function repeat(float $seconds,callable $callback) : string;
	public static function queue(callable $callback,mixed ...$args) : void;
	public static function defer(callable $callback) : string;

	public static function onReadable(mixed $resource,callable $callback) : string;
	public static function onWritable(mixed $resource,callable $callback) : string;
	public static function onSignal(int $signal,callable $callback) : string;

	public static function cancel(string $id) : void;

	public static function reference(string $id) : void;
	public static function unreference(string $id) : void;
	public static function isReferenced(string $id) : bool;

	public static function setErrorHandler(callable $callback) : void;

	public static function getSuspension() : Suspension;

	public static function run() : void;
}

?>