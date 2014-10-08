<?php

namespace Slipstream;

use Slipstream\StorageEngine\StorageEngineInterface;

class Worker extends Singleton
{
    /**
     * defines how many concurrent workers can start
     */
    const MAX_WORKER_COUNT = 6;

    /**
     * Sets the storage engine
     */
    public static function engine(StorageEngineInterface $Engine)
    {
        $Slipstream = self::getInstance(__CLASS__);
        $Slipstream->engine = $Engine;

        return true;
    }

    /**
     * Runs in a loop until there is no more work to do
     */
    public static function run($worker_limit = null)
    {
        // check worker limit
        if (self::shouldLimit($worker_limit)) {
            return;
        }

        // result will be numeric count of jobs if successful
        do {
            $result = self::work();
            if ($result === false) {
                self::error(__FILE__ . " failed processing job(s)");
                exit(1);
            }
            usleep(10000); // sleep for 0.01 of a second to be sure the cpu doesn't pin
        } while ($result !== false && $result > 0);

        return $result;
    }

    /**
     * Logs message to stderr
     */
    protected static function error($message)
    {
        $stderr = fopen('php://stderr', 'w');
        fwrite($stderr, 'Error: ' . $message . "\n");
        fclose($stderr);
    }

    /**
     * Performs the work inside a while loop. A single loop
     * represents a single object log
     */
    protected static function work()
    {
        $Slipstream = self::getInstance(__CLASS__);

        echo "Processing Job: ";
        $count = 0;
        while ($job = $Slipstream->engine->read()) {
            $Request = unserialize($job);

            if (!is_object($Request)) {
                // TODO
                // $Slipstream->engine->fail();
                echo "FAIL\n";

                return false;
            }

            //TODO log activity with splunk?

            // try the work 3 times with exp back off, then fail
            $retries = 0;
            do {
                echo ".";
                $success = $Request->execute(); // this runs the job
                if (!$success) {
                    $retries++;
                    $sleep = self::getDelay($retries);
                    sleep($sleep);
                }
            } while ($success == false && $retries < 3);

            if ($success == false) {
                // TODO
                // $Slipstream->engine->fail();
                echo "FAIL\n";

                return false;
            } else {
                $count++;
            }
        }

        // check result
        if ($count > 0) {
            echo "FINISH\n";
        } else {
            echo "DONE\n";
        }

        $Slipstream->engine->finish();

        return $count;
    }

    /**
     * Limits number of concurrent workers
     */
    protected static function shouldLimit($worker_limit = null)
    {
        if (is_null($worker_limit)) {
            $worker_limit = self::MAX_WORKER_COUNT;
        }

        // get a count of how many slipstream processes are running
        $cmd = 'ps ax | grep bin\/slipstream | grep -v grep | wc -l';
        $output = `$cmd`;
        $workers = intval(trim($output));

        if ($workers >= $worker_limit) {
            $limit = true;
        } else {
            $limit = false;
        }

        return $limit;
    }

    /**
     * Calculates exponential back-off
     */
    protected static function getDelay($retries)
    {
        return pow(2, $retries);
    }
}
