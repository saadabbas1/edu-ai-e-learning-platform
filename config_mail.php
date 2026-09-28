<?php
use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/config.php';

function mailer(): PHPMailer {
  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host       = 'smtp.gmail.com';
  $mail->SMTPAuth   = true;
  $mail->Username   = MAIL_USER;
  $mail->Password   = MAIL_PASS;
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = 587;
  $mail->CharSet    = 'UTF-8';
  $mail->setFrom(MAIL_USER, 'Edu AI');
  return $mail;
}
