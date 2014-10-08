<?php

namespace Slipstream\StorageEngine;

use Slipstream\ExclusiveLock;

class File extends StorageEngine
{
    const FILE_STATE_COLLECTING = 'collecting';
    const FILE_STATE_PROCESSING = 'processing';
    const FILE_STATE_READY      = 'ready';
    const FILE_STATE_ERROR      = 'error';

    protected $options = array(
        'path' => '/tmp/slipstream',
        'prepend' => '',
        'rotate_interval' => 300 // 5 minutes
    );

    public function __construct($options = array())
    {
        if (!is_array($options)) {
            $options = array();
        }
        parent::__construct($options);
        $this->options['file_states'] = array(
            self::FILE_STATE_COLLECTING,
            self::FILE_STATE_PROCESSING,
            self::FILE_STATE_READY,
            self::FILE_STATE_ERROR,
        );
        $this->pathCheck();
    }

    /**
     * Removes the job file
     */
    public function finish()
    {
        // get a processing lock
        $processing_lock = $this->getStateLock(self::FILE_STATE_PROCESSING);
        if (!$processing_lock) {
            return false;
        }

        // get job file
        $job_file = $this->getJobFile($processing_lock);

        if (file_exists($job_file)) {
            return unlink($job_file);
        } else {
            return false;
        }
    }

    /**
     * Purges all log files
     */
    public function purge()
    {
        $states = array();
        foreach ($this->options['file_states'] as $key => $state) {
            $states[$state] = false;
        }

        // get a lock for all states
        $locked = true;
        foreach ($states as $state => $status) {
            $states[$state] = $this->getStateLock($state);
            if (!$states[$state]) {
                $locked = false;
            }
        }

        if ($locked) {
            foreach ($states as $state => $status) {
                $files = $this->getLogFileList($state);
                foreach ($files as $key => $file) {
                    unlink($file);
                }
            }
        }

        foreach ($states as $state => $lock) {
            if (!is_object($lock)) {
                continue;
            }
            $lock->unlock();
        }
    }

    /**
     * Pulls next job from workflow
     *
     * 1.) Moves any non-active collecting job file to ready
     * 2.) Looks for an existing processing job file
     * 3.) If processing job exists, pull from processing job
     * 4.) If no processing job exists, move next ready job to processing
     */
    public function read()
    {
        $datafile = $this->getActiveLogFile();
        $this->makeReady(basename($datafile));

        // get a processing lock
        $processing_lock = $this->getStateLock(self::FILE_STATE_PROCESSING);
        if (!$processing_lock) {
            return false;
        }

        // get job file
        $job_file = $this->getJobFile($processing_lock);
        if (!$job_file) {
            $processing_lock->unlock();

            return false;
        }

        // load file as a stream, and read in a job
        $error = false;
        $handle = fopen($job_file, "r");

        // read uint32 job size
        $size = @array_pop(@unpack('V', fread($handle, 4)));

        if (!is_int($size) || $size < 1) {
            // TODO: this is an error state
            fclose($handle);
            $processing_lock->unlock();

            return false;
        }

        // get a job
        $job = fread($handle, $size);
        if (!$job) {
            // TODO: this is an error state
            fclose($handle);
            $processing_lock->unlock();

            return false;
        }

        // get remainder of data
        $data = stream_get_contents($handle);
        fclose($handle);

        // re-write remaining jobs to the job file
        file_put_contents($job_file, $data, LOCK_EX);

        // release processing lock
        $processing_lock->unlock();

        // return jobs
        return $job;
    }

    /**
     * Safe way to write a job to a job file using
     */
    public function write($data)
    {
        $datafile = $this->getActiveLogFile();
        $this->makeReady(basename($datafile));

        // get a lock on the collecting pool
        $collecting_lock = $this->getStateLock(self::FILE_STATE_COLLECTING);
        if (!$collecting_lock) {
            return false;
        }

        // prepend the data with the payload size using a uint32
        $size = pack('V', strlen($data));

        // write the data to the log file
        file_put_contents($datafile, $size . $data, FILE_APPEND | LOCK_EX);

        // release the lock
        $collecting_lock->unlock();

        return true;
    }

    /**
     * returns the current active log file name (shifts with time)
     */
    private function getActiveLogFile()
    {
        $now = time();
        $interval = ceil($now / $this->options['rotate_interval']) * $this->options['rotate_interval'];
        $prepend = $this->options['prepend'];
        $name = (!empty($prepend)) ? $prepend . '_' . $interval : $interval;

        return $this->options['path'] . '/' . self::FILE_STATE_COLLECTING . '/' . $name;
    }

    /**
     * Gets a job file in the processing state or ready state
     */
    private function getJobFile($processing_lock)
    {
        $job_file = false;
        $ready_files = array();
        $processing_files = array();

        // look for existing processing to do
        $processing_files = $this->getLogFileList(self::FILE_STATE_PROCESSING);
        // reverse the files that are found (oldest file will be first)
        krsort($processing_files);
        // if no processing, look for ready
        if (count($processing_files) < 1) {
            // get a ready lock
            $ready_lock = $this->getStateLock(self::FILE_STATE_READY);
            if (!$ready_lock) {
                $processing_lock->unlock();

                return false;
            }
            // move states
            $ready_files = $this->getLogFileList(self::FILE_STATE_READY);
            krsort($ready_files);
            if (count($ready_files) > 0) {
                $job_file = array_pop($ready_files);
                $state = self::FILE_STATE_PROCESSING;
                $job_file = $this->changeState($ready_lock, $processing_lock, $job_file, $state);
                $ready_lock->unlock();
            }
        } else {
            if (count($processing_files) > 0) {
                $job_file = array_pop($processing_files);
            }
        }

        return $job_file;
    }

    /**
     * Moves all non-actice collecting logs to ready state
     */
    private function makeReady($active)
    {
        // get a lock for both states
        $collecting_lock = $this->getStateLock(self::FILE_STATE_COLLECTING);
        $ready_lock = $this->getStateLock(self::FILE_STATE_READY);
        if (!$collecting_lock || !$ready_lock) {
            $collecting_lock->unlock();
            $ready_lock->unlock();

            return false;
        }
        // move states
        $collecting_files = $this->getLogFileList(self::FILE_STATE_COLLECTING);
        foreach ($collecting_files as $key => $file) {
            if (strstr($file, $active)) {
                continue;
            }
            $this->changeState($collecting_lock, $ready_lock, $file, self::FILE_STATE_READY);
        }
        // release lock
        $collecting_lock->unlock();
        $ready_lock->unlock();
    }

    /**
     * Changes the state of a log ensuring locks
     */
    private function changeState($lock_from, $lock_to, $file, $state)
    {
        if (!$lock_from->lock() || !$lock_to->lock()) {
            return false;
        }
        $newfile = $this->options['path'] . '/' . $state . '/' . basename($file);
        $result = rename($file, $newfile);
        if (!$result) {
            return false;
        }

        return $newfile;
    }

    /**
     * Gets a list of log files by dir
     * @param  [type] $dir [description]
     * @return [type] [description]
     */
    private function getLogFileList($state)
    {
        $prepend = $this->options['prepend'];
        $dir = $this->options['path'] . '/' . $state;
        $files = array();
        if ($handle = opendir($dir)) {
            while (false !== ($entry = readdir($handle))) {
                $file = $dir . '/' . $entry;
                if (!is_file($file) || strstr($entry, 'lockfile')) {
                    // don't current file, move dirs or lockfiles
                    continue;
                }
                if (!empty($prepend) && !strstr($entry, $prepend)) {
                    continue;
                }
                $files[] = $file;
            }
            closedir($handle);
        }

        return $files;
    }

    /**
     * Gets a lock on a state or returns false
     */
    private function getStateLock($state)
    {
        $prepend = $this->options['prepend'];
        $lock_name = (!empty($prepend)) ? $prepend . '_write' : 'write';
        $lock = new ExclusiveLock($this->options['path'] . '/' . $state . '/' . $lock_name);
        $lock_timeout = false;
        $lock_tries = 0;
        $lock_tries_limit = 20; // will timeout after 200ms
        while ($lock->lock() == false && $lock_tries < $lock_tries_limit) {
            $lock_tries++;
            usleep(10000); // sleep for 0.01 of a second
            // timeout on getting a lock occurred
            if ($lock_tries == $lock_tries_limit) {
                $lock_timeout = true;
            }
        }
        if ($lock_timeout) {
            return false;
        }

        return $lock;
    }

    /**
     * Create paths if not exists
     */
    private function pathCheck()
    {
        foreach ($this->options['file_states'] as $key => $path) {
            $dir = $this->options['path'] . '/' . $path;
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }
    }
}
