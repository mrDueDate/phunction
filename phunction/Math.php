<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* Copyright (c) Alix Axel <alix.axel@gmail.com>
**/

class phunction_Math extends phunction
{
	public function __construct()
	{
	}

	public function __get($key)
	{
		return $this->$key = parent::__get(sprintf('%s_%s', ltrim(strrchr(__CLASS__, '_'), '_'), $key));
	}

	public static function Base($input, $output, $number = 1, $charset = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
	{
		if (strlen($charset) >= 2)
		{
			$input = max(2, min(intval($input), strlen($charset)));
			$output = max(2, min(intval($output), strlen($charset)));
			$number = ltrim(preg_replace('~[^' . preg_quote(substr($charset, 0, max($input, $output)), '~') . ']+~', '', $number), $charset[0]);

			if (strlen($number) > 0)
			{
				if (extension_loaded('gmp') === true)
				{
					$string = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

					if ((max($input, $output) <= 62) && (version_compare(PHP_VERSION, '5.3.2', '>=') === true))
					{
						return strtr(gmp_strval(gmp_init(strtr($number, $charset, $string), $input), $output), $string, $charset);
					}
				}

				if ($input != 10)
				{
					$result = 0;

					foreach (str_split(strrev($number)) as $key => $value)
					{
						$result += pow($input, $key) * intval(strpos($charset, $value));
					}

					$number = $result;
				}

				if ($output != 10)
				{
					$result = $charset[$number % $output];

					while (($number = intval($number / $output)) > 0)
					{
						$result = $charset[$number % $output] . $result;
					}

					$number = $result;
				}

				return $number;
			}

			return $charset[0];
		}

		return false;
	}

	public static function BC($string, $precision = 32)
	{
		if (extension_loaded('bcmath') === true)
		{
			if (is_array($string) === true)
			{
				if ((count($string = array_slice($string, 1)) == 3) && (bcscale($precision) === true))
				{
					$callback = array('^' => 'pow', '*' => 'mul', '/' => 'div', '%' => 'mod', '+' => 'add', '-' => 'sub');

					if (array_key_exists($operator = current(array_splice($string, 1, 1)), $callback) === true)
					{
						$x = 1;
						$result = @call_user_func_array('bc' . $callback[$operator], $string);

						if ((strcmp('^', $operator) === 0) && (($i = fmod(array_pop($string), 1)) > 0))
						{
							$y = self::BC(sprintf('((%1$s * %2$s ^ (1 - %3$s)) / %3$s) - (%2$s / %3$s) + %2$s', $string = array_shift($string), $x, $i = pow($i, -1)));

							do
							{
								$x = $y;
								$y = self::BC(sprintf('((%1$s * %2$s ^ (1 - %3$s)) / %3$s) - (%2$s / %3$s) + %2$s', $string, $x, $i));
							}

							while (self::BC(sprintf('%s > %s', $x, $y)));
						}

						if (strpos($result = bcmul($x, $result), '.') !== false)
						{
							$result = rtrim(rtrim($result, '0'), '.');

							if (preg_match(sprintf('~[.][9]{%u}$~', $precision), $result) > 0)
							{
								$result = bcadd($result, (strncmp('-', $result, 1) === 0) ? -1 : 1, 0);
							}

							else if (preg_match(sprintf('~[.][0]{%u}[1]$~', $precision - 1), $result) > 0)
							{
								$result = bcmul($result, 1, 0);
							}
						}

						return $result;
					}

					return intval(version_compare(call_user_func_array('bccomp', $string), 0, $operator));
				}

				$string = array_shift($string);
			}

			$string = str_replace(' ', '', str_ireplace('e', ' * 10 ^ ', $string));

			while (preg_match('~[(]([^()]++)[)]~', $string) > 0)
			{
				$string = preg_replace_callback('~[(]([^()]++)[)]~', __METHOD__, $string);
			}

			foreach (array('\^', '[\*/%]', '[\+-]', '[<>]=?|={1,2}') as $operator)
			{
				while (preg_match(sprintf('~(?<![0-9])(%1$s)(%2$s)(%1$s)~', '[+-]?(?:[0-9]++(?:[.][0-9]*+)?|[.][0-9]++)', $operator), $string) > 0)
				{
					$string = preg_replace_callback(sprintf('~(?<![0-9])(%1$s)(%2$s)(%1$s)~', '[+-]?(?:[0-9]++(?:[.][0-9]*+)?|[.][0-9]++)', $operator), __METHOD__, $string, 1);
				}
			}
		}

		return (preg_match('~^[+-]?[0-9]++(?:[.][0-9]++)?$~', $string) > 0) ? $string : false;
	}

	public static function Benchmark($callbacks, $iterations = 100, $relative = false)
	{
		set_time_limit(0);

		if (count($callbacks = array_filter((array) $callbacks, 'is_callable')) > 0)
		{
			$result = array_fill_keys($callbacks, 0);
			$arguments = array_slice(func_get_args(), 3);

			for ($i = 0; $i < $iterations; ++$i)
			{
				foreach ($result as $key => $value)
				{
					$value = microtime(true); call_user_func_array($key, $arguments); $result[$key] += microtime(true) - $value;
				}
			}

			asort($result, SORT_NUMERIC);

			foreach (array_reverse($result) as $key => $value)
			{
				if ($relative === true)
				{
					$value /= reset($result);
				}

				$result[$key] = number_format($value, 8, '.', '');
			}

			return $result;
		}

		return false;
	}

	public static function Between($number, $minimum = null, $maximum = null)
	{
		$number = floatval($number);

		if ((isset($minimum) === true) && ($number < $minimum))
		{
			$number = floatval($minimum);
		}

		if ((isset($maximum) === true) && ($number > $maximum))
		{
			$number = floatval($maximum);
		}

		return $number;
	}

	public static function Chance($chance, $universe = 100)
	{
		return ($chance >= mt_rand(1, $universe)) ? true : false;
	}

	public static function Checksum($string, $encode = false)
	{
		if ($encode === true)
		{
			$result = 0;
			$string = str_split($string);

			foreach ($string as $value)
			{
				$result = ($result + ord($value) - 48) * 10 % 97;
			}

			return implode('', $string) . sprintf('%02u', (98 - $result * 10 % 97) % 97);
		}

		else if (strcmp($string, self::Checksum(substr($string, 0, -2), true)) === 0)
		{
			return substr($string, 0, -2);
		}

		return false;
	}

	public static function Cloud($data, $minimum = null, $maximum = null)
	{
		$result = array();

		if ((is_array($data) === true) && (count($data) > 0))
		{
			$data = array_map('abs', $data);
			$regression = array(min($data) => $minimum, max($data) => $maximum);

			foreach ($data as $key => $value)
			{
				$result[$key] = self::Regression($regression, $value);
			}
		}

		return $result;
	}

	public static function Deviation()
	{
		if (function_exists('stats_standard_deviation') !== true)
		{
			$result = self::Mean(func_get_args());
			$arguments = parent::Flatten(func_get_args());

			foreach ($arguments as $key => $value)
			{
				$arguments[$key] = pow($value - $result, 2);
			}

			return sqrt(self::Mean($arguments));
		}

		return stats_standard_deviation(parent::Flatten(func_get_args()));
	}

	public static function Divisors($number)
	{
		$i = 0;
		$result = array();

		if (fmod($number, 1) == 0)
		{
			while ($i < $number)
			{
				if ($number % ++$i == 0)
				{
					$result[] = $i;
				}
			}
		}

		return $result;
	}

	public static function Enum($id)
	{
		static $enum = array();

		if (func_num_args() > 1)
		{
			$result = 0;

			if (empty($enum[$id]) === true)
			{
				$enum[$id] = array();
			}

			foreach (array_unique(array_slice(func_get_args(), 1)) as $argument)
			{
				if (empty($enum[$id][$argument]) === true)
				{
					$enum[$id][$argument] = pow(2, count($enum[$id]));
				}

				$result += $enum[$id][$argument];
			}

			return $result;
		}

		return false;
	}

	public static function Factor($number)
	{
		$i = 2;
		$result = array();

		if (fmod($number, 1) == 0)
		{
			while ($i <= sqrt($number))
			{
				while ($number % $i == 0)
				{
					$number /= $i;

					if (empty($result[$i]) === true)
					{
						$result[$i] = 0;
					}

					++$result[$i];
				}

				$i = (function_exists('gmp_nextprime') === true) ? gmp_strval(gmp_nextprime($i)) : ++$i;
			}

			if ($number > 1)
			{
				$result[$number] = 1;
			}
		}

		return $result;
	}

	public static function Factorial($number)
	{
		$number = max(1, abs(intval($number)));

		if (function_exists('gmp_fact') === true)
		{
			return gmp_strval(gmp_fact($number));
		}

		return (function_exists('bcmul') === true) ? array_reduce(range(1, $number), 'bcmul', 1) : array_product(range(1, $number));
	}

	public static function GTIN($string, $encode = false)
	{
		if ($encode === true)
		{
			$result = 0;
			$string = str_split(strrev($string), 1);

			foreach ($string as $key => $value)
			{
				$result += ($key % 2 == 0) ? $value * 3 : $value;
			}

			return implode('', array_reverse($string)) . abs(10 - ($result % 10));
		}

		else if (strcmp($string, self::GTIN(substr($string, 0, -1), true)) === 0)
		{
			return substr($string, 0, -1);
		}

		return false;
	}

	public static function ifMB($id, $reference, $amount = 0.00, $entity = 10559)
	{
		$stack = 0;
		$weights = array(51, 73, 17, 89, 38, 62, 45, 53, 15, 50, 5, 49, 34, 81, 76, 27, 90, 9, 30, 3);
		$argument = sprintf('%05u%03u%04u%08u', $entity, $id, $reference % 10000, round($amount * 100));

		foreach (str_split($argument) as $key => $value)
		{
			$stack += $value * $weights[$key];
		}

		return array
		(
			'entity' => sprintf('%05u', $entity),
			'reference' => sprintf('%03u%04u%02u', $id, $reference % 10000, 98 - ($stack % 97)),
			'amount' => number_format($amount, 2, '.', ''),
		);
	}

	public static function Luhn($string, $encode = false)
	{
		if ($encode > 0)
		{
			$encode += 1;

			while (--$encode > 0)
			{
				$result = 0;
				$string = str_split(strrev($string), 1);

				foreach ($string as $key => $value)
				{
					$result += ($key % 2 == 0) ? array_sum(str_split($value * 2, 1)) : $value;
				}

				if (($result %= 10) != 0)
				{
					$result -= 10;
				}

				$string = implode('', array_reverse($string)) . abs($result);
			}

			return $string;
		}

		else if (strcmp($string, self::Luhn(substr($string, 0, max(1, abs($encode)) * -1), max(1, abs($encode)))) === 0)
		{
			return substr($string, 0, max(1, abs($encode)) * -1);
		}

		return false;
	}

	public static function Matrix($size, $length = 2)
	{
		if (count($size = array_filter(explode('*', $size), 'is_numeric')) == 2)
		{
			$size[0] = min(26, $size[0]);

			foreach (($result = range(1, array_product($size))) as $key => $value)
			{
				$result[$key] = str_pad(mt_rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
			}

			return array_combine(array_slice(range('A', 'Z'), 0, $size[0]), array_chunk($result, $size[1]));
		}

		return false;
	}

	public static function Mean()
	{
		return ph()->Math->Mean->Arithmetic(func_get_args());
	}

	public static function Median()
	{
		if ((($result = count($arguments = parent::Flatten(func_get_args()))) > 2) && (sort($arguments, SORT_NUMERIC) === true))
		{
			$arguments = array_slice($arguments, floor($result / 2) - 1, 2);
		}

		return (($result % 2) == 0) ? self::Mean($arguments) : array_pop($arguments);
	}

	public static function Mode()
	{
		if (count($arguments = array_count_values(parent::Flatten(func_get_args()))) > 0)
		{
			return array_keys($arguments, max($arguments));
		}

		return array();
	}

	public static function Pagination($data, $limit = null, $current = null, $adjacents = null)
	{
		$result = array();

		if (isset($data, $limit) === true)
		{
			$result = range(1, ceil($data / $limit));

			if (isset($current, $adjacents) === true)
			{
				if (($adjacents = floor($adjacents / 2) * 2 + 1) >= 1)
				{
					$result = array_slice($result, max(0, min(count($result) - $adjacents, intval($current) - ceil($adjacents / 2))), $adjacents);
				}
			}
		}

		return $result;
	}

	public static function Prime($number)
	{
		if (function_exists('gmp_prob_prime') === true)
		{
			return (gmp_prob_prime(abs($number)) > 0) ? true : false;
		}

		return (preg_match('~^1?$|^(11+?)\1++$~', str_repeat('1', abs($number))) + preg_last_error() == 0) ? true : false;
	}

	public static function Probability($data, $number = 1)
	{
		$result = array();

		if (is_array($data) === true)
		{
			$data = array_map('abs', $data);
			$number = min(max(1, abs($number)), count($data));

			while (--$number >= 0)
			{
				$chance = 0;
				$probability = mt_rand(1, array_sum($data));

				foreach ($data as $key => $value)
				{
					$chance += $value;

					if ($chance >= $probability)
					{
						$result[] = $key; unset($data[$key]); break;
					}
				}
			}
		}

		return $result;
	}

	public static function Range()
	{
		$arguments = parent::Flatten(func_get_args());

		if ((count($arguments) > 1) && (sort($arguments, SORT_NUMERIC) === true))
		{
			return abs(array_pop($arguments) - array_shift($arguments));
		}

		return 0;
	}

	public static function Rating($negative = 0, $positive = 0, $decay = 0, $power = 95)
	{
		$power = max(0, min(100, floatval($power))) / 100;
		$score = (function_exists('stats_cdf_normal') === true) ? stats_cdf_normal($power, 0, 1, 2) : ($power * 0.03115085 - 1.55754263);

		if (($n = $negative + $positive) > 0)
		{
			$p = $positive / $n;

			if (($decay = pow($decay, 0.5)) > 0)
			{
				return (($p + $score * $score / (2 * $n) - $score * sqrt(($p * (1 - $p) + $score * $score / (4 * $n)) / $n)) / (1 + $score * $score / $n)) / $decay;
			}

			return ($p + $score * $score / (2 * $n) - $score * sqrt(($p * (1 - $p) + $score * $score / (4 * $n)) / $n)) / (1 + $score * $score / $n);
		}

		return 0;
	}

	public static function Regression($data, $number = null)
	{
		$n = count($data);
		$sum = array_fill(0, 4, 0);

		if (is_array($data) === true)
		{
			foreach ($data as $key => $value)
			{
				$sum[0] += $key;
				$sum[1] += $value;
				$sum[2] += $key * $key;
				$sum[3] += $key * $value;
			}

			if (($result = $n * $sum[2] - pow($sum[0], 2)) != 0)
			{
				$result = ($n * $sum[3] - $sum[0] * $sum[1]) / $result;
			}

			$result = array('m' => $result, 'b' => ($sum[1] - $result * $sum[0]) / $n);

			if (isset($number) === true)
			{
				return floatval($number) * $result['m'] + $result['b'];
			}

			return $result;
		}

		return false;
	}

	public static function Relative()
	{
		$result = 0;
		$arguments = self::Flatten(func_get_args());

		foreach ($arguments as $argument)
		{
			if (substr($argument, -1) == '%')
			{
				$argument = $result * floatval(rtrim($argument, '%') / 100);
			}

			$result += floatval($argument);
		}

		return floatval($result);
	}

	public static function Round($number, $precision = 0)
	{
		return number_format($number, intval($precision), '.', '');
	}

	public static function Verhoeff($string, $encode = false)
	{
		if ($encode > 0)
		{
			$encode += 1;
			$lookup = array
			(
				'd' => '0123456789123406789523401789563401289567401239567859876043216598710432765982104387659321049876543210',
				'p' => '01234567891576283094580379614289160435279453126870428657390127938064157046913258',
				'i' => '0432156789',
			);

			foreach ($lookup as $key => $value)
			{
				$lookup[$key] = array_chunk(str_split($value, 1), 10);
			}

			while (--$encode > 0)
			{
				$result = 0;
				$string = str_split(strrev($string), 1);

				foreach ($string as $key => $value)
				{
					$result = $lookup['d'][$result][$lookup['p'][($key + 1) % 8][$value]];
				}

				$string = strrev(implode('', $string)) . $lookup['i'][0][$result];
			}

			return $string;
		}

		else if (strcmp($string, self::Verhoeff(substr($string, 0, max(1, abs($encode)) * -1), max(1, abs($encode)))) === 0)
		{
			return substr($string, 0, max(1, abs($encode)) * -1);
		}

		return false;
	}
}

?>