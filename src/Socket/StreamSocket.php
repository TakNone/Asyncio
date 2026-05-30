<?php

declare(strict_types = 1);

namespace Tak\Asyncio\Socket;

use Tak\Asyncio\Loop;

use Tak\Asyncio\TimeoutCancellation;

use Swoole\Coroutine\Socket;

readonly class StreamSocket {
	protected object $socket;

	public function __construct(int $domain = AF_INET,int $type = SOCK_STREAM,int $protocol = SOL_TCP & SOL_UDP){
		switch(Loop::name()){
			case 'Swoole':
				$this->socket = new Socket($domain,$type,$protocol);
				break;
			case 'Revolt':
				$this->socket = socket_create($domain,$type,$protocol);
				socket_set_nonblock($this->socket);
				break;
		}
	}
	public function bind(string $address,int $port) : bool {
		$this->check($address,$port);
		return match(Loop::name()){
			'Swoole' => $this->socket->bind($address,$port),
			'Revolt' => socket_bind($this->socket,$address,$port)
		};
	}
	public function listen(int $backlog) : bool {
		return match(Loop::name()){
			'Swoole' => $this->socket->listen($backlog),
			'Revolt' => socket_listen($this->socket,$backlog)
		};
	}
	public function accept(float $timeout = -1) : object | false {
		$client = match(Loop::name()){
			'Swoole' => $this->socket->accept($timeout),
			'Revolt' => socket_accept($this->socket)
		};
		return is_object($client) ? new readonly class($client) extends StreamSocket {
			public function __construct(protected object $socket){
			}
		} : false;
	}
	public function connect(string $host,int $port,float $timeout = -1) : bool {
		$this->check($host,$port);
		switch(Loop::name()){
			case 'Swoole':
				return $this->socket->connect($host,$port,$timeout);
			case 'Revolt':
				if(@socket_connect($this->socket,$host,$port)){
					return true;
				} else {
					$error = socket_last_error($this->socket);
					if($error === SOCKET_EINPROGRESS or $error === SOCKET_EALREADY){
						$cancellation = $timeout > 0 ? new TimeoutCancellation($timeout) : null;
						$cancellation?->throwIfCancelled();
						$suspension = Loop::getSuspension();
						$id = Loop::onWritable($this->getResource(),static function(string $id) use($suspension) : void {
							Loop::cancel($id);
							$suspension->resume(true);
						});
						$cancel = $cancellation?->subscribe(static function() use($suspension,$id) : void {
							Loop::cancel($id);
							$suspension->resume(false);
						});
						try {
							return $suspension->suspend();
						} finally {
							$cancellation?->unsubscribe($cancel);
						}
					} else {
						return false;
					}
				}
		}
	}
	public function write(string $data,float $timeout = -1,int $flags = MSG_OOB) : int | false {
		switch(Loop::name()){
			case 'Swoole':
				return $this->socket->sendAll($data,$timeout);
			case 'Revolt':
				$result = socket_send($this->socket,$data,strlen($data),$flags);
				if($result === false and socket_last_error($this->socket) === SOCKET_EAGAIN){
					$cancellation = $timeout > 0 ? new TimeoutCancellation($timeout) : null;
					$cancellation?->throwIfCancelled();
					$suspension = Loop::getSuspension();
					$id = Loop::onWritable($this->getResource(),function(string $id) use($suspension,$data,$flags) : void {
						Loop::cancel($id);
						$result = socket_send($this->socket,$data,strlen($data),$flags);
						$suspension->resume($result);
					});
					$cancel = $cancellation?->subscribe(static function() use($suspension,$id) : void {
						Loop::cancel($id);
						$suspension->resume(false);
					});
					try {
						$result = $suspension->suspend();
					} finally {
						$cancellation?->unsubscribe($cancel);
					}
				}
				return $result;
		}
	}
	public function read(? int $length = null,float $timeout = -1,int $flags = MSG_WAITALL) : string | false {
		switch(Loop::name()){
			case 'Swoole':
				return is_null($length) ? $this->socket->recv(1 << 16,$timeout) : $this->socket->recvAll($length,$timeout);
			case 'Revolt':
				$buffer = strval(null);
				$result = socket_recv($this->socket,$buffer,$length,is_null($length) ? 0 : $flags);
				if($result === false and socket_last_error($this->socket) === SOCKET_EAGAIN){
					$cancellation = $timeout > 0 ? new TimeoutCancellation($timeout) : null;
					$cancellation?->throwIfCancelled();
					$suspension = Loop::getSuspension();
					$id = Loop::onReadable($this->getResource(),function(string $id) use($suspension,&$buffer,$length,$flags) : void {
						Loop::cancel($id);
						$result = socket_recv($this->socket,$buffer,$length,is_null($length) ? 0 : $flags);
						$suspension->resume($result);
					});
					$cancel = $cancellation?->subscribe(static function() use($suspension,$id) : void {
						Loop::cancel($id);
						$suspension->resume(false);
					});
					try {
						$result = $suspension->suspend();
					} finally {
						$cancellation?->unsubscribe($cancel);
					}
				}
				return $result ? $buffer : false;
		}
	}
	public function isClosed() : bool {
		return match(Loop::name()){
			'Swoole' => $this->socket->isClosed(),
			'Revolt' => is_resource($this->socket) === false || $this->getPeerName() === false
		};
	}
	public function close() : bool {
		switch(Loop::name()){
			case 'Swoole':
				return $this->socket->close();
			case 'Revolt':
				socket_close($this->socket);
				return true;
		}
	}
	public function getPeerName() : array | false {
		switch(Loop::name()){
			case 'Swoole':
				return $this->socket->getpeername();
			case 'Revolt':
				if(socket_getpeername($this->socket,$address,$port)){
					return compact('address','port');
				} else {
					return false;
				}
		}
	}
	public function getSockName() : array | false {
		switch(Loop::name()){
			case 'Swoole':
				return $this->socket->getsockname();
			case 'Revolt':
				if(socket_getsockname($this->socket,$address,$port)){
					return compact('address','port');
				} else {
					return false;
				}
		}
	}
	public function setOption(int $option,mixed $value) : bool {
		return match(Loop::name()){
			'Swoole' => $this->socket->setOption(SOL_TCP & SOL_UDP,$option,$value),
			'Revolt' => socket_set_option($this->socket,SOL_TCP & SOL_UDP,$option,$value)
		};
	}
	public function getOption(int $option) : mixed {
		return match(Loop::name()){
			'Swoole' => $this->socket->getOption(SOL_TCP & SOL_UDP,$option),
			'Revolt' => socket_get_option($this->socket,SOL_TCP & SOL_UDP,$option)
		};
	}
	public function setupTls(TlsContext $context) : bool {
		switch(Loop::name()){
			case 'Swoole':
				$this->socket->setProtocol($context->toSwooleConfig());
				return $this->socket->sslHandshake();
			case 'Revolt':
				$stream = $this->getResource();
				stream_context_set_options($stream,$context->toStreamContext());
				do {
					$result = stream_socket_enable_crypto($stream,true);
				} while(is_int($result));
				return $result;
		}
	}
	public function getResource() : mixed {
		static $resource = null;
		if(is_resource($resource) === false){
			$resource = socket_export_stream($this->socket);
			stream_set_blocking($resource,false);
		}
		return $resource;
	}
	private function check(string $ip,int $port) : void {
		if(filter_var($ip,FILTER_VALIDATE_IP) === false){
			throw new \InvalidArgumentException('Invalid IP !');
		}
		$options = ['options'=>['min_range'=>0,'max_range'=>65535]];
		if(filter_var($port,FILTER_VALIDATE_INT,$options) === false){
			throw new \InvalidArgumentException('Invalid PORT !');
		}
	}
	public function __destruct(){
		if($this->isClosed() === false){
			$this->close();
		}
	}
}

?>