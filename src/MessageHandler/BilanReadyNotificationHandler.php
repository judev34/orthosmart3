<?php

namespace App\MessageHandler;

use App\Message\BilanReadyNotification;
use App\Service\EmailService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class BilanReadyNotificationHandler
{
    public function __construct(
        private EmailService $emailService,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(BilanReadyNotification $message): void
    {
        try {
            // Notifier le patient que son bilan est prêt
            $patientSubject = 'Votre bilan orthophonique est disponible';
            $patientMessage = sprintf(
                'Bonjour %s,

Votre bilan orthophonique est maintenant disponible dans votre espace patient.

Vous pouvez le consulter en vous connectant à votre compte.

Cordialement,
L\'équipe OrthoSmart',
                $message->getPatientName()
            );

            $this->emailService->sendPractitionerNotification(
                $message->getPatientEmail(),
                $patientSubject,
                $patientMessage
            );

            // Notifier le praticien
            $practitionerSubject = 'Bilan validé et envoyé au patient';
            $practitionerMessage = sprintf(
                'Le bilan du patient %s (ID: %d) a été validé et est maintenant disponible pour le patient.

Bilan ID: %d',
                $message->getPatientName(),
                $message->getPatientId(),
                $message->getBilanId()
            );

            $this->emailService->sendPractitionerNotification(
                $message->getPractitionerEmail(),
                $practitionerSubject,
                $practitionerMessage
            );

            $this->logger->info('Notifications bilan prêt envoyées via queue', [
                'bilan_id' => $message->getBilanId(),
                'patient_id' => $message->getPatientId(),
                'practitioner_id' => $message->getPractitionerId()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi des notifications bilan via queue', [
                'bilan_id' => $message->getBilanId(),
                'patient_id' => $message->getPatientId(),
                'error' => $e->getMessage()
            ]);
            
            throw $e; // Re-throw pour déclencher le retry
        }
    }
}
