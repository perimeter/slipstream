<?php

class Slipstream_StorageEngineFileTest extends PHPUnit_Framework_TestCase
{
    public function testWrite()
    {
        $options = array(
            'path' => '/tmp/slipstream-test',
            'rotate_interval' => 2
        );
        $engine = new Slipstream\StorageEngine\File($options);

        // pre-purge
        $engine->purge();

        // sleep until next interval
        $now = time();
        $interval = ceil($now / $options['rotate_interval']) * $options['rotate_interval'];
        sleep($interval - time() + 1);

        // make sure 10 log entries are created
        for ($i = 0; $i < 10; $i++) {
            $result = $engine->write(serialize(array('foo' => 'bar')));
            $this->assertTrue($result);
        }

        // sleep so this data can be moved to ready state
        sleep($options['rotate_interval'] + 1);
    }

    public function testRead()
    {
        $engine = new Slipstream\StorageEngine\File(array(
            'path' => '/tmp/slipstream-test'
        ));
        $count = 0;
        while ($result = $engine->read()) {
            $count++;
            $this->assertTrue($result !== false);
            $data = unserialize($result);
            $this->assertTrue($data !== false);
            $this->assertTrue(is_array($data));
            $this->assertTrue(array_key_exists('foo', $data));
            $this->assertTrue($data['foo'] == 'bar');
        }

        $this->assertTrue($count > 1);
        $result = $engine->finish();
        $this->assertTrue($result !== false);
    }

    public function testPurge()
    {
        $engine = new Slipstream\StorageEngine\File(array('path' => '/tmp/slipstream-test'));
        $engine->purge();
    }
}
