<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* phunction 2.12.7 (github.com/alixaxel/phunction/)
* Copyright (c) 2012 Alix Axel <alix.axel@gmail.com>
**/

class phunction
{
	public static $id = null;

	public function __construct()
	{
		ob_start();
		error_reporting(-1);
		date_default_timezone_set('GMT');

		if ((headers_sent() !== true) && (strcmp('cli', PHP_SAPI) !== 0))
		{
			header('Content-Type: text/html; charset=utf-8');

			if (strncasecmp('www.', self::Value($_SERVER, 'HTTP_HOST'), 4) === 0)
			{
				self::Redirect(str_ireplace('://www.', '://', self::URL()), null, null, 301);
			}

			else if ((strlen(session_id()) == 0) && (is_writable(session_save_path()) === true))
			{
				session_start();
			}
		}

		if (version_compare(PHP_VERSION, '5.3.0', '<') === true)
		{
			set_magic_quotes_runtime(false);
		}

		foreach (array('_GET', '_PUT', '_POST', '_COOKIE', '_REQUEST') as $value)
		{
			$GLOBALS[$value] = self::Value($GLOBALS, $value, null);

			if ((strcmp('_PUT', $value) === 0) && (strcasecmp('PUT', self::Value($_SERVER, 'REQUEST_METHOD')) === 0))
			{
				if ((($GLOBALS[$value] = file_get_contents('php://input')) !== false) && (preg_match('~/x-www-form-urlencoded$~', self::Value($_SERVER, 'CONTENT_TYPE')) > 0))
				{
					parse_str($GLOBALS[$value], $GLOBALS[$value]);
				}
			}

			$GLOBALS[$value] = self::Filter(self::Voodoo($GLOBALS[$value]), false);
		}

		if (defined('ROOT') !== true)
		{
			define('ROOT', realpath(dirname(self::Value($_SERVER, 'SCRIPT_FILENAME', __FILE__))));
		}

		array_map('ini_set', array('html_errors', 'display_errors', 'default_socket_timeout'), array(0, 1, 3));
		
		if (spl_autoload_functions() === false)
		{
			spl_autoload_extensions('.php');
			spl_autoload_register();			
		}		
	}

	public function __get($key)
	{
		if (class_exists($class = __CLASS__ . '_' . $key, false) !== true)
		{
			require(sprintf('%s'.DIRECTORY_SEPARATOR.'%s.php', dirname(__FILE__), str_replace('_', DIRECTORY_SEPARATOR, $class)));
		}

		return (substr_count($class, '_') > 1) ? new $class() : $this->$key = new $class();
	}

	public static function Cache($key, $value = null, $ttl = 60)
	{
		if (extension_loaded('apc') === true)
		{
			if ((isset($value) === true) && (apc_store($key, $value, intval($ttl)) !== true))
			{
				return $value;
			}

			return apc_fetch($key);
		}

		return (isset($value) === true) ? $value : false;
	}

	public static function CLI()
	{
		return (strcmp('cli', PHP_SAPI) === 0) ? true : false;
	}

	public static function Coalesce()
	{
		$arguments = self::Flatten(func_get_args());

		foreach ($arguments as $argument)
		{
			if (isset($argument) === true)
			{
				return $argument;
			}
		}

		return null;
	}

	public static function Date($format = 'U', $date = 'now', $zone = null)
	{
		if (is_object($result = date_create($date)) === true)
		{
			if (isset($zone) === true)
			{
				if (is_string($zone) !== true)
				{
					$zone = date_default_timezone_get();
				}

				@date_timezone_set($result, timezone_open($zone));
			}

			if (count($arguments = array_slice(func_get_args(), 3)) > 0)
			{
				foreach (array_filter($arguments, 'strtotime') as $argument)
				{
					date_modify($result, $argument);
				}
			}

			return date_format($result, str_replace(explode('|', 'DATE|TIME|YEAR|ZONE'), explode('|', 'Y-m-d|H:i:s|Y|T'), $format));
		}

		return false;
	}

	public static function DB($query = null)
	{
		static $db = array();
		static $result = array();

		if (isset($db[self::$id], $query) === true)
		{
			if (empty($result[self::$id][$hash = md5($query)]) === true)
			{
				$result[self::$id][$hash] = $db[self::$id]->prepare($query);
			}

			if (is_object($result[self::$id][$hash]) === true)
			{
				if ($result[self::$id][$hash]->execute(self::Flatten(array_slice(func_get_args(), 1))) === true)
				{
					if (preg_match('~^(?:INSERT|REPLACE)\b~i', $query) > 0)
					{
						$sequence = null;

						if (strcmp('pgsql', $db[self::$id]->getAttribute(PDO::ATTR_DRIVER_NAME)) === 0)
						{
							if (preg_match('~\bRETURNING\b~i', $query) > 0)
							{
								return $result[self::$id][$hash]->fetchColumn();
							}

							$sequence = sprintf('%s_id_seq', trim(ph()->Text->Regex($query, 'INTO\s*(["\w]+)', array(1, 0), 'i'), '"'));
						}

						return $db[self::$id]->lastInsertId($sequence);
					}

					else if (preg_match('~^(?:UPDATE|DELETE)\b~i', $query) > 0)
					{
						return $result[self::$id][$hash]->rowCount();
					}

					else if (preg_match('~^(?:SELECT|SHOW|EXPLAIN|DESC(?:RIBE)?|PRAGMA|CALL)\b~i', $query) > 0)
					{
						return $result[self::$id][$hash]->fetchAll();
					}

					return true;
				}
			}

			return false;
		}

		else if (preg_match('~^(?:mysql|pgsql):~i', $query) > 0)
		{
			try
			{
				$auth = array_slice(func_get_args(), 1, 2);
				$options = func_num_args() > 3 ? (array)func_get_arg(3) : array	( PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC );
				$db[self::$id] = new PDO(preg_replace('~^([^:]+):/{0,2}([^:/]+)(?::(\d+))?/(\w+)/?$~', '$1:host=$2;port=$3;dbname=$4', $query), array_shift($auth), array_shift($auth),$options);

				if (strcmp('mysql', $db[self::$id]->getAttribute(PDO::ATTR_DRIVER_NAME)) === 0)
				{
					self::DB('SET time_zone = ?;', date_default_timezone_get());
					self::DB('SET NAMES ? COLLATE ?;', 'utf8', 'utf8_unicode_ci');
				}

				$db[self::$id]->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
			}

			catch (PDOException $e)
			{
				return false;
			}
		}

		else if (preg_match('~^(?:sqlite|firebird):~', $query) > 0)
		{
			$db[self::$id] = new PDO(preg_replace('~^([^:]+):(?:/{2})?(.+)$~', '$1:$2', $query), null, null, array(PDO::ATTR_EMULATE_PREPARES => false));
		}

		return (isset($db[self::$id]) === true) ? $db[self::$id] : false;
	}

	public static function Dump()
	{
		foreach (func_get_args() as $argument)
		{
			if (is_resource($argument) === true)
			{
				$result = sprintf('%s (#%u)', get_resource_type($argument), $argument);
			}

			else if ((is_array($argument) === true) || (is_object($argument) === true))
			{
				$result = rtrim(print_r($argument, true));
			}

			else
			{
				$result = stripslashes(preg_replace("~^'|'$~", '', var_export($argument, true)));
			}

			if (strcmp('cli', PHP_SAPI) !== 0)
			{
				$result = '<pre style="background: #df0; margin: 5px; padding: 5px; text-align: left;">' . htmlspecialchars($result, ENT_QUOTES) . '</pre>';
			}

			echo $result . "\n";
		}
	}

	public static function Event($id, $callback = null)
	{
		static $events = array();

		if ($callback === true)
		{
			$result = array();

			foreach (self::Value($events, $id, array()) as $key => $value)
			{
				$result[$key] = call_user_func_array($value, array_slice(func_get_args(), 2));
			}

			return $result;
		}

		else if (is_callable($callback) === true)
		{
			$events[$id][] = $callback;
		}

		return count(self::Value($events, $id, null));
	}

	public static function Export($name, $data)
	{
		$result = null;

		if (is_scalar($data) === true)
		{
			$result .= sprintf("%s = %s;\n", $name, var_export($data, true));
		}

		else if (is_array($data) === true)
		{
			$result .= sprintf("%s = array();\n", $name);

			foreach ($data as $key => $value)
			{
				$result .= self::Export($name . '[' . var_export($key, true) . ']', $value) . "\n";
			}

			if (array_keys($data) === range(0, count($data)))
			{
				$result = preg_replace('~^' . sprintf(preg_quote($name . '[%s]', '~'), '\d+') . '~m', $name . '[]', $result);
			}
		}

		else if (is_object($data) === true)
		{
			$result .= sprintf("%s = %s;\n", $name, preg_replace('~\n\s*~', '', var_export($data, true)));
		}

		else
		{
			$result .= sprintf("%s = %s;\n", $name, 'null');
		}

		return rtrim($result, "\n");
	}

	public static function Filter($data, $control = true, $encoding = null)
	{
		if (is_array($data) === true)
		{
			$result = array();

			foreach ($data as $key => $value)
			{
				$result[self::Filter($key, $control, $encoding)] = self::Filter($value, $control, $encoding);
			}

			return $result;
		}

		else if (is_string($data) === true)
		{
			if (preg_match('~[^\x00-\x7F]~', $data) > 0)
			{
				if ((empty($encoding) === true) && (function_exists('mb_detect_encoding') === true))
				{
					$encoding = mb_detect_encoding($data, mb_detect_order(), true);
				}

				$data = @iconv((empty($encoding) === true) ? 'UTF-8' : $encoding, 'UTF-8//IGNORE', $data);
			}

			return ($control === true) ? preg_replace('~\p{C}+~u', '', $data) : preg_replace(array('~\R~u', '~[^\P{C}\t\n]+~u'), array("\n", ''), $data);
		}

		return $data;
	}

	public static function Flatten($data, $key = null, $default = false)
	{
		$result = array();

		if (is_array($data) === true)
		{
			if (is_null($key) === true)
			{
				foreach (new RecursiveIteratorIterator(new RecursiveArrayIterator($data)) as $value)
				{
					$result[] = $value;
				}
			}

			else if (isset($key) === true)
			{
				foreach ($data as $value)
				{
					$result[] = self::Value($value, $key, $default);
				}
			}
		}

		return $result;
	}

	public static function Highway($path, $throttle = null)
	{
		if ((is_dir($path = ph()->Disk->Path($path)) === true) && (count($segments = self::Segment()) > 0))
		{
			$class = null;

			while ((is_null($segment = array_shift($segments)) !== true) && (is_dir($path . $class . $segment . '/') === true))
			{
				$class .= $segment . '/';
			}

			if ((is_array($class = glob($path . $class . '{,_}' . $segment . '.php', GLOB_BRACE)) === true) && (count($class) > 0))
			{
				$class = preg_replace('~[.]php$~', '', current($class));
				$method = (count($segments) > 0) ? array_shift($segments) : self::Value($_SERVER, 'REQUEST_METHOD', 'GET');

				if (is_callable(array(self::Object($class), strtolower($method))) === true)
				{
					if ($throttle > 0)
					{
						usleep(intval(floatval($throttle) * 1000000));
					}

					exit(call_user_func_array(array(self::Object($class), strtolower($method)), $segments));
				}
			}

			throw new Exception('/' . implode('/', self::Segment()), 404);
		}

		return true;
	}

	public static function Object($object)
	{
		static $result = array();

		if (class_exists($object, true) === true)
		{
			if (isset($result[self::$id][$object]) !== true)
			{
				$result[self::$id][$object] = new $object();
			}

			return $result[self::$id][$object];
		}

		else if (is_file($object . '.php') === true)
		{
			if (class_exists(basename($object), false) !== true)
			{
				require($object . '.php');
			}

			return self::Object(basename($object));
		}

		return false;
	}

	public static function Redirect($url, $path = null, $query = null, $code = 303)
	{
		if ((headers_sent() !== true) && (strcmp('cli', PHP_SAPI) !== 0))
		{
			session_write_close();

			if ((preg_match('~^30[37]$~', $code) > 0) && (version_compare(ltrim(strstr(self::Value($_SERVER, 'SERVER_PROTOCOL'), '/'), '/'), '1.1', '<') === true))
			{
				$code = 302;
			}

			header(sprintf('Location: %s', ((isset($path) === true) || (isset($query) === true)) ? self::URL($url, $path, $query) : $url), true, $code);
		}

		exit();
	}

	public static function Request($input, $filters = null, $callbacks = null, $required = true)
	{
		if (array_key_exists($input, $_REQUEST) === true)
		{
			$result = array_map('trim', (array) $_REQUEST[$input]);

			if (($required === true) || (count($result) > 0))
			{
				foreach (array_filter(explode('|', $filters), 'is_callable') as $filter)
				{
					if (in_array(false, array_map($filter, $result)) === true)
					{
						return false;
					}
				}
			}

			foreach (array_filter(explode('|', $callbacks), 'is_callable') as $callback)
			{
				$result = array_map($callback, $result);
			}

			return (is_array($_REQUEST[$input]) === true) ? $result : $result[0];
		}

		return ($required === true) ? false : null;
	}

	/**
	 *	Core function to create Routes in application.
	 *	There are many ways to use it. This functionality is new, and is more or less an alternative to {@link phunction::Highway} method.
	 *
	 *	First of all there is a first argument ($route). It is the url path you want to assign action to.
	 *	Route can use placeholders :num: and :any: which will match either a number 0-9 or any string. 
	 *  It can also use direct regular expressions. Each part (including mentioned placeholders) in parentheses wil be captured and used in further processing.
	 *	How captured values will be used depends on other conditions.
	 *	
	 *	Second argument (optional, can be null) should point to an object - object name (also works with namespaces), or object instance will work just fine.
	 *	If there are any captured parameters, and third parameter ($callback) is null, first captured parameter will be used as object's method, and the rest will be passed to it as arguments.
	 *	Of course only string parameters are considered as method names.
	 *
	 *	Third parameter is an callback. Method name (if $object is not null), or function name which will be called. Any captured parameters will be used as arguments to method or function.
	 *
	 *	Special conditions are as follows:
	 *	Both $object and $callback are null, and there is one literal parameter - it is used as object, and rest (numeric) parameters are passed as arguments to its constructor.
	 *	There is more then one literal paramter - first one is used as object name, second as method, rest as above.
	 *	
	 *	In any case route matching is done using regular expresions (as mentioned), and route strings are matched to the begining of string (^). 
	 *	That means that there can be anything after your route, and when the first part matches, route is used. 
	 *	This also allows to create more advanced matching, like match send and post actions to POST method, and anything else to GET (see examples).
	 *
	 *	As a general rule routes should be created from the specific ones to more general. 
	 *
	 *	There are two special cases. If route is only slash (also known as home page) it works only with home page. And when route is null it will match just everything. 
	 *	Null route is usefull when creating 404 error page (and it should be the last Route if you want to handle it this way.
	 *
	 *	Examples
	 *	<code>
	 *	ph()->Route('post/(create|delete)','PostsController',null,'POST');	
	 * 	//will match post/create and post/delete (called via POST method), and Create, Delete will be called as methods of PostsController object
	 *
	 *	ph()->Route('post/(:any:)/(:any:)','PostsController');	
	 *	//will match everything else:
	 *	// post/edit/4	-> PostController->edit(4);
	 *	// post/list/recent -> PostController->list('recent');
	 *	</code>
	 *
	 */	
	public static function Route($route, $object = null, $callback = null, $method = null, $throttle = null)
	{
		static $result = null;
		
		if ($route === null) 
		{
			$route = '.*?';
		} 
		else if ($route === '/') 
		{
			$route .= '$';
		}		

		if ((strlen($method) * strcasecmp($method, self::Value($_SERVER, 'REQUEST_METHOD'))) == 0)
		{
			$matches = array();

			if ($result === null)
			{
				$result = preg_replace('~/+~', '/', substr(self::Value($_SERVER, 'PHP_SELF'), strlen(self::Value($_SERVER, 'SCRIPT_NAME'))) . '/');
			}

			if (preg_match('~^' . str_replace(array(':any:', ':num:'), array('[^/]+', '[0-9]+'), $route) . '~i', $result, $matches) > 0)
			{
				array_shift($matches);
				
				$params = array_diff($matches,array_filter($matches,'is_numeric'));

				if ($throttle > 0)
				{
					usleep(intval(floatval($throttle) * 1000000));
				}			
				
				$cp = count($params);				
				if (!$callback && $cp===1) 
				{
					$callback = array_shift($params);
				}
				else if (!$callback && !$object) 
				{
					if ($cp) 
						list($object,$callback) = array_splice($params,0,2);
					else 
						return false;
				}
				else if ($object && !$callback) 
				{
					if ($cp) 	
						list($callback) = array_splice($params,0,1);
					else					
						$object = !($r = new ReflectionClass($object));
				}			
				
				$matches = array_diff($matches,array($object,$callback));
				
				if (empty($object) !== true)
				{
					$callback = array(self::Object($object), $callback);
				}			
				
				if (isset($r)) 
				{
					$r->newInstanceArgs(array_slice($matches, 0));
				} 
				else 
				{
					if (!is_callable($callback))
					{
						return false;
					}				
					call_user_func_array($callback, array_slice($matches, 0));
				}
				exit;
			}
		}

		return false;
	}

	/**
	 *	Returns url segment.
	 *	If called without parameters returns an array of url segments.
	 * 	First parameter can be either an integer, or string.
	 *	For integer segment indexed by given number is returned (0 - first),
	 *	String is considered as a segment itself, and returned value is segment next to it.
	 * 	When segment is not found second parameter is returned.
	 *
	 *	Example:
	 *	<code>
	 *	<?php
	 *		//http://www.example.com/manage/users/list/C
	 *		ph()->Segment(1); 		//returns 'users'
	 * 		ph()->Segment('list');	//returns 'C'
	 *		ph()->Segment();		//Array ( [0] => manage [1] => users [2] => list [3] => C )
	 *	</code>
	 *
	 *	@param (int|string) $key Segment, or segment index. Defaults to null.
	 *	@param (mixed) $default	Default value to return when not found. Defaults to null.
	 *	@return string;
	 */
	public static function Segment($key = null, $default = false)
	{
		static $result = null;

		if (is_null($result) === true)
		{
			if (count($result = explode('/', substr(self::Value($_SERVER, 'PHP_SELF'), strlen(self::Value($_SERVER, 'SCRIPT_NAME'))))) > 0)
			{
				$result = array_values(array_filter($result, 'strlen'));
			}
		}

		return (isset($key) === true) ? self::Value($result, (is_int($key) === true) ? $key : (array_search($key, $result) + 1), $default) : $result;
	}

	public static function Sort($array, $natural = true, $reverse = false)
	{
		if (is_array($array) === true)
		{
			if (extension_loaded('intl') === true)
			{
				if (is_object($collator = collator_create('root')) === true)
				{
					if ($natural === true)
					{
						$collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);
					}

					$collator->asort($array);
				}
			}

			else if (function_exists('array_multisort') === true)
			{
				$data = array();

				foreach ($array as $key => $value)
				{
					if ($natural === true)
					{
						$value = preg_replace('~([0-9]+)~e', 'sprintf("%032d", "$1")', $value);
					}

					if (strpos($value = htmlentities($value, ENT_QUOTES, 'UTF-8'), '&') !== false)
					{
						$value = html_entity_decode(preg_replace('~&([a-z]{1,2})(acute|caron|cedil|circ|grave|lig|orn|ring|slash|tilde|uml);~i', '$1' . chr(255) . '$2', $value), ENT_QUOTES, 'UTF-8');
					}

					$data[$key] = strtolower($value);
				}

				array_multisort($data, $array);
			}

			return ($reverse === true) ? array_reverse($array, true) : $array;
		}

		return false;
	}

	public static function Text($single, $plural = null, $number = null, $domain = null, $path = null)
	{
		static $result = null;

		if (extension_loaded('gettext') === true)
		{
			foreach (array('LANG', 'LANGUAGE', 'LC_ALL', 'LC_MESSAGES') as $value)
			{
				if (defined($value) === true)
				{
					setlocale(constant($value), 'en_US');
				}

				putenv(sprintf('%s=%s', $value, 'en_US'));
			}

			if (isset($domain) === true)
			{
				$result = $domain;
			}

			if ((isset($path) === true) && (is_dir($path) === true))
			{
				$path = ph()->Disk->Path($path);

				foreach (glob($path . '*.mo') as $value)
				{
					bindtextdomain(basename($value, '.mo'), $path);
					bind_textdomain_codeset(basename($value, '.mo'), 'UTF-8');
				}
			}

			if (isset($result, $single) === true)
			{
				return (isset($plural, $number) === true) ? dngettext($result, $single, $plural, $number) : dgettext($result, $single);
			}
		}

		return ((isset($plural, $number) === true) && (abs($number) !== 1)) ? $plural : $single;
	}

	public static function Throttle($ttl = 60, $exit = 60, $count = 1, $proxy = false, $namespace = false)
	{
		if (extension_loaded('apc') === true)
		{
			$ip = ph()->HTTP->IP(null, $proxy);
			$key = array(__METHOD__, $ip, $proxy, $namespace);

			if (is_bool(apc_add(vsprintf('%s:%s:%b:%s', $key), 0, $ttl)) === true)
			{
				$result = apc_inc(vsprintf('%s:%s:%b:%s', $key), intval($count));

				if ($result < $exit)
				{
					return ($result / $ttl);
				}

				return true;
			}
		}

		return false;
	}

	/**
	 *	Function can be used to get or create valid url.
	 *	There are few use it can be used (all parameters are optional)
	 *	
	 *	First parameter can be omitted. So if path will be passed as first parameter, and query as second it will work as expected.
	 *
	 *	@param string $url	When null current url will be used as base, otherwise it should be any url which will be recreated as valid.
	 *	@param string $path	Path (or segments) to attach to base url. If first parameter is given with path, its path part will be changed according to this parameter.
	 *	@param array|string $query Query part. It will be appended to the one included in url (if any).
	 */
	public static function URL($url = null, $path = null, $query = null)
	{
		if ($url !== null && (is_array($url = @parse_url($url)) === true) && (isset($url['scheme'], $url['host']) === true))
		{
			$result = strtolower($url['scheme']) . '://';

			if ((isset($url['user']) === true) || (isset($url['pass']) === true))
			{
				$result .= ltrim(rtrim(self::Value($url, 'user') . ':' . self::Value($url, 'pass'), ':') . '@', '@');
			}

			$result .= strtolower($url['host']) . '/';

			if ((isset($url['port']) === true) && (strcmp($url['port'], getservbyname($url['scheme'], 'tcp')) !== 0))
			{
				$result = rtrim($result, '/') . ':' . intval($url['port']) . '/';
			}

			if (($path !== false) && ((isset($path) === true) || (isset($url['path']) === true)))
			{
				if (is_scalar($path) === true)
				{
					if (($query !== false) && (preg_match('~[?&]~', $path) > 0))
					{
						$url['query'] = ltrim(rtrim(self::Value($url, 'query'), '&') . '&' . preg_replace('~^.*?[?&]([^#]*).*$~', '$1', $path), '&');
					}

					$url['path'] = '/' . ltrim(preg_replace('~[?&#].*$~', '', $path), '/');
				}

				while (preg_match('~/[.][.]?(?:/|$)~', $url['path']) > 0)
				{
					$url['path'] = preg_replace(array('~/+~', '~/[.](?:/|$)~', '~(?:^|/[^/]+)/[.]{2}(?:/|$)~'), '/', $url['path']);
				}

				$result .= preg_replace('~/+~', '/', ltrim($url['path'], '/'));
			}

			if (($query !== false) && ((isset($query) === true) || (isset($url['query']) === true)))
			{
				parse_str(self::Value($url, 'query'), $url['query']);

				if (is_array($query) === true)
				{
					$url['query'] = array_merge($url['query'], $query);
				}

				if ((count($url['query'] = self::Voodoo(array_filter($url['query'], 'count'))) > 0) && (ksort($url['query']) === true))
				{
					$result .= rtrim('?' . http_build_query($url['query'], '', '&'), '?');
				}
			}

			return preg_replace('~(%[0-9a-f]{2})~e', 'strtoupper("$1")', $result);
		}
		else if (strlen($scheme = preg_replace('~^www$~i', 'http', getservbyport(self::Value($_SERVER, 'SERVER_PORT', 80), 'tcp'))) > 0)
		{
			if (is_array($url))
			{
				list($query, $path, $url) = array($path, $url['path'], null);
			}
			return self::URL($scheme . '://' . self::Value($_SERVER, 'SERVER_NAME') . self::Value($_SERVER, 'REQUEST_URI'), $path, $query);
		}

		return false;
	}

	public static function Value($data, $key = null, $default = false)
	{
		if (isset($key) === true)
		{
			if (is_array($key) !== true)
			{
				$key = explode('.', $key);
			}

			foreach ((array) $key as $value)
			{
				$data = (is_object($data) === true) ? get_object_vars($data) : $data;

				if ((is_array($data) !== true) || (array_key_exists($value, $data) !== true))
				{
					return $default;
				}

				$data = $data[$value];
			}
		}

		return $data;
	}

	public static function View($path, $data = null, $minify = false, $return = false)
	{
		if (is_file(($path = str_replace('::', DIRECTORY_SEPARATOR, $path)) . '.php') === true)
		{
		
			if ($data === null) 
			{
				$data = ph()->View->Data();
			}
			
			extract((array) $data);

			if ((($minify === true) || ($return === true)) && (ob_start() === true))
			{
				require($path . '.php');

				if ((($result = ob_get_clean()) !== false) && (ob_start() === true))
				{
					echo ($minify === true) ? preg_replace('~^[[:blank:]]+~m', '', $result) : $result;
				}

				return ($return === true) ? ob_get_clean() : ob_end_flush();
			}

			require($path . '.php');
		}
	}

	public static function Voodoo($data)
	{
		if ((version_compare(PHP_VERSION, '5.4.0', '<') === true) && (get_magic_quotes_gpc() === 1))
		{
			if (is_array($data) === true)
			{
				$result = array();

				foreach ($data as $key => $value)
				{
					$result[self::Voodoo($key)] = self::Voodoo($value);
				}

				return $result;
			}

			return (is_string($data) === true) ? stripslashes($data) : $data;
		}

		return $data;
	}
}

function ph($ph = null)
{
	static $result = null;

	if (is_null($result) === true)
	{
		$result = new phunction();
	}

	if (is_object($result) === true)
	{
		phunction::$id = strval($ph);
	}

	return $result;
}

?>