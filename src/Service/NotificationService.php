<?php

namespace App\Service;

use App\Message\PatientActivationNotification;
use App\Message\PractitionerNotification;
use App\Message\BilanReadyNotification;
use App\Entity\Patient;
use App\Entity\User;
use App\Entity\Bilan;
use Symfony\Component\Messenger\MessageBusInterface;
use Psr\Log\LoggerInterface;

class NotificationService
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Envoie une notification d'activation patient (asynchrone)
     */
    public function sendPatientActivationNotification(Patient $patient, string $activationToken): void
    {
        $message = new PatientActivationNotification(
            $patient->getId(),
            $patient->getEmail(),
            $patient->getPrenom(),
            $activationToken
        );

        $this->messageBus->dispatch($message);

        $this->logger->info('Notification d\'activation patient mise en queue', [
            'patient_id' => $patient->getId(),
            'patient_email' => $patient->getEmail()
        ]);
    }

    /**
     * Envoie une notification au praticien (asynchrone)
     */
    public function sendPractitionerNotification(User $practitioner, string $subject, string $message, string $type = 'info'): void
    {
        $notification = new PractitionerNotification(
            $practitioner->getId(),
            $practitioner->getEmail(),
            $subject,
            $message,
            $type
        );

        $this->messageBus->dispatch($notification);

        $this->logger->info('Notification praticien mise en queue', [
            'practitioner_id' => $practitioner->getId(),
            'practitioner_email' => $practitioner->getEmail(),
            'type' => $type
        ]);
    }

    /**
     * Envoie une notification de bilan prêt (asynchrone)
     */
    public function sendBilanReadyNotification(Bilan $bilan): void
    {
        $patient = $bilan->getPrescription()->getPatient();
        $practitioner = $patient->getPraticien();

        $notification = new BilanReadyNotification(
            $bilan->getId(),
            $patient->getId(),
            $patient->getEmail(),
            $patient->getNomComplet(),
            $practitioner->getId(),
            $practitioner->getEmail()
        );

        $this->messageBus->dispatch($notification);

        $this->logger->info('Notification bilan prêt mise en queue', [
            'bilan_id' => $bilan->getId(),
            'patient_id' => $patient->getId(),
            'practitioner_id' => $practitioner->getId()
        ]);
    }

    /**
     * Notifications spécifiques pour événements métier
     */
    public function notifyPatientRegistered(Patient $patient): void
    {
        $practitioner = $patient->getPraticien();
        
        $this->sendPractitionerNotification(
            $practitioner,
            'Nouveau patient enregistré',
            sprintf(
                'Le patient %s (%s) a été enregistré avec succès dans votre liste de patients.

Email: %s
Date de naissance: %s

Le patient recevra un email d\'activation pour créer son mot de passe.',
                $patient->getNomComplet(),
                $patient->getEmail(),
                $patient->getEmail(),
                $patient->getDateNaissance()?->format('d/m/Y') ?? 'Non renseignée'
            ),
            'success'
        );
    }

    public function notifyPatientActivated(Patient $patient): void
    {
        $practitioner = $patient->getPraticien();
        
        $this->sendPractitionerNotification(
            $practitioner,
            'Patient activé',
            sprintf(
                'Le patient %s (%s) a activé son compte avec succès.

Il peut maintenant se connecter et passer ses tests orthophoniques.',
                $patient->getNomComplet(),
                $patient->getEmail()
            ),
            'info'
        );
    }

    public function notifyTestCompleted(Patient $patient, string $testName): void
    {
        $practitioner = $patient->getPraticien();
        
        $this->sendPractitionerNotification(
            $practitioner,
            'Test terminé',
            sprintf(
                'Le patient %s (%s) a terminé le test "%s".

Vous pouvez maintenant consulter les résultats et générer le bilan.',
                $patient->getNomComplet(),
                $patient->getEmail(),
                $testName
            ),
            'info'
        );
    }

    public function notifyPrescriptionExpiring(Patient $patient, \DateTimeInterface $expirationDate): void
    {
        $practitioner = $patient->getPraticien();
        
        $this->sendPractitionerNotification(
            $practitioner,
            'Prescription bientôt expirée',
            sprintf(
                'La prescription du patient %s (%s) expire le %s.

Pensez à renouveler la prescription si nécessaire.',
                $patient->getNomComplet(),
                $patient->getEmail(),
                $expirationDate->format('d/m/Y')
            ),
            'warning'
        );
    }
}
