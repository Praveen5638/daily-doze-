<?php
declare(strict_types=1);

final class Mailer
{
    public static function send(string $to, string $subject, string $html): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            log_message('warning', 'Mail skipped: invalid recipient', ['to' => $to, 'subject' => $subject]);
            return false;
        }

        $transport = strtolower(MAIL_TRANSPORT);
        if ($transport === 'smtp' && MAIL_HOST !== '' && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = MAIL_HOST;
                $mail->Port = MAIL_PORT;
                $mail->SMTPAuth = MAIL_USER !== '';
                if (MAIL_USER !== '') {
                    $mail->Username = MAIL_USER;
                    $mail->Password = MAIL_PASS;
                }
                $mail->SMTPSecure = MAIL_SECURE === 'ssl'
                    ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                    : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->CharSet = 'UTF-8';
                $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
                $mail->addAddress($to);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $html;
                $mail->AltBody = trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $html)));
                $mail->send();
                return true;
            } catch (Throwable $e) {
                log_message('error', 'SMTP send failed', ['error' => $e->getMessage(), 'to' => $to]);
            }
        }

        if ($transport === 'mail' && function_exists('mail')) {
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
            ];
            $sent = @mail($to, $subject, $html, implode("\r\n", $headers));
            if ($sent) {
                return true;
            }
            log_message('error', 'PHP mail() failed', ['to' => $to]);
        }

        self::logOnly($to, $subject, $html);
        return true;
    }

    public static function sendAdmin(string $subject, string $html): bool
    {
        $to = setting('admin_notification_email');
        if ($to === '') {
            $to = setting('contact_email');
        }
        if ($to === '') {
            self::logOnly('(admin)', $subject, $html);
            return true;
        }
        return self::send($to, $subject, $html);
    }

    private static function logOnly(string $to, string $subject, string $html): void
    {
        $dir = STORAGE_PATH . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $entry = str_repeat('=', 70) . PHP_EOL
            . 'Date: ' . date('c') . PHP_EOL
            . 'To: ' . $to . PHP_EOL
            . 'Subject: ' . $subject . PHP_EOL
            . PHP_EOL . $html . PHP_EOL;
        @file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
    }
}
