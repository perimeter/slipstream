<?php
namespace Slipstream\RequestType;

use Slipstream\Curl;

class Http implements RequestTypeInterface
{
    const LB = "\r\n";

    private $method = 'GET';
    private $version = '1.1';

    private $scheme = array(
        'scheme' => 'http',
        'host' => 'localhost',
        'path' => '/',
        'query' => '',
        'port' => '',
    );

    public $headers = array(
        'User-Agent' => 'Slipstream',
        'Accept' => '*/*'
    );

    private $data = null;

    public function __construct($options = array())
    {
        $url = (is_array($options) && array_key_exists('url', $options)) ? $options['url'] : '/';
        $headers = (is_array($options) && array_key_exists('headers', $options)) ? $options['headers'] : array();
        $data = (is_array($options) && array_key_exists('data', $options)) ? $options['data'] : null;
        $this->scheme = array_merge($this->scheme, parse_url($url));
        $this->headers = array_merge($this->headers, $headers);
        $this->data = $data;
    }

    /**
     * Makes curl http request (currently tested with GET requests only)
     */
    public function execute()
    {
        $url = $this->scheme['scheme'] . '://';
        $url .= $this->scheme['host'];

        if (!empty($this->scheme['port']) && is_numeric($this->scheme['port'])) {
            $url .= ':' . $this->scheme['port'];
        }

        $url .= $this->scheme['path'];

        if (!empty($this->scheme['query'])) {
            $url .= '?' . $this->scheme['query'];
        }

        $Curl = new Curl($url);
        $Curl->returntransfer = 1;

        if (strtoupper($this->method) !== 'GET') {
            $Curl->customrequest = strtoupper($this->method);
        }

        $headers = array();
        foreach ($this->headers as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        $Curl->httpheader =$headers;

        if (!empty($this->data)) {
            $Curl->postfields = $this->data;
        }

        $Curl->exec();

        if ($Curl->info('http_code') >= 400) {
            return false;
        }

        return true;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function setMethod($method)
    {
        $this->method = $method;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function setVersion($version)
    {
        $this->version = $version;
    }
}
