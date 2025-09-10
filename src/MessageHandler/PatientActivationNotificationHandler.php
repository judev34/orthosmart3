<?php

namespace App\MessageHandler;

use App\Message\PatientActivationNotification;
use App\Service\EmailService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class PatientActivationNotificationHandler
{
    public function __construct(
        private EmailService $emailService,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(PatientActivationNotification $message): void
    {
        try {
            $token = $message->getActivationToken();
            
            $this->logger->info('Traitement message d\'activation', [
                'patient_id' => $message->getPatientId(),
                'patient_email' => $message->getPatientEmail(),
                'token_length' => strlen($token),
                'token_preview' => substr($token, 0, 8) . '...'
            ]);
            
            // Générer l'URL d'activation
            $activationUrl = $this->urlGenerator->generate(
                'patient_activate',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $this->logger->info('URL d\'activation générée', [
                'url' => $activationUrl
            ]);

            // Envoyer l'email d'activation
            $this->emailService->sendPatientActivationEmail(
                $message->getPatientEmail(),
                $message->getPatientName(),
                $activationUrl
            );

            $this->logger->info('Email d\'activation envoyé via queue', [
                'patient_id' => $message->getPatientId(),
                'patient_email' => $message->getPatientEmail()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi d\'email d\'activation via queue', [
                'patient_id' => $message->getPatientId(),
                'patient_email' => $message->getPatientEmail(),
                'error' => $e->getMessage()
            ]);
            
            throw $e; // Re-throw pour déclencher le retry
        }
    }
}
