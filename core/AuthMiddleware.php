<?php 

namespace Core;

class AuthMiddleware {

	public static function handle(): void {
		$auth = new Auth();

		if (!$auth->check()) {
			$currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
			$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
			if ($scriptDir !== '/' && strpos($currentUrl, $scriptDir) === 0) {
				$currentUrl substr($currentUrl, strlen($scriptDir));
			}

			Session::putIntendedUrl($currentUrl);

			header("Location: ".route_to('/'));
			exit;
		}
	}
}