<?php

namespace App\Mailer;

use RuntimeException;

class SmtpTransport
{
    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * @var string
     */
    private $encryption;

    /**
     * @var int
     */
    private $timeout;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @param string      $host
     * @param int         $port
     * @param string      $encryption
     * @param int         $timeout
     * @param string|null $username
     * @param string|null $password
     */
    public function __construct($host, $port, $encryption, $timeout, $username = null, $password = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->encryption = strtolower($encryption);
        $this->timeout = $timeout;
        $this->username = $username ?: '';
        $this->password = $password ?: '';
    }

    /**
     * @param string      $fromEmail
     * @param string      $fromName
     * @param string      $toEmail
     * @param string      $subject
     * @param string      $body
     * @param string|null $replyTo
     * @return void
     */
    public function send($fromEmail, $fromName, $toEmail, $subject, $body, $replyTo = null)
    {
        $remote = $this->host . ':' . $this->port;
        $cryptoTransport = null;

        if ($this->encryption === 'ssl') {
            $remote = 'ssl://' . $remote;
        } elseif ($this->encryption === 'tls') {
            $cryptoTransport = 'tls';
        }

        $context = stream_context_create();
        $socket = @stream_socket_client($remote, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            throw new RuntimeException('SMTP sunucusuna bağlanılamadı: ' . $errstr . ' (' . $errno . ')');
        }

        stream_set_timeout($socket, $this->timeout);
        $this->expect($socket, 220);
        $this->write($socket, 'EHLO ' . $this->getHostName());
        $response = $this->readMultiline($socket);

        if ($cryptoTransport === 'tls') {
            $this->write($socket, 'STARTTLS');
            $this->expect($socket, 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                throw new RuntimeException('STARTTLS müzakeresi başarısız oldu.');
            }
            $this->write($socket, 'EHLO ' . $this->getHostName());
            $response = $this->readMultiline($socket);
        }

        if ($this->username !== '') {
            $this->write($socket, 'AUTH LOGIN');
            $this->expect($socket, 334);
            $this->write($socket, base64_encode($this->username));
            $this->expect($socket, 334);
            $this->write($socket, base64_encode($this->password));
            $this->expect($socket, 235);
        }

        $this->write($socket, 'MAIL FROM:<' . $fromEmail . '>');
        $this->expect($socket, 250);

        $recipients = is_array($toEmail) ? $toEmail : array($toEmail);
        foreach ($recipients as $recipient) {
            $this->write($socket, 'RCPT TO:<' . $recipient . '>');
            $this->expect($socket, 250);
        }

        $this->write($socket, 'DATA');
        $this->expect($socket, 354);

        $headers = array();
        $headers[] = 'From: ' . $this->formatAddress($fromEmail, $fromName);
        $headers[] = 'To: ' . (is_array($toEmail) ? implode(', ', $toEmail) : $toEmail);
        $headers[] = 'Subject: ' . $subject;
        $headers[] = 'Date: ' . date(DATE_RFC2822);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $headers[] = 'X-Mailer: ResellerPanel';

        if ($replyTo) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $messageData = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        $messageData = str_replace(["\r\n", "\r", "\n"], "\r\n", $messageData);
        $messageData = str_replace("\n.", "\n..", $messageData);

        $this->write($socket, $messageData . "\r\n.");
        $this->expect($socket, 250);

        $this->write($socket, 'QUIT');
        fclose($socket);
    }

    /**
     * @param resource $socket
     * @param string   $command
     * @return void
     */
    private function write($socket, $command)
    {
        fwrite($socket, $command . "\r\n");
    }

    /**
     * @param resource $socket
     * @param int      $expectedCode
     * @return void
     */
    private function expect($socket, $expectedCode)
    {
        $response = $this->readLine($socket);
        if ((int)substr($response, 0, 3) !== $expectedCode) {
            throw new RuntimeException('SMTP sunucusundan beklenmeyen yanıt: ' . $response);
        }
    }

    /**
     * @param resource $socket
     * @return string
     */
    private function readLine($socket)
    {
        $line = fgets($socket, 515);
        if ($line === false) {
            throw new RuntimeException('SMTP sunucusundan yanıt alınamadı.');
        }
        return trim($line);
    }

    /**
     * @param resource $socket
     * @return string
     */
    private function readMultiline($socket)
    {
        $data = '';
        do {
            $line = fgets($socket, 515);
            if ($line === false) {
                throw new RuntimeException('SMTP sunucusundan yanıt alınamadı.');
            }
            $data .= $line;
        } while (isset($line[3]) && $line[3] === '-');

        return $data;
    }

    /**
     * @return string
     */
    private function getHostName()
    {
        $host = gethostname();
        if ($host === false || $host === '') {
            $host = 'localhost';
        }
        return $host;
    }

    /**
     * @param string $email
     * @param string $name
     * @return string
     */
    private function formatAddress($email, $name)
    {
        $trimmedName = trim($name);
        if ($trimmedName === '') {
            return '<' . $email . '>';
        }

        return sprintf('"%s" <%s>', addcslashes($trimmedName, '"\\'), $email);
    }
}
