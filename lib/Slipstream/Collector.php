<?php

namespace Slipstream;

use Slipstream\StorageEngine\StorageEngineInterface;
use Slipstream\RequestType\RequestTypeInterface;

class Collector extends Singleton
{
    /**
     * Sets the storage engine
     */
    public static function engine(StorageEngineInterface $Engine)
    {
        $Slipstream = self::getInstance(__CLASS__);
        $Slipstream->engine = $Engine;
        return TRUE;
    }

    /**
     * Purges the request storage
     */
    public static function purge()
    {
        $Slipstream = self::getInstance(__CLASS__);
        return $Slipstream->engine->purge();
    }

    /**
     * Stores the request
     */
    public static function store(RequestTypeInterface $request)
    {
        $Slipstream = self::getInstance(__CLASS__);
        return $Slipstream->engine->write(serialize($request));
    }
}
