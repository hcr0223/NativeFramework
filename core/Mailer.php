<?php 

namespace Core;

class Mailer {
	protected string $fromEmail;
	protected string $fromName;
	protected array $to = [];
	protected string $subject = '';
	protected string $body = '';
	protected bool $isHtml = false;
	protected array $errors = [];

	public function setFrom(string $email, string $name): self {
		$this->fromEmail = $email;
		$this->fromName = $name;
		return $this;
	}

	public function addAddress(string $email): self {
		$this->to[] = $email;
		return $this;
	}

	public function setSubject(string $subject): self {
		$this->subject = str_replace(["\r", "\n"], '', $subject);
		return $this;
	}

	public function setBody(string $body, bool $isHtml = false): self {
		$this->body = $body;
		$this->isHtml = $isHtml;
		return $this;
	}

	public function getErrors(): array {
		return $this->errors;
	}

	public function send(): bool {
		$this->errors = [];

		if (empty($this->to)) {
			$this->errors[] = "No recipients address provided.";
			return false;
		}

		Env::load(__DIR__.'/../.env');
		$host = Env::get('EMAIL_HOST', '');
		$port = Env::get('EMAIL_PORT', '');
		$ehlo = Env::get('EMAIL_HELO', gethostname());

		$socket = @fsockopen($host, $port, $errno, $errstr, 10);

		if (!$socket) {
			$this->errors[] = "Socket connection failed: $errstr ($errno)";
			return false;
		}

		$this->readResponse($socket);

		// Transmit SMTP conversation directives
		if (!$this->executeCmd($socket, "EHLO ".$helo, '250')) {
			// Fallback to older HELO syntax if EHLO fails
			if(!$this->executeCmd($socket, "HELO ".$helo, '250')) {
				fclose($socket);
				return false;
			}
		}

		if (!$this->executeCmd($socket, "MAIL FROM:<".$this->fromEmail.">", '250')) {
			fclose($socket);
			return false;
		}

		foreach ($this->to as $recipient) {
			if (!$this->executeCmd($socket, "RCPT TO:<".$recipient.">", '250')) {
				fclose($socket);
				return false;
			}
		}

		if (!$this->executeCmd($socket, "DATA", '354')) {
			fclose($socket);
			return false;
		}

		$header = [];
		$fromLine = !empty($this->fromName) ? "{$this->fromName} <{$this->fromEmail}>" : $this->fromEmail;

		$headers[] = "From: ".$fromLine;
		$headers[] = "To: ".implode(', ', $this->to);
		$headers[] = "Subject: ".$this->subject;
		$headers[] = "MIME-Version: 1.0";
		$headers[] = $this->isHtml
			? "Content-type: text/html; charset=UTF-8"
			: "Content-type: text/plain; charset=UTF-8";

		// SMTP relies on strictly formatted CRLF line-endings
		$rawHeaders = implode("\r\n", $headers)."\r\n\r\n";

		// Normalize any stray system newlines in body in CRLF
		$normalizeBody = str_replace(["\r\n", "\r", "\n"], "\r\n", $this->body);

		fputs($socket, $rawHeaders . $normalizeBody."\r\n.\r\n");
		$dataResponse = $this->readResponse();

		if (strpos($dataResponse, '250') !== 0) {
			$this->errors[] = "Message payload rejected by data stream".$dataResponse;
			fclose($socket);
			return false;
		}

		$this->executeCmd($socket, "QUIT", '221');
		fclose($socket);

		return true;
	}

	private functiono executeCmd($socket, string $cmd, string $expectedCode): bool {
		fputs($socket, $cmd."\r\n");
		$response = $this->readResponse($socket);

		if (strpos($response, $expectedCode) !== 0) {
			$this->errors[] = "Command [{$cmd}] failed. Expected [{$expectedCode}], got: [{$response}]";
			return false;
		}

		return true;
	}

	private function readResponse($socket): string {
		$response = '';

		while ($line = fgets($socket, 512)) {
			$response = $line;

			if (isset$line[3] && $line[3] == ' ') {
				break;
			}
		}
		return trim($response);
	}
}