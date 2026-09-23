<?php

namespace Core;

abstract class Controller {
    protected function render(string $view, array $data = []) {
        extract($data);

        $viewFile = __DIR__."/../app/Views/{$view}.php";

        if (file_exists($viewFile)) {
            ob_start();
            include $viewFile;
            $content = ob_get_clean();

            echo $content;
        } else {
            http_response_code(500);
            echo "<h1>500 Internal Server Error</h1><p>View file '{$view}.php' not found!</p>";
        }
    }

    protected function requestData(): array {
        $sanitized = [];
        foreach($_POST as $key => $value) {
            $sanitized[$key] = is_string($value) ? trim($value) : $value;
        }
        return $sanitized;
    }

    protected function validate(array $rules): ?Validator {
        $data = $this->requestData();
        $validator = new Validator($data);

        if (!$validator->validate($rules)) {
            return $validator;
        }

        return null;
    }

    public function json(mixed $data, int $statusCode = 200): void {
        if (ob_get_level() > 0) {
            ob_clean();
        }

        http_response_code($statusCode);
        header("Content-type: application/json; charset=utf-8");
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}