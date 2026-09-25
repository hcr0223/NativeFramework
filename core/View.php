<?php

namespace Core;

class View {
	protected static ?string $layout = null;
	protected static array $sections = [];
	protected static ?string $currentSection = null;

	public static function render(string $view, array $data = []): string {
		self::$layout = null;
		self::$sections = [];
		self::$currentSection = null;

		$viewFile = __DIR__."/../app/Views/{$view}.php";

		if (!file_exists($viewFile)) {
			http_response_code(500);
			die("<h1>500 Internal Server Error</h1><p>View file '{$view}.php' not found!</p>");
		}

		extract($data);

		ob_start();
		include $viewFile;
		$content = ob_get_clean();

		if(self::$layout) {
			$layoutFile = __DIR__."/../app/Views/layout/".self::$layout.".php";
			if (!file_exists($layoutFile)) {
				http_response_code(500);
				die("<h1>500 Internal Server Error</h1><p>Layout file '" . self::$layout . ".php' not found!</p>");
			}

			ob_start();
			include $layoutFile;
			$content = ob_get_clean();
		}

		return self::compileDirectives($content);
	}

	protected static function compileDirectives(string $content): string {
        /* Escaped echo: {{ $expr }} -> <?= htmlspecialchars($expr ?? '', ENT_QUOTES, 'UTF-8') ?>*/
        $content = preg_replace('/\{\{\s*(.+?)\s*\}\}/s', '<?= htmlspecialchars($1 ?? \'\', ENT_QUOTES, \'UTF-8\') ?>', $content);

        /* Unescaped echo: {!! $expr !!} -> <?= $expr ?>*/
        $content = preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?= $1 ?>', $content);

        return $content;
    }

    public static function extends(string $layout): void {
        self::$layout = $layout;
    }

    public static function section(string $name): void {
        self::$currentSection = $name;
        ob_start();
    }

    public static function endsection(): void {
        if (self::$currentSection) {
            self::$sections[self::$currentSection] = ob_get_clean();
            self::$currentSection = null;
        }
    }

    public static function yield(string $name, string $default = ''): string {
        return self::$sections[$name] ?? $default;
    }

}
