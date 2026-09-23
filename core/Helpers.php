<?php

function asset(string $path): string {
	$scriptName = $_SERVER['SCRIPT_NAME'];
	$basePath = str_replace('index.php', '', $scriptName);

	return rtrim($basePath, '/').'/'.ltrim($path, '/');
}

function route_to(string $path): string {
	return \Core\URL::to($path);
}

function response_json(mixed $data, int $statusCode): void {
	http_response_code($statusCode);
	header("Content-type: application/json; charset=utf-8");
	echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

function env(string $key, mixed $default = null): mixed {
	return \Core\Env::get($key, $default);
}