<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private ParameterBagInterface $params,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Envoie un email d'activation patient
     */
    public function sendPatientActivationEmail(string $recipientEmail, string $recipientName, string $activationUrl): void
    {
        $appName = $this->params->get('app.name');
        $fromEmail = $this->params->get('app.email.from');

        $email = (new Email())
            ->from($fromEmail)
            ->to($recipientEmail)
            ->subject('Activation de votre compte ' . $appName)
            ->html($this->getPatientActivationTemplate($recipientName, $activationUrl, $appName));

        try {
            $this->mailer->send($email);
            
            $this->logger->info('Email d\'activation envoyé', [
                'recipient' => $recipientEmail,
                'type' => 'patient_activation'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi email d\'activation', [
                'recipient' => $recipientEmail,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Envoie un email de notification au praticien
     */
    public function sendPractitionerNotification(string $recipientEmail, string $subject, string $message): void
    {
        $fromEmail = $this->params->get('app.email.from');
        $appName = $this->params->get('app.name');

        $email = (new Email())
            ->from($fromEmail)
            ->to($recipientEmail)
            ->subject($subject . ' - ' . $appName)
            ->html($this->getPractitionerNotificationTemplate($message, $appName));

        try {
            $this->mailer->send($email);
            
            $this->logger->info('Email de notification envoyé', [
                'recipient' => $recipientEmail,
                'type' => 'practitioner_notification'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi email de notification', [
                'recipient' => $recipientEmail,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Template HTML pour l'activation patient
     */
    private function getPatientActivationTemplate(string $recipientName, string $activationUrl, string $appName): string
    {
        return '
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Activation de votre compte</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #007bff; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background-color: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
                .button { display: inline-block; background-color: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
                .warning { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
                ul { padding-left: 20px; }
                li { margin-bottom: 8px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>🔐 Activation de votre compte</h1>
                <p>' . $appName . '</p>
            </div>
            
            <div class="content">
                <p>Bonjour <strong>' . htmlspecialchars($recipientName) . '</strong>,</p>
                
                <p>Votre praticien a créé un compte pour vous sur la plateforme <strong>' . $appName . '</strong>.</p>
                
                <p>Pour activer votre compte et définir votre mot de passe, cliquez sur le bouton ci-dessous :</p>
                
                <p style="text-align: center;">
                    <a href="' . htmlspecialchars($activationUrl) . '" class="button">
                        🔑 Activer mon compte
                    </a>
                </p>
                
                <div class="warning">
                    <h3>⚠️ Informations importantes :</h3>
                    <ul>
                        <li><strong>Ce lien est valide pendant 24 heures</strong></li>
                        <li>Vous devrez confirmer votre date de naissance pour des raisons de sécurité</li>
                        <li>Choisissez un mot de passe fort (12 caractères minimum avec majuscule, minuscule, chiffre et caractère spécial)</li>
                        <li>Ne partagez jamais ce lien avec une autre personne</li>
                    </ul>
                </div>
                
                <p>Si vous n\'avez pas demandé cette activation, ignorez cet email et contactez votre praticien.</p>
                
                <p>Cordialement,<br>
                <strong>L\'équipe ' . $appName . '</strong></p>
            </div>
            
            <div class="footer">
                <p>🔒 Vos données sont protégées selon les standards médicaux HDS</p>
                <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
            </div>
        </body>
        </html>';
    }

    /**
     * Template HTML pour notifications praticien
     */
    private function getPractitionerNotificationTemplate(string $message, string $appName): string
    {
        return '
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Notification</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #28a745; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background-color: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
                .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>📋 Notification</h1>
                <p>' . $appName . '</p>
            </div>
            
            <div class="content">
                ' . nl2br(htmlspecialchars($message)) . '
            </div>
            
            <div class="footer">
                <p>Cet email a été envoyé automatiquement depuis ' . $appName . '</p>
            </div>
        </body>
        </html>';
    }
}
