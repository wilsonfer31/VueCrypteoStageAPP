<?php

/**
 * Abstract class to define a cache method 
 *
 * * */
interface ICacheMethod {

	public function read($key);

	public function write($key, $value);

	public function delete($key);

	public function clear($onlyExpired = true);
}

/**
 *
 * 	Cache implementation
 * 	
 * 	This class need two defines to work:
 * 		DEFAULT_PATH_CACHE Path to the cache
 * 		USE_CACHE          Enable the cache 
 */
class db_Cache {

	private $_method = null;
	static private $enabled = true;
	static private $timeout = 0;

	static public function Enable($bool) {
		self::$enabled = $bool;
	}

	static public function SetTimeout($timeout) {
		self::$timeout = $timeout;
	}

	static public function GetTimeout() {
		return self::$timeout;
	}

	/**
	 * Private constructor
	 *
	 * @param $method	Instance of a cache method object
	 */
	private function __construct(ICacheMethod $method) {
		$this->_method = $method;
	}

	/**
	 * Returns a singleton instance
	 *
	 * @param string $name Name of the method  * 
	 * @return object
	 */
	static public function &getInstance($method = 'CacheFile') {
		static $instance = null;

		if (is_null($instance)) {
			$method = 'db_'.$method;
			$method = new $method();
			if (time() % 1000 == 0)
				$method->clear();

			$instance = new self($method);
		}

		return $instance;
	}

	/**
	 * Read a value from the cache
	 *
	 * @param string $key Identifier for the data
	 * @return mixed The cached data, or false if the data was not found
	 * @access public
	 */
	public function read($key) {
		if (!self::$enabled)
			return false;

		if (empty($key))
			return false;

		if (!isset($this->_method))
			return false;

		$r = $this->_method->read($key);
		/* $log = new Log('cache');
		  if ($r!==false)
		  $log->addNotice('Cache hit: '.$key);
		  else
		  $log->addNotice('Cache miss: '.$key); */
		return $r;
	}

	/**
	 * Write a value in the cache
	 *
	 * @param string $key Identifier for the data
	 * @param mixed $value Data to be cached - anything except a resource
	 * @return boolean True on success, false on failure
	 */
	public function write($key, $value) {
		if (!self::$enabled)
			return false;

		if (empty($key))
			return false;

		if (!isset($this->_method))
			return false;

		$r = $this->_method->write($key, $value);
		/* 	if ($r!==false) {
		  $log = new Log('cache');
		  $log->addNotice('Cache write: '.$key);
		  } */
		return $r;
	}

	/**
	 * Delete a value from the cache
	 *
	 * @param string $key Identifier for the data
	 * @return boolean True if the value was deleted
	 * @access public
	 */
	public function delete($key) {
		if (!self::$enabled)
			return false;

		if (empty($key))
			return false;

		if (!isset($this->_method))
			return false;

		return $this->_method->delete($key);
	}

}

/**
 * 	
 * 	Implement file cache method  
 *  
 */
class db_CacheFile implements ICacheMethod {

	protected $path = '';

	public function setPath($path) {
		$this->path = $path;
	}

	public function __construct($path = null) {
		if ($path != null)
			$this->path = $path;

		if ($path == null && defined('DEFAULT_PATH_CACHE'))
			$this->path = DEFAULT_PATH_CACHE;

		if (empty($this->path)) {
			if (is_dir('cache'))
				$this->path = 'cache';
		}

		if (empty($this->path)) {
			trigger_error('Cache Folder is not defined', E_USER_WARNING);
			return;
		}
		if (!file_exists($this->path)) {
			if (!@mkdir($this->path)) {
				trigger_error('Cache Folder (' . $this->path . ') doesn\'t exist', E_USER_WARNING);
				return;
			}
			else
				chmod($this->path, 0775);
		}

		//if (!is_writable($this->path))
		//trigger_error('Cache Folder ('.$this->path.') is not writable', E_USER_WARNING);
	}

	protected function getFilename($key) {
		$key = preg_replace("/[^\w\.-]+/", "_", $key);
		return $this->path . '/' . $key;
	}

	public function write($key, $value) {
		$filename = $this->getFilename($key);
		$exp = db_Cache::GetTimeout() ? time() + db_Cache::GetTimeout() : 0;
		$content = $exp . "\n" . serialize($value) . "\n";
		return file_put_contents($filename, $content) !== false;
	}

	public function read($key) {
		$filename = $this->getFilename($key);
		if (!file_exists($filename))
			return false;

		$fp = fopen($filename, 'r');
		if (!$fp) {
			trigger_error('Can\'t open cache file ' . $filename, E_USER_NOTICE);
			return false;
		}

		$timeout = fgets($fp, 12);

		if (intval($timeout) > 0 && intval($timeout) < time()) {
			fclose($fp);
			unlink($filename);
			return false;
		}

		$data = '';
		while (!feof($fp))
			$data .= fgets($fp, 4096);

		fclose($fp);

		return unserialize(trim($data));
	}

	function delete($key) {
		$filename = $this->getFilename($key);
		if ($filename === false)
			return false;

		return unlink($filename);
	}

	function clear($onlyExpired = true) {
		// TODO
	}

}

?>
