<?php

class Slipstream_CollectorTest extends PHPUnit_Framework_TestCase
{
    public function testHttpRequestType()
    {
        $request = new Slipstream\RequestType\Http(array(
            'url' => 'http://www.example.com/',
            'headers' => array('x-something' => 'value')
        ));
        $this->assertObjectHasAttribute('scheme', $request);
        $this->assertObjectHasAttribute('headers', $request);
        $this->assertObjectHasAttribute('data', $request);
    }

    public function testStore()
    {
        $engine = new Slipstream\StorageEngine\File(array('path' => '/tmp/slipstream-test'));
        Slipstream\Collector::engine($engine);
        $request = new Slipstream\RequestType\Http(array(
            'url' => 'http://www.example.com/',
            'headers' => array('x-something' => 'value')
        ));
        $result = Slipstream\Collector::store($request);
        $this->assertTrue($result);
    }

    public function testPurge()
    {
        $engine = new Slipstream\StorageEngine\File(array('path' => '/tmp/slipstream-test'));
        Slipstream\Collector::engine($engine);
        Slipstream\Collector::purge();
    }
}
