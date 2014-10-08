<?php

namespace Slipstream;

class Core
{
    /*
    This is a fast, non-cryptographic hash function that's good for sharding and hash tables.
    See: http://en.wikipedia.org/wiki/Fowler-Noll-Vo_hash_function
    */
    public static function fnv_1a_hash($str)
    {
        $buf = str_split($str);
        $hash = 16777619;
        foreach($buf as $chr)
        {
            $hash = $hash ^ ord($chr);
            $hash += ($hash << 1) + ($hash << 4) + ($hash << 7) + ($hash << 8) + ($hash << 24); //This is $hash * 2166136261
        }
        return $hash & 0x0ffffffff;
    }
}
