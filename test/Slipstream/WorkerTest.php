<?php

class Slipstream_WorkerTest extends PHPUnit_Framework_TestCase
{
    public function testRun()
    {
        $engine = new Slipstream\StorageEngine\File(array('path' => '/tmp/slipstream-test'));
        Slipstream\Collector::engine($engine);
        $request = new Slipstream\RequestType\Http(array(
            'url' => 'http://http://www.iana.org/domains/example',
        ));
        Slipstream\Collector::store($request);
        Slipstream\Worker::engine($engine);

        ob_start();
        $result = Slipstream\Worker::run();
        $output = ob_get_contents();
        ob_end_clean();

        $this->assertTrue($result !== FALSE); 
        $this->assertTrue(is_integer($result)); 
        $this->assertTrue(strlen($output) > 0);

        Slipstream\Collector::purge();
    }
}
