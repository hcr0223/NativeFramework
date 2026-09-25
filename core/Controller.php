<?php

namespace Core;

abstract class Controller {
    protected function render(string $view, array $data = []): void {
        echo View::render($view, $data);
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