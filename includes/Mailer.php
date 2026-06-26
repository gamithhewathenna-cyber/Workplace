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

/**
 * Branded HTML email template matching the portal's dark theme.
 *
 * @param string $heading  Bold heading inside the card
 * @param string $content  HTML body content
 * @param string $btn_text Optional CTA button label
 * @param string $btn_url  Optional CTA button URL
 */
function mail_template(string $heading, string $content, string $btn_text = '', string $btn_url = ''): string {
    $company = htmlspecialchars(get_setting('company_name', 'Employee Portal'), ENT_QUOTES, 'UTF-8');
    $logo    = get_setting('company_logo');
    $host    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

    $logo_tag = $logo
        ? '<img src="' . $host . '/uploads/logo/' . htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') . '" alt="' . $company . '" style="height:36px;object-fit:contain;vertical-align:middle">'
        : '<span style="font-size:1.1rem;font-weight:700;color:#c084fc">' . $company . '</span>';

    $btn_html = ($btn_text && $btn_url)
        ? '<p style="margin:1.75rem 0 .5rem"><a href="' . htmlspecialchars($btn_url, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#7d459a;color:#fff;padding:.7rem 1.6rem;border-radius:8px;text-decoration:none;font-weight:600;font-size:.9rem">' . htmlspecialchars($btn_text, ENT_QUOTES, 'UTF-8') . '</a></p>'
        : '';

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="font-family:Arial,sans-serif;background:#0a0a0a;margin:0;padding:2rem 1rem">'
        . '<div style="max-width:560px;margin:0 auto">'

        // Header
        . '<div style="padding:.75rem 0 1.25rem;margin-bottom:1.25rem;border-bottom:1px solid #1e1e1e">'
        . $logo_tag
        . '</div>'

        // Card
        . '<div style="background:#111111;border-radius:14px;padding:2rem;color:#e8e8e8;border:1px solid #1e1e1e">'
        . '<h2 style="margin:0 0 1.25rem;font-size:1.15rem;color:#c084fc;font-weight:700">' . $heading . '</h2>'
        . '<div style="font-size:.9rem;line-height:1.75;color:#d0d0d0">' . $content . '</div>'
        . $btn_html
        . '</div>'

        // Footer
        . '<p style="text-align:center;color:#444;font-size:.72rem;margin-top:1.25rem">'
        . 'This is an automated message from <strong style="color:#666">' . $company . '</strong>. Please do not reply to this email.'
        . '</p>'

        . '</div></body></html>';
}
