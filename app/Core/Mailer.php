<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Mailer
{
    private array $config;

    public function __construct()
    {
        $root = dirname(__DIR__, 2);
        $this->config = require $root . '/config/config.php';
        $this->config = $this->config['mail'] ?? [];

        // Load PHPMailer if not already loaded
        if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer') && !class_exists('\\PHPMailer')) {
            $base = $root . '/app/Lib/PHPMailer';
            $phpmailer = $base . '/PHPMailer.php';
            $smtp      = $base . '/SMTP.php';
            if (!is_file($phpmailer) || !is_file($smtp)) {
                throw new RuntimeException('PHPMailer library not found. Place PHPMailer.php and SMTP.php in app/Lib/PHPMailer/');
            }
            require_once $phpmailer;
            require_once $smtp;
        }
    }

    public function send(string $to, string $subject, string $html, string $text = ''): void
    {
        $class = class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')
            ? '\\PHPMailer\\PHPMailer\\PHPMailer'
            : '\\PHPMailer'; // legacy global class

        $mail = new $class(true);

        // SMTP config
        $mail->isSMTP();
        $mail->Host       = $this->config['host'] ?? '';
        $mail->Port       = (int)($this->config['port'] ?? 587);
        $mail->SMTPAuth   = true;
        $mail->SMTPSecure = $this->config['secure'] ?? 'tls';
        $mail->Username   = $this->config['username'] ?? '';
        $mail->Password   = $this->config['password'] ?? '';

        $mail->setFrom($this->config['from_email'] ?? 'choongyunxian@ga2wellness.com', $this->config['from_name'] ?? 'HireMe');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $text ?: strip_tags($html);

        $mail->send();
    }
}
