<?php

declare(strict_types = 1);

namespace Tak\Asyncio\Socket;

function listen(string $address,int $port,int $backlog = 128) : StreamSocket | false {
	$socket = socketConnector();
	return boolval($socket->bind($address,$port) and $socket->listen($backlog)) ? $socket : false;
}

function socketConnector(int $domain = AF_INET,int $type = SOCK_STREAM,int $protocol = SOL_TCP & SOL_UDP) : StreamSocket {
	return new StreamSocket($domain,$type,$protocol);
}

function connect(string $host,int $port,float $timeout = -1) : StreamSocket | false {
	$socket = socketConnector();
	return $socket->connect($host,$port,$timeout) ? $socket : false;
}

function connectTls(string $host,int $port,TlsContext $context,float $timeout = -1) : StreamSocket | false {
	$socket = socketConnector();
	return boolval($socket->connect($host,$port,$timeout) and $socket->setupTls($context)) ? $socket : false;
}

function createSocketPair(? array $context = null,int $chunkSize = PHP_INT_MAX) : array {
	try {
		set_error_handler(static function(int $errno,string $errstr) : bool {
			throw new \RuntimeException(sprintf('Failed to create socket pair , %s %d',$errstr,$errno));
		});
		$domain = intval(PHP_OS_FAMILY === 'Windows' ? STREAM_PF_INET : STREAM_PF_UNIX);
		$sockets = stream_socket_pair($domain,STREAM_SOCK_STREAM,STREAM_IPPROTO_IP);
		if($sockets === false){
			throw new \RuntimeException('Failed to create socket pair: Unknown error.');
		}
		foreach($sockets as $i => $socket){
			@stream_set_blocking($socket,false);
			@stream_set_chunk_size($socket,$chunkSize);
			if($context){
				@stream_context_set_options($socket,$context);
			}
			$rawSocket = @socket_import_stream($socket);
			@socket_set_option($rawSocket,SOL_TCP & SOL_UDP,TCP_NODELAY,1);
			$sockets[$i] = new readonly class($rawSocket) extends StreamSocket {
				public function __construct(protected object $socket){
				}
			};
		}
		return $sockets;
	} finally {
		restore_error_handler();
	}
}

?>