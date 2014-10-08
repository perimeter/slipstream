[![Build Status](https://secure.travis-ci.org/perimeter/slipstream.png)](http://travis-ci.org/perimeter/slipstream)

Slipstream
==========

Small php lib to assist with processing asynchronous requests (e.g http, tcp, mysql).

## Store Example

Currently only the File storage engine and Http(s) request types are supported. Http
requests are fulfilled using libcurl.

```php
$request = new Slipstream\RequestType\Http(array(
    'url' => 'http://example.com/',
    'headers' => array('x-foo' => 'bar')
));

// defaults to /tmp/slipstream
$engine = new Slipstream\StorageEngine\File();
Slipstream\Collector::engine($engine);

$result = Slipstream\Collector::store($request);
```

## Worker Request Execution

The worker will work through the request backlog until there is no work remaining. A single 
worker should only be running on a server at a given time. The worker can be executed at
any desired interval, but best ran at least every 5 minutes via cron scheduling.

    $ php bin/slipstream
    Processing Job: ...............DONE

## Internals

As requests are stored, they are written to a serialized object log. The log mechanism
attempts to uniquely identify request sources and uses an hashing algorithm to map to an
appropriate log slot. Log targets are also switched every minute up to a max of 10 logs,
after which the oldest unprocessed log will become overwritten. This helps keep the
disk usage minimal should the worker fail to execute.

Work entering a processing state will be moved to another location so that it does
not get overwritten during processing. Generally, processing should be monitored such
that it doesn't take a worker more than 8 minutes to process its work. Should this
situation arise, work could be overwritten before the worker is able to get to it.

An object log will be moved to a failed state if work in the log fails 3 times 
consecutively  to execute. Failures use an exponential back-off to help improve success
probability.

Logs which error out will be moved to the 'failed' directory in the slipstream log path.
These logs should be dealt with, but may eventually become overwritten if the same
failures continue.
