<?php
namespace Sharedance;

class SessionHandler implements \SessionHandlerInterface
{

    private $host;
    private $port = 1042;
    private $timeout = 10;

    private $socket;

    public function __construct($host, $port = null, $timeout = null)
    {
        $this->host = $host;
        if ($port) {
            $this->port = $port;
        }
        if ($timeout) {
            $this->timeout = $timeout;
        }
    }

    private function connect()
    {
        $errno = $errstr = null;
        if ( !$this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout) ) {
            throw new SessionHandlerException( sprintf('socket failed on %s "%s"', $this, $errstr), $errno);
        }

        return true;
    }

    private function send($command)
    {
        if ( !$this->connect() || !$res = fwrite( $this->socket, $command ) ) {
            $message = sprintf('Failed to send command %s to %s', $command, $this->__toString());
            throw new SessionHandlerException( $message );
        }
        $data = '';
        while (!feof($this->socket)) {
            $data .= fread( $this->socket, 4096 );
        }

        return $data;
    }

    public function open($save_path, $session_id)
    {
        return $this->connect();
    }

    public function read($key)
    {
        $command = 'F' . pack('N', strlen($key)) . $key;
        $response = $this->send($command);

        return ($response === '') ? false : $response;
    }

    public function write($key, $data)
    {
        $command = 'S' . pack('NN', strlen( $key ), strlen( $data )) . $key . $data;
        $response = $this->send($command);

        if ($response != "OK\n") {
            throw new SessionHandlerException( sprintf('Failed to write data to %s[%s]', $this, $response) );
        }

        return true;
    }

    public function close()
    {
        if ( is_resource( $this->socket ) ) {
            return @fclose($this->socket);
        }

        return true;
    }

    public function destroy($session_id)
    {
        $command= 'D' . pack('N', strlen( $session_id )) . $session_id;
        $response = $this->send( $command );

        if ($response != "OK\n") {
            throw new SessionHandlerException( sprintf('Failed to write data to %s[%s]', $this, $response) );
        }

        return true;
    }

    public function gc($maxlifetime)
    {
        return true;
    }

    public function __toString()
    {
        return '[' . $this->host . ':' . $this->port . ']';
    }

}
