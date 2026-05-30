<?php

declare(strict_types = 1);

namespace Tak\Asyncio\Socket;

defined('CRLF') || define('CRLF',chr(13).chr(10));

class WebSocket {
	protected StreamSocket $stream;
	protected string $buffer;

	public function __construct(public string $path = '/',public array $headers = array()){
		$this->stream = new StreamSocket();
		$this->buffer = strval(null);
	}
	public function accept(float $timeout = -1) : object | false {
		if($client = $this->stream->accept($timeout)){
			return new class($client) extends WebSocket {
				public function __construct(protected StreamSocket $stream){
					$this->buffer = strval(null);
				}
				public function handleHandshake(array $response,float $timeout = -1) : bool {
					$request = $this->stream->read(null,$timeout);
					if($request == false || str_contains($request,'Sec-WebSocket-Key') === false){
						return false;
					}
					if(preg_match('#Sec-WebSocket-Key:\s+(?<key>.+)$#m',$request,$matches)){
						$key = trim($matches['key']);
						$accept = base64_encode(sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11',true));
						$answer = 'HTTP/1.1 101 Switching Protocols'.CRLF;
						$answers = $response + array(
							'Upgrade'=>'websocket',
							'Connection'=>'Upgrade',
							'Sec-WebSocket-Accept'=>$accept
						);
						$formatted = str_replace(['='],[': '],http_build_query(data : $answers,arg_separator : CRLF));
						return boolval($this->stream->write($answer.$formatted.CRLF.CRLF,$timeout));
					} else {
						return false;
					}
				}
				public function read(? int $length = null,float $timeout = -1) : string | false {
					if(is_null($length) and strlen($this->buffer) > 0){
						$content = $this->buffer;
						$this->buffer = strval(null);
						return $content;
					}
					$resume = false;
					while(is_null($length) || strlen($this->buffer) < $length){
						$frame = $this->readFrame($timeout,true);
						if($frame === false){
							return false;
						}
						extract($frame);
						if($opcode === 0x09){
							$this->write($payload,$timeout,0x0A);
							continue;
						}
						if($opcode === 0x0A){
							continue;
						}
						if($opcode === 0x08){
							$this->stream->close();
							return false;
						}
						if($opcode === 0x00){
							if($resume === false){
								continue;
							}
							if(is_null($length)){
								return $payload;
							}
							$this->buffer .= $payload;
							if($fin){
								break;
							} else {
								continue;
							}
						}
						if($opcode === 0x01 || $opcode === 0x02){
							if(is_null($length)){
								return $payload;
							}
							$this->buffer .= $payload;
							$resume = true;
							if($fin){
								break;
							} else {
								continue;
							}
						}
					}
					if(is_null($length)){
						return false;
					}
					$content = substr($this->buffer,0,$length);
					$this->buffer = substr($this->buffer,$length);
					return $content;
				}
				public function write(string $data,float $timeout = -1,int $opcode = 0x1) : int | false {
					return $this->writeAll($this->generateFrame($data,$opcode,false),$timeout);
				}
			};
		} else {
			return false;
		}
	}
	public function connect(string $host,int $port,float $timeout = -1) : bool {
		return boolval($this->stream->connect($host,$port,$timeout) and $this->doHandshake($host,$port,$timeout));
	}
	private function doHandshake(string $host,int $port,float $timeout = -1) : bool {
		$key = base64_encode(random_bytes(16));
		$header = 'GET '.$this->path.' HTTP/1.1'.CRLF;
		$headers = $this->headers + array(
			'Host'=>$host.chr(58).$port,
			'Upgrade'=>'websocket',
			'Connection'=>'Upgrade',
			'Sec-WebSocket-Key'=>$key,
			'Sec-WebSocket-Protocol'=>'binary',
			'Sec-WebSocket-Version'=>'13'
		);
		$formatted = str_replace(['='],[': '],http_build_query(data : $headers,arg_separator : CRLF));
		$this->stream->write($header.$formatted.CRLF.CRLF,$timeout);
		$response = $this->stream->read(null,$timeout);
		if($response == false || str_contains($response,'101 Switching Protocols') === false){
			return false;
		}
		if(preg_match('#Sec-WebSocket-Accept:\s(?<token>.+)$#mU',$response,$matches)){
			$expected = base64_encode(sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11',true));
			return boolval($expected === trim($matches['token']));
		} else {
			return false;
		}
	}
	public function write(string $data,float $timeout = -1,int $opcode = 0x1) : int | false {
		return $this->writeAll($this->generateFrame($data,$opcode,true),$timeout);
	}
	public function read(? int $length = null,float $timeout = -1) : string | false {
		if(is_null($length) and strlen($this->buffer) > 0){
			$content = $this->buffer;
			$this->buffer = strval(null);
			return $content;
		}
		$resume = false;
		while(is_null($length) || strlen($this->buffer) < $length){
			$frame = $this->readFrame($timeout,false);
			if($frame === false){
				return false;
			}
			extract($frame);
			if($opcode === 0x09){
				$this->write($payload,$timeout,0x0A);
				continue;
			}
			if($opcode === 0x0A){
				continue;
			}
			if($opcode === 0x08){
				$this->stream->close();
				return false;
			}
			if($opcode === 0x00){
				if($resume === false){
					continue;
				}
				if(is_null($length)){
					return $payload;
				}
				$this->buffer .= $payload;
				if($fin){
					break;
				} else {
					continue;
				}
			}
			if($opcode === 0x01 || $opcode === 0x02){
				if(is_null($length)){
					return $payload;
				}
				$this->buffer .= $payload;
				$resume = true;
				if($fin){
					break;
				} else {
					continue;
				}
			}
		}
		if(is_null($length)){
			return false;
		}
		$content = substr($this->buffer,0,$length);
		$this->buffer = substr($this->buffer,$length);
		return $content;
	}
	protected function maskPayload(string $payload,string $mask) : string {
		$out = strval(null);
		$len = strlen($payload);
		for($i = 0; $i < $len; $i++){
			$out .= $payload[$i] ^ $mask[$i % 4];
		}
		return $out;
	}
	protected function writeAll(string $data,float $timeout = -1) : int | false {
		$written = 0;
		while($written < strlen($data)){
			$chunk = $this->stream->write(substr($data,$written),$timeout);
			if($chunk === false || $chunk <= 0){
				return false;
			}
			$written += $chunk;
		}
		return $written;
	}
	protected function readExact(int $length,float $timeout = -1) : string | false {
		$data = strval(null);
		while(strlen($data) < $length){
			$chunk = $this->stream->read($length - strlen($data),$timeout);
			if($chunk === false || $chunk === strval(null)){
				return false;
			}
			$data .= $chunk;
		}
		return $data;
	}
	protected function generateFrame(string $data,int $opcode = 0x1,bool $masked = false) : string {
		$len = strlen($data);
		$frame = chr(0x80 | ($opcode & 0x0F));
		if($len <= 125){
			$frame .= chr(intval($masked ? 0x80 : 0x00) | $len);
		} elseif($len <= 65535){
			$frame .= chr(intval($masked ? 0x80 : 0x00) | 126).pack('n',$len);
		} else {
			$frame .= chr(intval($masked ? 0x80 : 0x00) | 127).pack('J',$len);
		}
		if($masked){
			$mask = random_bytes(4);
			$frame .= $mask;
			$data = $this->maskPayload($data,$mask);
		}
		return strval($frame.$data);
	}
	protected function readFrame(float $timeout = -1,bool $maskedExpected = false) : array | false {
		$header = $this->readExact(2,$timeout);
		if($header === false) return false;
		$bytes = unpack('C2',$header);
		$fin = boolval($bytes[1] & 0x80);
		$opcode = intval($bytes[1] & 0x0F);
		$masked = boolval($bytes[2] & 0x80);
		$len = intval($bytes[2] & 0x7F);
		if($len === 126){
			$ext = $this->readExact(2,$timeout);
			if($ext === false) return false;
			$len = unpack('n',$ext)[true];
		} elseif($len === 127){
			$ext = $this->readExact(8,$timeout);
			if($ext === false) return false;
			$len = unpack('J',$ext)[true];
		}
		if($maskedExpected === $masked){
			$mask = strval(null);
			if($masked){
				$mask = $this->readExact(4,$timeout);
				if($mask === false) return false;
			}
			$payload = $this->readExact(intval($len),$timeout);
			if($payload === false) return false;
			if($masked){
				$payload = $this->maskPayload($payload,$mask);
			}
			return compact($fin,$opcode,$payload);
		} else {
			return false;
		}
	}
	public function __call(string $name,array $arguments) : mixed {
		return call_user_func_array(array($this->stream,$name),$arguments);
	}
}

?>