<?php

declare(strict_types = 1);

namespace Tak\Asyncio\Socket;

final class TlsContext {
	public function __construct(
		public ? string $certificateFile = null,
		public ? string $privateKeyFile = null,
		public bool $verifyPeer = true,
		public bool $verifyPeerName = true,
		public ? string $peerName = null,
		public int $securityLevel = 2,
		public ? string $caFile = null,
		public string $ciphers = 'DEFAULT'
	){
		if($this->certificateFile !== null and file_exists($this->certificateFile) === false){
			throw new \InvalidArgumentException('Certificate file not found : '.$this->certificateFile);
		}
		if($this->privateKeyFile !== null and file_exists($this->privateKeyFile) === false){
			throw new \InvalidArgumentException('Private key file not found : '.$this->privateKeyFile);
		}
		if($this->privateKeyFile !== null and $this->certificateFile === null){
			throw new \LogicException('A private key was provided, but no certificate file was specified');
		}
		if($this->caFile !== null and file_exists($this->caFile) === false){
			throw new \InvalidArgumentException('CA file not found : '.$this->caFile);
		}
		if($this->securityLevel < 0 or $this->securityLevel > 5){
			throw new \InvalidArgumentException('Security level must be between 0 and 5');
		}
	}
	public function toStreamContext() : array {
		return [
			'ssl' => array_filter([
				'local_cert' => $this->certificateFile,
				'local_pk' => $this->privateKeyFile,
				'verify_peer' => $this->verifyPeer,
				'verify_peer_name' => $this->verifyPeerName,
				'peer_name' => $this->peerName,
				'cafile' => $this->caFile,
				'ciphers' => $this->ciphers,
				'capture_peer_cert' => true
			]),
		];
	}
	public function toSwooleConfig() : array {
		return array_filter([
			'ssl_cert_file' => $this->certificateFile,
			'ssl_key_file' => $this->privateKeyFile,
			'ssl_verify_peer' => $this->verifyPeer,
			'ssl_host_name' => $this->peerName,
			'ssl_cafile' => $this->caFile,
			'ssl_ciphers' => $this->ciphers
		]);
	}
}

?>