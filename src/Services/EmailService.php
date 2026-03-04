<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private PHPMailer $mailer;
    private \Psr\Log\LoggerInterface $logger;

    public function __construct(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->mailer = new PHPMailer(true);

        $this->configureMailer();
    }

    private function configureMailer(): void
    {
        // Debug (0 = off, 2 = verbose) – isključi u produkciji
        $this->mailer->SMTPDebug = 0;
        $this->mailer->isSMTP();
        $this->mailer->Host = $_ENV['MAIL_HOST'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $_ENV['MAIL_USERNAME'];
        $this->mailer->Password = $_ENV['MAIL_PASSWORD'];
        $this->mailer->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
        $this->mailer->Port = (int)$_ENV['MAIL_PORT'];

        $this->mailer->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
        $this->mailer->isHTML(true);
        $this->mailer->CharSet = 'UTF-8';
    }

    public function sendTwoFactorCode(string $toEmail, string $username, string $code): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($toEmail);

            $this->mailer->Subject = 'Vaš 2FA verifikacioni kod';
            $this->mailer->Body = $this->getTwoFactorEmailTemplate($username, $code);
            $this->mailer->AltBody = "Vaš 2FA kod: $code";

            $this->mailer->send();
            $this->logger->info("2FA email POSLAT na: $toEmail | Kod: $code");
            return true;
        } catch (Exception $e) {
            $this->logger->error("2FA email GREŠKA: {$this->mailer->ErrorInfo} | To: $toEmail");
            return false;
        }
    }

    private function getTwoFactorEmailTemplate(string $username, string $code): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>2FA Verifikacioni Kod</title>
            <style>
                body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
                .container { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
                .code { font-size: 32px; font-weight: bold; text-align: center; letter-spacing: 5px; color: #007bff; margin: 20px 0; }
                .footer { font-size: 12px; color: #666; text-align: center; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Poštovani $username,</h2>
                <p>Vaš 2FA verifikacioni kod je:</p>
                <div class='code'>$code</div>
                <p>Ovaj kod važi <strong>10 minuta</strong>.</p>
                <p>Ako niste zahtevali ovaj kod, ignorišite ovu poruku.</p>
                <div class='footer'>
                    &copy; " . date('Y') . " {$_ENV['APP_NAME']}. Sva prava zadržana.
                </div>
            </div>
        </body>
        </html>";
    }
}