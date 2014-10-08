<?php

namespace Slipstream\StorageEngine;

interface StorageEngineInterface
{
    public function finish();
    public function read();
    public function purge();
    public function write($data);
}
