<?php 

namespace Core;

class Mailer {
	protected string $fromEmail = '';
	protected string $fromName = '';
	protected array $to = [];
	protected string $subject = '';
	protected string $body = '';
	protected bool $isHtml = true;
	protected array $errors = [];
	protected array $attachments = [];

	public function __construct() {
		Env::load(__DIR__.'/../.env');
		$this->fromEmail = Env::get('EMAIL_FROM_ADDRESS', '');
		$this->fromName = Env::get('MAIL_FROM_NAME', '');
	}

	public function setFrom(string $email, string $name): self {
		$this->fromEmail = $email;
		$this->fromName = $name;
		return $this;
	}

	public function addAddress(string $email): self {
		$this->to[] = $email;
		return $this;
	}

	public function addAttachment(string $filePath, string $filename = ''): self {
		if (file_exists($filePath)) {
			$this->attachments[] = [
				'name' => $filename !== '' ? $filename : basename($filePath),
				'data' => file_get_contents($filePath)
			];
		} else {
			$this->errors[] = "Attachment file not found: {$filePath}";
		}
		return $this;
	}

	public function addAttachmentData(string $content, string $filename): self {
		if (empty($filename)) {
			$this->errors[] = "Attachment filename cannot be empty when using raw data.";
			return $this;
		}

		$this->attachments[] = [
			'name' => $filename,
			'data' => $content
		];

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

	public function view(string $view, array $data = []):self {
		$this->body = View::render($view, $data);
		$this->isHtml = true;
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
		$host = Env::get('EMAIL_HOST', '127.0.0.1');
		$port = Env::get('EMAIL_PORT', '25');
		$username = Env::get('EMAIL_USERNAME', '');
		$password = Env::get('EMAIL_PASSWORD','');
		$ehlo = Env::get('EMAIL_HELO', gethostname());


		$socket = @fsockopen($host, (int) $port, $errno, $errstr, 10);

		if (!$socket) {
			$this->errors[] = "Socket connection failed: $errstr ($errno)";
			return false;
		}

		$this->readResponse($socket);

		// Transmit SMTP conversation directives
		if (!$this->executeCmd($socket, "EHLO ".$ehlo, '250')) {
			// Fallback to older HELO syntax if EHLO fails
			if(!$this->executeCmd($socket, "HELO ".$ehlo, '250')) {
				fclose($socket);
				return false;
			}
		}

		// Autenticación SMTP si se definieron credenciales
        if (!empty($username) && !empty($password)) {
            if (!$this->executeCmd($socket, "AUTH LOGIN", '334')) {
                fclose($socket);
                return false;
            }
            if (!$this->executeCmd($socket, base64_encode($username), '334')) {
                fclose($socket);
                return false;
            }
            if (!$this->executeCmd($socket, base64_encode($password), '235')) {
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

		$fromLine = !empty($this->fromName) ? "{$this->fromName} <{$this->fromEmail}>" : $this->fromEmail;
		$boundary = "----=_Part_".md5(uniqid((string)time(), true));

		$headers = [];
		$headers[] = "From: ".$fromLine;
		$headers[] = "To: ".implode(', ', $this->to);
		$headers[] = "Subject: ".$this->subject;
		$headers[] = "MIME-Version: 1.0";

		if (!empty($this->attachments)) {
			$headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
		} else {
			$headers[] = $this->isHtml
				? "Content-Type: text/html; charset=UTF-8"
				: "Content-Type: text/plain; charset=UTF-8";
		}

		// SMTP relies on strictly formatted CRLF line-endings
		$rawHeaders = implode("\r\n", $headers)."\r\n\r\n";

		// Normalize any stray system newlines in body in CRLF
		$payload = "";
		$normalizeBody = str_replace(["\r\n", "\r", "\n"], "\r\n", $this->body);

		if (!empty($this->attachments)) {
			$contentType = $this->isHtml ? 'text/html' : 'text/plain';

			/* Part 1 of payload */
			$payload .= "--{$boundary}\r\n";
			$payload .= "Content-Type: {$contentType}; charset=UTF-8\r\n";
			$payload .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
			$payload .= $normalizeBody."\r\n\r\n";

			/* Part 2 of payload */
			foreach ($this->attachments as $attachment) {
				$encodedContent = chunk_split(base64_encode($attachment['data']));

				$payload .= "--{$boundary}\r\n";
				$payload .= "Content-Type: application/octet-stream; name=\"{$attachment['name']}\"\r\n";
				$payload .= "Content-Transfer-Encoding: base64\r\n";
				$payload .= "Content-Disposition: attachment; filename=\"{$attachment['name']}\"\r\n\r\n";
				$payload .= $encodedContent."\r\n";
			}

			$payload .= "--{$boundary}--\r\n";
		} else {
			$payload = $normalizeBody."\r\n";
		}

		fputs($socket, $rawHeaders . $normalizeBody."\r\n.\r\n");
		$dataResponse = $this->readResponse($socket);

		if (strpos($dataResponse, '250') !== 0) {
			$this->errors[] = "Message payload rejected by data stream".$dataResponse;
			fclose($socket);
			return false;
		}

		$this->executeCmd($socket, "QUIT", '221');
		fclose($socket);

		return true;
	}

	private function executeCmd($socket, string $cmd, string $expectedCode): bool {
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

			if (isset($line[3]) && $line[3] == ' ') {
				break;
			}
		}
		return trim($response);
	}
}