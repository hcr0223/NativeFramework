<?php 

namespace Core;

class Env {
	protected static bool $loaded = false;

	public static function load(string $path): void {
		if (!file_exists($path)) {
			return;
		}

		$lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		foreach ($lines as $line) {
			$line = trim($line);

			if (empty($line) || str_starts_with($line, "#")) {
				continue;
			}

			if (str_contains($line, "=")) {
				[$key, $value] = explode('=', $line, 2);
				$key = trim($key);
				$value = trim($value);

				if (
					(str_starts_with($value, '"') && str_ends_with($value, '"')) ||
					(str_starts_with($value, "'") && str_ends_with($value, "'"))
				) {	
					$value = substr($value, 1, -1);
				}

				putenv("{$key}={$value}");
				$_ENV[$key] = $value;
				$_SERVER[$key] = $value;
			}
		}
		self::$loaded = true;
	}

	public static function get(string $key, mixed $default = null): mixed {
		$value = getenv($key);

		if ($value === false) {
			return $default;
		}

		return match (strtolower($value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)'   => null,
            default            => $value,
        };
	} 
}
