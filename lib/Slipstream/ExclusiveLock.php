<?php
/*
CLASS ExclusiveLock
Description
==================================================================
This is a pseudo implementation of mutex since php does not have
any thread synchronization objects
This class uses flock() as a base to provide locking functionality.
Lock will be released in following cases
1 - user calls unlock
2 - when this lock object gets deleted
3 - when request or script ends
==================================================================
Usage:

//get the lock
$lock = new ExclusiveLock("mylock");

//lock
if ($lock->lock( ) == FALSE) {
    error("Locking failed");
}
//--
//Do your work here
//--

//unlock
$lock->unlock();
===================================================================
*/

namespace Slipstream;

class ExclusiveLock
{
    protected $key   = null;  //user given value
    protected $file  = null;  //resource to lock
    protected $own   = FALSE; //have we locked resource

    function __construct($key) 
    {
        $this->key = $key;
        //create a new resource or get exisitng with same key
        $this->file = fopen("$key.lockfile", 'w+');
    }


    function __destruct() 
    {
        if ($this->own == TRUE) {
            $this->unlock( );
        }
    }


    function lock() 
    {
        if (!flock($this->file, LOCK_EX)) { //failed
            $key = $this->key;
            error_log("Slipstream\ExclusiveLock::lock FAILED to acquire lock [$key]");
            return FALSE;
        }
        ftruncate($this->file, 0); // truncate file
        //write something to just help debugging
        fwrite( $this->file, "Locked\n");
        fflush( $this->file );

        $this->own = TRUE;
        return $this->own;
    }


    function unlock() 
    {
        $key = $this->key;
        if ($this->own == TRUE) {
            if (!flock($this->file, LOCK_UN)) { //failed
                error_log("Slipstream\ExclusiveLock::lock FAILED to release lock [$key]");
                return FALSE;
            }
            ftruncate($this->file, 0); // truncate file
            //write something to just help debugging
            fwrite( $this->file, "Unlocked\n");
            fflush( $this->file );
        } else {
            error_log("Slipstream\ExclusiveLock::unlock called on [$key] but its not acquired by caller");
        }
        $this->own = FALSE;
        return $this->own;
    }
};
