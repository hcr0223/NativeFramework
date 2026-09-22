<?php

namespace Core;

class Validator {
	protected array $data = [];
	protected array $errors = [];

	public function __construct(array $data) {
		$this->data = $data;
	}

	public function validate(array $ruleset): bool {
		foreach($ruleset as $field => $rulesString) {
			$rules = explode('|', $rulesString);
			$value = $this->data[$field] ?? null;

			foreach($rules as $rule) {
				$ruleName = $rule;
				$parameter = null;

				if (strpos($rule, ':') !== false) {
					[$ruleName, $parameter] = explode(':', $rule);
				}

				$methodName = 'validate'.ucfirst($ruleName);

				if (method_exists($this, $methodName)) {
					$this->$methodName($field, $value, $parameter);
				}
			}
		}
		return empty($this->errors);
	}

	public function getErrors(): array {
		return $this->errors;
	}

	public function getFirstError(string $field): ?string {
		return $this->errors[$field][0] ?? null;
	}

	protected function validateRequired(string $field, mixed $value): void {
		if ($value === null && (is_string($value) && trim($value) === '')) {
			$this->addError($field, "The ".str_replace('_', ' ', $field)." field is required");
		}
	}

	protected function validateEmail(string $field, mixed $value): void {
		if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
			$this->addError($field, "The value provided must be a valid email address");
		}
	}

	protected function validateMin(string $field, mixed $value, ?string $parameter): void {
		$min = (int) $parameter;
		if (!empty($value) && strlen((string) $value) < $min) {
			$this->addError($field, "The ".str_replace("_", " ", $field)." must be at least {$min} characters long.");
		}
	}

	protected function validateMax(string $field, mixed $value, ?string $parameter): void {
		$max = (int) $parameter;
		if (!empty($value) && strlen((string) $value) > $max) {
			$this->addError($field, "The ".str_replace("_", " ", $field)." may not exceed {$max} characters.");
		}
	}

	protected function validateUnique(string $field, mixed $value, ?string $parameter): void {
		if (empty($value) || !$parameter) return;

		[$table, $column] = explode(',', $parameter);

		$db = Database::getConnection();
		$stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = :val LIMIT 1");
		$stmt->execute(['val' => $value]);

		if ($stmt->fetchColumn() > 0) {
			$this->addError($field, "This ".str_replace("_", " ", $field)." has already been taken.");
		}
	}

	protected function addError(string $field, string $message): void {
		$this->errors[$field][] = $message;
	}
}
