<?php
// Sends one plain email through Gmail (SMTP) using PHPMailer in folder PHPMailer-6.9.3.

function send_smtp_mail($to, $subject, $body, &$error = null)
{
    $error = null;
    $cfg = require __DIR__ . '/mail_config.php';

    if (trim($cfg['smtp_user']) === '' || strpos($cfg['smtp_pass'], 'xxxx') !== false) {
        $error = 'Edit mail_config.php: set your Gmail and app password.';
        return false;
    }

    require_once __DIR__ . '/PHPMailer-6.9.3/src/Exception.php';
    require_once __DIR__ . '/PHPMailer-6.9.3/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer-6.9.3/src/SMTP.php';

    $m = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $m->isSMTP();
        $m->Host       = $cfg['smtp_host'];
        $m->SMTPAuth   = true;
        $m->Username   = $cfg['smtp_user'];
        $m->Password   = $cfg['smtp_pass'];
        $m->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $m->Port       = (int) $cfg['smtp_port'];
        $m->CharSet    = 'UTF-8';

        $m->setFrom($cfg['from_email'], $cfg['from_name']);
        $m->addAddress($to);
        $m->Subject = $subject;
        $m->Body    = $body;
        $m->isHTML(false);
        $m->send();
        return true;
    } catch (Exception $e) {
        $error = $e->getMessage();
        return false;
    }
}
