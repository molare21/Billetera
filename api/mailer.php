<?php

function aldia_mail_config(): ?array {
    $path = dirname($_SERVER['DOCUMENT_ROOT']) . '/private/aldia-mail.php';
    if (!is_file($path)) {
        return null;
    }
    $config = require $path;
    return is_array($config) ? $config : null;
}

function aldia_smtp_read($fp): string {
    $response = '';
    while (($line = fgets($fp, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function aldia_smtp_code(string $response): int {
    return (int)substr($response, 0, 3);
}

function aldia_smtp_expect($fp, array $allowed): string {
    $response = aldia_smtp_read($fp);
    if (!in_array(aldia_smtp_code($response), $allowed, true)) {
        throw new RuntimeException('SMTP error');
    }
    return $response;
}

function aldia_smtp_command($fp, string $command, array $allowed): string {
    fwrite($fp, $command . "\r\n");
    return aldia_smtp_expect($fp, $allowed);
}

function aldia_send_email(string $to, string $subject, string $html): array {
    $config = aldia_mail_config();

    if (!$config) {
        return [
            'success' => false,
            'configured' => false,
            'message' => 'El correo transaccional todavía no está configurado.'
        ];
    }

    $host = trim((string)($config['host'] ?? 'smtp.hostinger.com'));
    $port = (int)($config['port'] ?? 465);
    $secure = strtolower((string)($config['secure'] ?? 'ssl'));
    $username = trim((string)($config['username'] ?? ''));
    $password = (string)($config['password'] ?? '');
    $fromEmail = trim((string)($config['from_email'] ?? $username));
    $fromName = trim((string)($config['from_name'] ?? 'AL DÍA'));

    if ($username === '' || $password === '' || $fromEmail === '') {
        return [
            'success' => false,
            'configured' => false,
            'message' => 'La configuración SMTP está incompleta.'
        ];
    }

    $transport = $secure === 'ssl' ? 'ssl://' . $host : $host;
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false
        ]
    ]);

    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client(
        $transport . ':' . $port,
        $errno,
        $errstr,
        12,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$fp) {
        return [
            'success' => false,
            'configured' => true,
            'message' => 'No se pudo conectar al servidor de correo.'
        ];
    }

    stream_set_timeout($fp, 12);

    try {
        aldia_smtp_expect($fp, [220]);
        aldia_smtp_command($fp, 'EHLO aldiabilletera.com', [250]);

        if ($secure === 'tls' || $secure === 'starttls') {
            aldia_smtp_command($fp, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('TLS error');
            }
            aldia_smtp_command($fp, 'EHLO aldiabilletera.com', [250]);
        }

        aldia_smtp_command($fp, 'AUTH LOGIN', [334]);
        aldia_smtp_command($fp, base64_encode($username), [334]);
        aldia_smtp_command($fp, base64_encode($password), [235]);

        aldia_smtp_command($fp, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        aldia_smtp_command($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        aldia_smtp_command($fp, 'DATA', [354]);

        $boundary = 'aldia_' . bin2hex(random_bytes(8));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';

        $plain = trim(strip_tags(
            preg_replace('/<br\s*\/?>/i', "\n", $html)
        ));

        $message =
            'From: ' . $encodedFromName . ' <' . $fromEmail . ">\r\n" .
            'To: <' . $to . ">\r\n" .
            'Subject: ' . $encodedSubject . "\r\n" .
            'MIME-Version: 1.0' . "\r\n" .
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n" .
            "\r\n" .
            '--' . $boundary . "\r\n" .
            'Content-Type: text/plain; charset=UTF-8' . "\r\n" .
            'Content-Transfer-Encoding: 8bit' . "\r\n\r\n" .
            $plain . "\r\n\r\n" .
            '--' . $boundary . "\r\n" .
            'Content-Type: text/html; charset=UTF-8' . "\r\n" .
            'Content-Transfer-Encoding: 8bit' . "\r\n\r\n" .
            $html . "\r\n\r\n" .
            '--' . $boundary . '--' . "\r\n";

        // SMTP dot-stuffing
        $message = preg_replace('/^\./m', '..', $message);
        fwrite($fp, $message . "\r\n.\r\n");
        aldia_smtp_expect($fp, [250]);
        aldia_smtp_command($fp, 'QUIT', [221]);

        fclose($fp);

        return [
            'success' => true,
            'configured' => true,
            'message' => 'Correo enviado.'
        ];

    } catch (Throwable $e) {
        @fclose($fp);
        return [
            'success' => false,
            'configured' => true,
            'message' => 'No se pudo enviar el correo.'
        ];
    }
}

function aldia_email_shell(string $title, string $bodyHtml, string $buttonText, string $buttonUrl): string {
    $safeUrl = htmlspecialchars($buttonUrl, ENT_QUOTES, 'UTF-8');
    $safeButton = htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8');
    return '<!doctype html><html><body style="margin:0;background:#080808;color:#f8f6f1;font-family:Arial,sans-serif">' .
        '<div style="max-width:560px;margin:0 auto;padding:42px 24px">' .
        '<div style="font-size:22px;font-weight:800;margin-bottom:34px">al <span style="color:#efb548">día</span></div>' .
        '<h1 style="font-size:26px;margin:0 0 12px">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>' .
        '<div style="color:#b7b0a6;font-size:15px;line-height:1.6">' . $bodyHtml . '</div>' .
        '<a href="' . $safeUrl . '" style="display:block;text-align:center;margin:28px 0 22px;padding:15px 18px;background:#efb548;color:#080808;text-decoration:none;border-radius:10px;font-weight:800">' . $safeButton . '</a>' .
        '<div style="font-size:11px;line-height:1.5;color:#6f6a63">Si tú no solicitaste este correo, puedes ignorarlo.</div>' .
        '</div></body></html>';
}
