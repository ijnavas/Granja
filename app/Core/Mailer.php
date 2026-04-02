<?php
declare(strict_types=1);

namespace App\Core;

class Mailer
{
    private string $host;
    private int    $port;
    private string $secure;
    private string $user;
    private string $pass;
    private string $from;

    public function __construct()
    {
        $cfg        = require ROOT_PATH . '/config.php';
        $m          = $cfg['mail'];
        $this->host   = $m['smtp_host'];
        $this->port   = (int) $m['smtp_port'];
        $this->secure = $m['smtp_secure']; // ssl | tls | none
        $this->user   = $m['smtp_user'];
        $this->pass   = $m['smtp_pass'];
        $this->from   = $m['mail_from'];
    }

    public function send(string $to, string $subject, string $body): bool
    {
        $prefix = $this->secure === 'ssl' ? 'ssl://' : '';
        $errno  = 0;
        $errstr = '';

        $socket = @stream_socket_client(
            "{$prefix}{$this->host}:{$this->port}",
            $errno,
            $errstr,
            15
        );

        if (!$socket) {
            error_log("Mailer: no se pudo conectar — $errstr ($errno)");
            return false;
        }

        stream_set_timeout($socket, 15);

        $this->read($socket); // Saludo del servidor

        $this->write($socket, 'EHLO ' . gethostname());
        $this->read($socket);

        // STARTTLS si el modo es tls
        if ($this->secure === 'tls') {
            $this->write($socket, 'STARTTLS');
            $this->read($socket);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->write($socket, 'EHLO ' . gethostname());
            $this->read($socket);
        }

        // Autenticación
        $this->write($socket, 'AUTH LOGIN');
        $this->read($socket);
        $this->write($socket, base64_encode($this->user));
        $this->read($socket);
        $this->write($socket, base64_encode($this->pass));
        $authResponse = $this->read($socket);

        if (!str_starts_with($authResponse, '235')) {
            error_log("Mailer: autenticación fallida — $authResponse");
            fclose($socket);
            return false;
        }

        // Envío
        $this->write($socket, "MAIL FROM:<{$this->from}>");
        $this->read($socket);

        $this->write($socket, "RCPT TO:<{$to}>");
        $this->read($socket);

        $this->write($socket, 'DATA');
        $this->read($socket);

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $message  = "From: {$this->from}\r\n";
        $message .= "To: {$to}\r\n";
        $message .= "Subject: {$encodedSubject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "\r\n";
        $message .= chunk_split(base64_encode($body));
        $message .= "\r\n.";

        $this->write($socket, $message);
        $this->read($socket);

        $this->write($socket, 'QUIT');
        fclose($socket);

        return true;
    }

    private function write($socket, string $cmd): void
    {
        fwrite($socket, $cmd . "\r\n");
    }

    private function read($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $response;
    }
}
