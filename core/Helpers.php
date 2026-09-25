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

if (!function_exists('extends_layout')) {
    function extends_layout(string $layout): void {
        \Core\View::extends($layout);
    }
}

if (!function_exists('section')) {
    function section(string $name): void {
        \Core\View::section($name);
    }
}

if (!function_exists('endsection')) {
    function endsection(): void {
        \Core\View::endsection();
    }
}

if (!function_exists('yield_content')) {
    function yield_content(string $name, string $default = ''): string {
        return \Core\View::yield($name, $default);
    }
}