<?php

namespace Slipstream\StorageEngine;

abstract class StorageEngine implements StorageEngineInterface
{
    protected $options = array(
        'active_count' => 10,
        'active_duration' => 60
    );

    public function __construct($options = array())
    {
        if (!array_key_exists('active_count', $this->options)) {
            $this->options['active_count'] = 10;    // preserve 10 log round robin
        }

        if (!array_key_exists('active_duration', $this->options)) {
            $this->options['active_duration'] = 60;    // one minute default
        }

        $this->options = array_merge($this->options, $options);
    }

    /**
     * Attempts to semi-uniquely identify a slipstream user
     */
    public function getRequestThumbprint()
    {
        $sapi = php_sapi_name();
        $thumbprint = null;
        switch ($sapi) {
            // apache uses the server addr port, and remote addr + user agent
            case 'apache2handler':
                $thumbprint = $_SERVER['SERVER_ADDR'] . $_SERVER['SERVER_PORT'];
                $thumbprint .= ((!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']) . $_SERVER['HTTP_USER_AGENT'];
                break;
            // cli uses the username and shell args
            case 'cli':
                $thumbprint = $_SERVER['USER'] . implode('', $_SERVER['argv']);
                break;
            // otherwise use process id
            default:
                $thumbprint = getmypid();
                break;
        }

        return md5($thumbprint);
    }
}
