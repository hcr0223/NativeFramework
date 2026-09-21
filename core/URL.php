<?php

namespace Core;

class URL {
	protected static ?array $config = null;

	protected static function loadConfig(): array {
		if (self::$config === null) {
			$configPath = dirname(__DIR__, 1).'/config/app.php';
			self::$config = file_exists($configPath) ? require $configPath : [];
		}
		return self::$config;
	}

	public static function to(string $path): string {
		$config = self::loadConfig();
		$baseUrl = rtrim($config['base_url'] ?? '', '/');
		$cleanUrls = $config['clear_urls'] ?? false;

		$path = ltrim($path, '/');

		if ($cleanUrls) {
			return "{$baseUrl}/{$path}";
		}

		return "{$baseUrl}/index.php/{$path}";
	}
}