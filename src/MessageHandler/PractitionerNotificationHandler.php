<?php

namespace App\MessageHandler;

use App\Message\PractitionerNotification;
use App\Service\EmailService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class PractitionerNotificationHandler
{
    public function __construct(
        private EmailService $emailService,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(PractitionerNotification $message): void
    {
        try {
            // Envoyer la notification au praticien
            $this->emailService->sendPractitionerNotification(
                $message->getPractitionerEmail(),
                $message->getSubject(),
                $message->getMessage()
            );

            $this->logger->info('Notification praticien envoyée via queue', [
                'practitioner_id' => $message->getPractitionerId(),
                'practitioner_email' => $message->getPractitionerEmail(),
                'type' => $message->getType()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de notification praticien via queue', [
                'practitioner_id' => $message->getPractitionerId(),
                'practitioner_email' => $message->getPractitionerEmail(),
                'error' => $e->getMessage()
            ]);
            
            throw $e; // Re-throw pour déclencher le retry
        }
    }
}
