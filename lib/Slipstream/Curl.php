<?php/**
 * OOCurl
 *
 * Provides an Object-Oriented interface to the PHP cURL
 * functions and clean up some of the curl_setopt() calls.
 *
 * @package OOCurl
 * @author James Socol <me@jamessocol.com>
 * @version 0.3.0
 * @copyright Copyright (c) 2008-2013, James Socol
 * @license See LICENSE
 */

/**
 * Curl connection object
 *
 * Provides an Object-Oriented interface to the PHP cURL
 * functions and a clean way to replace curl_setopt().
 *
 * Instead of requiring a setopt() function and the CURLOPT_*
 * constants, which are cumbersome and ugly at best, this object
 * implements curl_setopt() through overloaded getter and setter
 * methods.
 *
 * For example, if you wanted to include the headers in the output,
 * the old way would be
 *
 * <code>
 * curl_setopt($ch, CURLOPT_HEADER, true);
 * </code>
 *
 * But with this object, it's simply
 *
 * <code>
 * $ch->header = true;
 * </code>
 *
 * <b>NB:</b> Since, in my experience, the vast majority
 * of cURL scripts set CURLOPT_RETURNTRANSFER to true, the {@link Curl}
 * class sets it by default. If you do not want CURLOPT_RETURNTRANSFER,
 * you'll need to do this:
 *
 * <code>
 * $c = new Curl;
 * $c->returntransfer = false;
 * </code>
 *
 * @package OOCurl
 * @author James Socol <me@jamessocol.com>
 * @version 0.3.0
 */
namespace Slipstream;

class Curl
{
	/**
	 * Store the curl_init() resource.
	 * @var resource
	 */
	protected $ch = null;

	/**
	 * Store the CURLOPT_* values.
	 *
	 * Do not access directly. Access is through {@link __get()}
	 * and {@link __set()} magic methods.
	 *
	 * @var array
	 */
	protected $curlopt = array();

	/**
	 * Flag the Curl object as linked to a {@link CurlParallel}
	 * object.
	 *
	 * @var bool
	 */
	protected $multi = false;

	/**
	 * Store the response. Used with {@link fetch()} and
	 * {@link fetch_json()}.
	 *
	 * @var string
	 */
	protected $response;

	/**
	 * The version of the OOCurl library.
	 * @var string
	 */
	const VERSION = '0.3';

	/**
	 * Create the new {@link Curl} object, with the
	 * optional URL parameter.
	 *
	 * @param string $url The URL to open (optional)
	 * @return Curl A new Curl object.
	 * @throws ErrorException
	 */
	public function __construct($url = null)
	{
		// Make sure the cURL extension is loaded
		if ( !extension_loaded('curl') )
			throw new ErrorException("cURL library is not loaded. Please recompile PHP with the cURL library.");

		// Create the cURL resource
		$this->ch = curl_init();

		// Set some default options
		$this->url = $url;
		$this->returntransfer = true;

		// Applications can override this User Agent value
		$this->useragent = 'OOCurl '.self::VERSION;

		// Return $this for chaining
		return $this;
	}

	/**
	 * When destroying the object, be sure to free resources.
	 */
	public function __destruct()
	{
		$this->close();
	}

	/**
	 * If the session was closed with {@link Curl::close()}, it can be reopened.
	 *
	 * This does not re-execute {@link Curl::__construct()}, but will reset all
	 * the values in {@link $curlopt}.
	 *
	 * @param string $url The URL to open (optional)
	 * @return bool|Curl
	 */
	public function init($url = null)
	{
		// If it's still init'ed, return false.
		if ( $this->ch ) return false;

		// init a new cURL session
		$this->ch = curl_init();

		// reset all the values that were already set
		foreach ($this->curlopt as $const => $value) {
			curl_setopt($this->ch, constant($const), $value);
		}

		// finally if there's a new URL, set that
		if ( !empty($url) ) $this->url = $url;

		// return $this for chaining
		return $this;
	}

	/**
	 * Execute the cURL transfer.
	 *
	 * @return mixed
	 */
	public function exec()
	{
		return curl_exec($this->ch);
	}

	/**
	 * If the Curl object was added to a {@link CurlParallel}
	 * object, then you can use this function to get the
	 * returned data (whatever that is). Otherwise it's similar
	 * to {@link exec()} except it saves the output, instead of
	 * running the request repeatedly.
	 *
	 * @see $multi
	 * @return mixed
	 */
	public function fetch()
	{
		if ($this->multi) {
			return curl_multi_getcontent($this->ch);
		} else {
			if ($this->response) {
				return $this->response;
			} else {
				$this->response = curl_exec($this->ch);
				return $this->response;
			}
		}
	}

	/**
	 * Fetch a JSON encoded value and return a JSON
	 * object. Requires the PHP JSON functions. Pass TRUE
	 * to return an associative array instead of an object.
	 *
	 * @param bool array optional. Return an array instead of an object.
	 * @return mixed an array or object (possibly null).
	 */
	public function fetch_json($array = false)
	{
		return json_decode($this->fetch(), $array);
	}

	/**
	 * Close the cURL session and free the resource.
	 */
	public function close()
	{
		if ( !empty($this->ch) && is_resource($this->ch) )
			curl_close($this->ch);
	}

	/**
	 * Return an error string from the last execute (if any).
	 *
	 * @return string
	 */
	public function error()
	{
		return curl_error($this->ch);
	}

	/**
	 * Return the error number from the last execute (if any).
	 *
	 * @return integer
	 */
	public function errno()
	{
		return curl_errno($this->ch);
	}

	/**
	 * Get cURL version information (and adds OOCurl version info)
	 *
	 * @return array
	 */
 	public function version()
 	{
 		$version = curl_version();

 		$version['oocurl_version'] = self::VERSION;
 		$version['oocurlparallel_version'] = CurlParallel::VERSION;

 		return $version;
 	}

	/**
	 * Get information about this transfer.
	 *
	 * Accepts any of the following as a parameter:
	 *  - Nothing, and returns an array of all info values
	 *  - A CURLINFO_* constant, and returns a string
	 *  - A string of the second half of a CURLINFO_* constant,
	 *     for example, the string 'effective_url' is equivalent
	 *     to the CURLINFO_EFFECTIVE_URL constant. Not case
	 *     sensitive.
	 *
	 * @param mixed $opt A string or constant (optional).
	 * @return mixed An array or string.
	 */
	public function info($opt = false)
	{
		if (false === $opt) {
			return curl_getinfo($this->ch);
		}

		if ( is_int($opt) || ctype_digit($opt) ) {
			return curl_getinfo($this->ch,$opt);
		}

		if (constant('CURLINFO_'.strtoupper($opt))) {
			return curl_getinfo($this->ch,constant('CURLINFO_'.strtoupper($opt)));
		}
	}

	/**
	 * Magic property setter.
	 *
	 * A sneaky way to access curl_setopt(). If the
	 * constant CURLOPT_$opt exists, then we try to set
	 * the option using curl_setopt() and return its
	 * success. If it doesn't exist, just return false.
	 *
	 * Also stores the variable in {@link $curlopt} so
	 * its value can be retrieved with {@link __get()}.
	 *
	 * @param string $opt The second half of the CURLOPT_* constant, not case sensitive
	 * @param mixed $value
	 * @return void
	 */
	public function __set($opt, $value)
	{
		$const = 'CURLOPT_'.strtoupper($opt);
		if ( defined($const) ) {
			if (curl_setopt($this->ch,
							constant($const),
							$value)) {
				$this->curlopt[$const] = $value;
			}
		}
	}

	/**
	 * Magic property getter.
	 *
	 * When options are set with {@link __set()}, they
	 * are also stored in {@link $curlopt} so that we
	 * can always find out what the options are now.
	 *
	 * The default cURL functions lack this ability.
	 *
	 * @param string $opt The second half of the CURLOPT_* constant, not case sensitive
	 * @return mixed The set value of CURLOPT_<var>$opt</var>, or NULL if it hasn't been set (ie: is still default).
	 */
	public function __get($opt)
	{
		return $this->curlopt['CURLOPT_'.strtoupper($opt)];
	}

	/**
	 * Magic property isset()
	 *
	 * Can tell if a CURLOPT_* value was set by using
	 * <code>
	 * isset($curl->*)
	 * </code>
	 *
	 * The default cURL functions lack this ability.
	 *
	 * @param string $opt The second half of the CURLOPT_* constant, not case sensitive
	 * @return bool
	 */
	public function __isset($opt)
	{
		return isset($this->curlopt['CURLOPT_'.strtoupper($opt)]);
	}

	/**
	 * Magic property unset()
	 *
	 * Unfortunately, there is no way, short of writing an
	 * extremely long, but mostly NULL-filled array, to
	 * implement a decent version of
	 * <code>
	 * unset($curl->option);
	 * </code>
	 *
	 * @todo Consider implementing an array of all the CURLOPT_*
	 *       constants and their default values.
	 * @param string $opt The second half of the CURLOPT_* constant, not case sensitive
	 * @return void
	 */
	public function __unset($opt)
	{
		// Since we really can't reset a CURLOPT_* to its
		// default value without knowing the default value,
		// just do nothing.
	}

	/**
	 * Grants access to {@link Curl::$ch $ch} to a {@link CurlParallel} object.
	 *
	 * @param CurlParallel $mh The CurlParallel object that needs {@link Curl::$ch $ch}.
	 */
	public function grant(CurlParallel $mh)
	{
		$mh->accept($this->ch);
		$this->multi = true;
	}

	/**
	 * Removes access to {@link Curl::$ch $ch} from a {@link CurlParallel} object.
	 *
	 * @param CurlParallel $mh The CurlParallel object that no longer needs {@link Curl::$ch $ch}.
	 */
	public function revoke(CurlParallel $mh)
	{
		$mh->release($this->ch);
		$this->multi = false;
	}
}

/**
 * Implements parallel-processing for cURL requests.
 *
 * The PHP cURL library allows two or more requests to run in
 * parallel (at the same time). If you have multiple requests
 * that may have high latency but can then be processed quickly
 * in series (one after the other), then running them at the
 * same time may save time, overall.
 *
 * You must create individual {@link Curl} objects first, add them to
 * the CurlParallel object, execute the CurlParallel object,
 * then get the data from the individual {@link Curl} objects. (Yes,
 * it's annoying, but it's limited by the PHP cURL library.)
 *
 * For example:
 *
 * <code>
 * $a = new Curl("http://www.yahoo.com/");
 * $b = new Curl("http://www.microsoft.com/");
 *
 * $m = new CurlParallel($a, $b);
 *
 * $m->exec(); // Now we play the waiting game.
 *
 * printf("Yahoo is %n characters.\n", strlen($a->fetch()));
 * printf("Microsoft is %n characters.\n", strlen($a->fetch()));
 * </code>
 *
 * You can add any number of {@link Curl} objects to the
 * CurlParallel object's constructor (including 0), or you
 * can add with the {@link add()} method:
 *
 * <code>
 * $m = new CurlParallel;
 *
 * $a = new Curl("http://www.yahoo.com/");
 * $b = new Curl("http://www.microsoft.com/");
 *
 * $m->add($a);
 * $m->add($b);
 *
 * $m->exec(); // Now we play the waiting game.
 *
 * printf("Yahoo is %n characters.\n", strlen($a->fetch()));
 * printf("Microsoft is %n characters.\n", strlen($a->fetch()));
 * </code>
 *
 * @package OOCurl
 * @author James Socol <me@jamessocol.com>
 * @version 0.3.0
 * @since 0.1.2
 */
class CurlParallel
{
	/**
	 * Store the cURL master resource.
	 * @var resource
	 */
	protected $mh;

	/**
	 * Store the resource handles that were
	 * added to the session.
	 * @var array
	 */
	protected $ch = array();

	/**
	 * Store the version number of this class.
	 */
	const VERSION = '0.3.0';

	/**
	 * Initialize the multisession handler.
	 *
	 * @uses add()
	 * @param Curl $curl,... {@link Curl} objects to add to the Parallelizer.
	 * @return CurlParallel
	 */
	public function __construct()
	{
		$this->mh = curl_multi_init();

		foreach ( func_get_args() as $ch ) {
			$this->add($ch);
		}

		return $this;
	}

	/**
	 * On destruction, frees resources.
	 */
	public function __destruct()
	{
		$this->close();
	}

	/**
	 * Close the current session and free resources.
	 */
	public function close()
	{
		foreach ($this->ch as $ch) {
			curl_multi_remove_handle($this->mh, $ch);
		}
		curl_multi_close($this->mh);
	}

	/**
	 * Add a {@link Curl} object to the Parallelizer.
	 *
	 * Will throw a catchable fatal error if passed a non-Curl object.
	 *
	 * @uses Curl::grant()
	 * @uses CurlParallel::accept()
	 * @param Curl $ch Curl object.
	 */
	public function add(Curl $ch)
	{
		// get the protected resource
		$ch->grant($this);
	}

	/**
	 * Remove a {@link Curl} object from the Parallelizer.
	 *
	 * @param Curl $ch Curl object.
	 * @uses Curl::revoke()
	 * @uses CurlParallel::release()
	 */
	public function remove(Curl $ch)
	{
		$ch->revoke($this);
	}

	/**
	 * Execute the parallel cURL requests.
	 */
	public function exec()
	{
		do {
			curl_multi_exec($this->mh, $running);
		} while ($running > 0);
	}

	/**
	 * Accept a resource handle from a {@link Curl} object and
	 * add it to the master.
	 *
	 * @param resource $ch A resource returned by curl_init().
	 */
	public function accept($ch)
	{
		$this->ch[] = $ch;
		curl_multi_add_handle($this->mh, $ch);
	}

	/**
	 * Accept a resource handle from a {@link Curl} object and
	 * remove it from the master.
	 *
	 * @param resource $ch A resource returned by curl_init().
	 */
	public function release($ch)
	{
		if ( false !== $key = array_search($this->ch, $ch) ) {
			unset($this->ch[$key]);
			curl_multi_remove_handle($this->mh, $ch);
		}
	}
}
