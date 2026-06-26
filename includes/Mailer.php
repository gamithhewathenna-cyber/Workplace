<?php
/**
 * includes/Mailer.php
 * Lightweight SMTP mailer — no external libraries required.
 * Supports: SSL (port 465), STARTTLS (port 587), AUTH LOGIN.
 */

class SmtpMailer {
    private string $host;
    private int    $port;
    private string $enc;    // 'ssl' | 'tls' | 'none'
    private string $user;
    private string $pass;
    private string $from_email;
    private string $from_name;
    /** @var resource|null */
    private $sock = null;
    private array $log = [];

    public function __construct(array $cfg) {
        $this->host       = $cfg['host']       ?? '';
        $this->port       = (int)($cfg['port'] ?? 587);
        $this->enc        = strtolower($cfg['encryption'] ?? 'tls');
        $this->user       = $cfg['username']   ?? '';
        $this->pass       = $cfg['password']   ?? '';
        $this->from_email = $cfg['from_email'] ?? '';
        $this->from_name  = $cfg['from_name']  ?? '';
    }

    public function send(string $to_email, string $to_name, string $subject, string $body, bool $html = true): bool {
        try {
            $this->connect();
            $this->ehlo();

            if ($this->enc === 'tls') {
                $this->write('STARTTLS');
                $this->expect(220, 'STARTTLS');
                stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->ehlo();
            }

            if ($this->user) {
                $this->write('AUTH LOGIN');
                $this->expect(334, 'AUTH LOGIN');
                $this->write(base64_encode($this->user));
                $this->expect(334, 'Username');
                $this->write(base64_encode($this->pass));
                $this->expect(235, 'Password');
            }

            $from_hdr = $this->from_name
                ? '"' . addslashes($this->from_name) . '" <' . $this->from_email . '>'
                : $this->from_email;
            $to_hdr   = $to_name ? '"' . addslashes($to_name) . '" <' . $to_email . '>' : $to_email;

            $this->write('MAIL FROM:<' . $this->from_email . '>');
            $this->expect(250, 'MAIL FROM');

            $this->write('RCPT TO:<' . $to_email . '>');
            $this->expect(250, 'RCPT TO');

            $this->write('DATA');
            $this->expect(354, 'DATA');

            $ctype = $html ? 'text/html' : 'text/plain';
            $enc_subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';

            $msg  = "From: $from_hdr\r\n";
            $msg .= "To: $to_hdr\r\n";
            $msg .= "Subject: $enc_subj\r\n";
            $msg .= "Date: " . date('r') . "\r\n";
            $msg .= "MIME-Version: 1.0\r\n";
            $msg .= "Content-Type: $ctype; charset=UTF-8\r\n";
            $msg .= "Content-Transfer-Encoding: base64\r\n";
            $msg .= "\r\n";
            $msg .= chunk_split(base64_encode($body));
            $msg .= "\r\n.";

            fwrite($this->sock, $msg . "\r\n");
            $this->expect(250, 'Message body');

            $this->write('QUIT');
            fclose($this->sock);
            $this->sock = null;
            return true;

        } catch (RuntimeException $e) {
            $this->log[] = 'ERROR: ' . $e->getMessage();
            if ($this->sock) { fclose($this->sock); $this->sock = null; }
            return false;
        }
    }

    public function lastError(): string {
        foreach (array_reverse($this->log) as $l) {
            if (strpos($l, 'ERROR:') === 0) return substr($l, 7);
        }
        return '';
    }

    public function getLog(): array { return $this->log; }

    // ── Internal helpers ───────────────────────────────────

    private function connect(): void {
        $addr = ($this->enc === 'ssl' ? 'ssl://' : '') . $this->host . ':' . $this->port;
        $this->log[] = "Connecting to $addr";
        $this->sock = @stream_socket_client($addr, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
        if (!$this->sock) {
            throw new RuntimeException("Connection failed to $addr — $errstr ($errno)");
        }
        stream_set_timeout($this->sock, 15);
        $greeting = $this->readLine();
        if ((int)$greeting !== 220) {
            throw new RuntimeException("Bad greeting: $greeting");
        }
    }

    private function ehlo(): void {
        $host = $_SERVER['HTTP_HOST'] ?? gethostname() ?: 'localhost';
        $this->write('EHLO ' . $host);
        // Read multi-line EHLO response
        while ($line = fgets($this->sock, 515)) {
            $this->log[] = '< ' . trim($line);
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
    }

    private function write(string $cmd): void {
        $safe = (strpos($cmd, 'LOGIN') !== false || strpos($cmd, 'PASS') !== false)
            ? '[AUTH LINE HIDDEN]'
            : $cmd;
        $this->log[] = '> ' . $safe;
        fwrite($this->sock, $cmd . "\r\n");
    }

    private function expect(int $code, string $ctx): void {
        $resp = $this->readLine();
        if ((int)$resp !== $code) {
            throw new RuntimeException("$ctx — expected $code, got: $resp");
        }
    }

    private function readLine(): string {
        $data = '';
        while ($line = fgets($this->sock, 515)) {
            $this->log[] = '< ' . trim($line);
            $data = $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return substr(trim($data), 0, 3);
    }
}

// ── Global helper ──────────────────────────────────────────

/**
 * Send an email using stored SMTP settings.
 * Returns true on success, false on failure.
 * Call get_mail_error() to retrieve the error message.
 */
function send_mail(string $to_email, string $to_name, string $subject, string $body, bool $html = true): bool {
    $host = get_setting('smtp_host');

    // Fall back to PHP mail() if SMTP not configured
    if (!$host) {
        $from    = get_setting('smtp_from_email') ?: ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $headers = "From: $from\r\nContent-Type: text/" . ($html ? 'html' : 'plain') . "; charset=UTF-8";
        return @mail($to_email, $subject, $body, $headers);
    }

    $mailer = new SmtpMailer([
        'host'       => $host,
        'port'       => get_setting('smtp_port', '587'),
        'username'   => get_setting('smtp_username'),
        'password'   => get_setting('smtp_password'),
        'encryption' => get_setting('smtp_encryption', 'tls'),
        'from_email' => get_setting('smtp_from_email'),
        'from_name'  => get_setting('smtp_from_name'),
    ]);

    $ok = $mailer->send($to_email, $to_name, $subject, $body, $html);

    if (!$ok) {
        $_SESSION['_mail_error'] = $mailer->lastError();
    }

    return $ok;
}

function get_mail_error(): string {
    $e = $_SESSION['_mail_error'] ?? '';
    unset($_SESSION['_mail_error']);
    return $e;
}
