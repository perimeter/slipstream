<?php

namespace Slipstream\RequestType;

class Tcp implements RequestTypeInterface
{
    protected $dest = '127.0.0.1';
    protected $destPort = 80;
    protected $bytes = '';

    public function execute()
    {
        echo $this->dest . "\n";
        $socket = fsockopen($this->dest, 80, $errno, $errstr, 5);
        if(!$socket)
        {
            return false;
        }
        echo $this->bytes . "\n";
        fwrite($socket, $this->bytes);
        while(!feof($socket))
        {
            echo fgets($socket, 128);
        }
        fclose($socket);
        die();
    }
}
