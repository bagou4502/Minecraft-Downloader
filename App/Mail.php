<?php

namespace App;

use Dotenv\Dotenv;
use JetBrains\PhpStorm\NoReturn;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
class Mail
{
    private string $host;
    private string $username;
    private string $password;
    private int $port;
    private string $fromEmail;
    private string $fromName;
    private string $toEmail;
    private string $toName;

    public function __construct()
    {
        $env = Dotenv::createImmutable(__DIR__ . '/../');
        $env->load();

        $this->host = $_ENV['SMTP_HOST'];
        $this->username = $_ENV['SMTP_USER'];
        $this->password = $_ENV['SMTP_PASS'];
        $this->port = $_ENV['SMTP_PORT'];
        $this->fromEmail = $_ENV['SMTP_FROM_EMAIL'];
        $this->fromName = $_ENV['SMTP_FROM_NAME'];
        $this->toEmail = $_ENV['SMTP_TO_EMAIL'];
        $this->toName = $_ENV['SMTP_TO_NAME'];
    }
    #[NoReturn] protected function makeError($message): void
    {
        http_response_code(500);
        header('Content-Type: application/json');
        $json = ['success' => false, 'message' => $message];
        //Die $json
        die(json_encode($json));
    }
    public function send($subject, $body)
    {

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->username;
            $mail->Password = $this->password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = $this->port;
            $mail->Timeout = 5;

            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($this->toEmail, $this->toName);
            // Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();

            return true;
        } catch (Exception $e) {
            $this->makeError("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }
    }
}