<?php
/**
 * Nexus 2.0 — Mailer
 * Cliente SMTP mínimo sin dependencias externas.
 * Soporta STARTTLS (puerto 587), SSL (puerto 465) y texto plano.
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

class Mailer
{
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $secure;
    private string $fromEmail;
    private string $fromName;

    /** @var resource|null */
    private $socket = null;

    public string $lastError = '';

    public function __construct(array $cfg)
    {
        $this->host      = $cfg['host']       ?? '';
        $this->port      = (int)($cfg['port']  ?? 587);
        $this->user      = $cfg['user']        ?? '';
        $this->pass      = $cfg['pass']        ?? '';
        $this->secure    = strtolower($cfg['secure']    ?? 'tls');
        $this->fromEmail = $cfg['from_email']  ?? $this->user;
        $this->fromName  = $cfg['from_name']   ?? '';
    }

    public function send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        try {
            if (!$this->connect())       { return false; }
            if (!$this->authenticate())  { return false; }
            $this->sendMessage($to, $subject, $htmlBody, $textBody);
            $this->disconnect();
            return true;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->disconnect();
            return false;
        }
    }

    private function connect(): bool
    {
        $target = ($this->secure === 'ssl')
            ? "ssl://{$this->host}:{$this->port}"
            : "{$this->host}:{$this->port}";

        $this->socket = @stream_socket_client($target, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);

        if (!$this->socket) {
            $this->lastError = "Conexión fallida: {$errstr} ({$errno})";
            return false;
        }

        stream_set_timeout($this->socket, 15);
        $this->read();

        $this->cmd('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

        if ($this->secure === 'tls') {
            $this->cmd('STARTTLS');
            stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->cmd('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        }

        return true;
    }

    private function authenticate(): bool
    {
        if (empty($this->user)) return true;

        $this->cmd('AUTH LOGIN');
        $this->cmd(base64_encode($this->user));
        $resp = $this->cmd(base64_encode($this->pass));

        if (substr(ltrim($resp), 0, 3) !== '235') {
            $this->lastError = 'Autenticación fallida: ' . trim($resp);
            return false;
        }
        return true;
    }

    private function sendMessage(string $to, string $subject, string $html, string $text): void
    {
        $from = empty($this->fromName)
            ? $this->fromEmail
            : "{$this->fromName} <{$this->fromEmail}>";

        $this->cmd("MAIL FROM:<{$this->fromEmail}>");
        $this->cmd("RCPT TO:<{$to}>");
        $this->cmd('DATA');

        $boundary = '----=_Part_' . md5(uniqid('', true));
        if (empty($text)) {
            $text = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html));
        }

        $msg  = "Date: " . date('r') . "\r\n";
        $msg .= "From: {$from}\r\n";
        $msg .= "To: {$to}\r\n";
        $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $msg .= "X-Mailer: NexusMail/1.0\r\n\r\n";
        $msg .= "--{$boundary}\r\n";
        $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode($text)) . "\r\n";
        $msg .= "--{$boundary}\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode($html)) . "\r\n";
        $msg .= "--{$boundary}--";

        fwrite($this->socket, $msg . "\r\n.\r\n");
        $this->read();
        $this->cmd('QUIT');
    }

    private function cmd(string $line): string
    {
        fwrite($this->socket, $line . "\r\n");
        return $this->read();
    }

    private function read(): string
    {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return $response;
    }

    private function disconnect(): void
    {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }
}
