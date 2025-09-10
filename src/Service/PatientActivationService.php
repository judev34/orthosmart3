<?php

namespace App\Service;

use App\Entity\Patient;
use App\Entity\PatientActivationToken;
use App\Repository\PatientActivationTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Service\EmailService;
use App\Service\NotificationService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PatientActivationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PatientActivationTokenRepository $tokenRepository,
        private EmailService $emailService,
        private NotificationService $notificationService,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        private UserPasswordHasherInterface $passwordHasher,
        private string $appName = 'OrthoSmart'
    ) {
    }

    /**
     * Génère et envoie un token d'activation pour un patient
     */
    public function generateAndSendActivationToken(Patient $patient, Request $request): string
    {
        // Invalider tous les tokens existants pour ce patient
        $this->tokenRepository->invalidateAllTokensForPatient($patient);

        // Générer un nouveau token sécurisé
        $plainToken = PatientActivationToken::generateSecureToken();
        $tokenHash = PatientActivationToken::hashToken($plainToken);

        // Créer l'entité token
        $activationToken = new PatientActivationToken();
        $activationToken->setPatient($patient)
            ->setTokenHash($tokenHash)
            ->setExpiresAt(new \DateTimeImmutable('+24 hours'))
            ->setIpCreated($request->getClientIp())
            ->setUserAgentCreated($request->headers->get('User-Agent'));

        $this->entityManager->persist($activationToken);
        $this->entityManager->flush();

        // Envoyer l'email d'activation via queue asynchrone
        $this->notificationService->sendPatientActivationNotification($patient, $plainToken);

        // Logger l'action (sans le token)
        $this->logger->info('Token d\'activation généré pour le patient', [
            'patient_id' => $patient->getId(),
            'patient_email' => $patient->getEmail(),
            'ip' => $request->getClientIp(),
            'expires_at' => $activationToken->getExpiresAt()->format('Y-m-d H:i:s')
        ]);

        return $plainToken;
    }

    /**
     * Valide un token d'activation
     */
    public function validateActivationToken(string $token): ?PatientActivationToken
    {
        $this->logger->info('Début validation token d\'activation', [
            'token_length' => strlen($token),
            'token_preview' => substr($token, 0, 8) . '...'
        ]);
        
        $tokenHash = PatientActivationToken::hashToken($token);
        
        $this->logger->info('Token hashé pour recherche', [
            'hash_length' => strlen($tokenHash),
            'hash_preview' => substr($tokenHash, 0, 16) . '...'
        ]);
        
        $activationToken = $this->tokenRepository->findValidTokenByHash($tokenHash);
        
        if (!$activationToken) {
            $this->logger->warning('Aucun token trouvé avec ce hash', [
                'hash_preview' => substr($tokenHash, 0, 16) . '...'
            ]);
            return null;
        }
        
        $this->logger->info('Token trouvé en base', [
            'token_id' => $activationToken->getId(),
            'patient_id' => $activationToken->getPatient()->getId(),
            'expires_at' => $activationToken->getExpiresAt()->format('Y-m-d H:i:s'),
            'used_at' => $activationToken->getUsedAt()?->format('Y-m-d H:i:s'),
            'is_valid' => $activationToken->isValid()
        ]);
        
        if (!$activationToken->isValid()) {
            $this->logger->warning('Token trouvé mais invalide', [
                'token_id' => $activationToken->getId(),
                'is_expired' => $activationToken->isExpired(),
                'is_used' => $activationToken->isUsed(),
                'expires_at' => $activationToken->getExpiresAt()->format('Y-m-d H:i:s'),
                'current_time' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
            ]);
            return null;
        }

        $this->logger->info('Token validé avec succès', [
            'token_id' => $activationToken->getId(),
            'patient_id' => $activationToken->getPatient()->getId()
        ]);

        return $activationToken;
    }

    /**
     * Active un patient avec son token
     */
    public function activatePatient(string $token, string $dateNaissance, string $newPassword): bool
    {
        $activationToken = $this->validateActivationToken($token);
        
        if (!$activationToken) {
            return false;
        }

        $patient = $activationToken->getPatient();
        
        // Vérifier la date de naissance comme double facteur
        if (!$this->verifyDateNaissance($patient, $dateNaissance)) {
            $this->logger->warning('Tentative d\'activation avec mauvaise date de naissance', [
                'patient_id' => $patient->getId(),
                'patient_email' => $patient->getEmail()
            ]);
            return false;
        }

        // Valider le mot de passe
        if (!$this->validatePassword($newPassword)) {
            return false;
        }

        // Activer le patient avec le système de hachage Symfony
        $hashedPassword = $this->passwordHasher->hashPassword($patient, $newPassword);
        $patient->setPassword($hashedPassword);
        
        // Marquer le token comme utilisé
        $activationToken->markAsUsed();
        
        $this->entityManager->flush();

        // Notifier le praticien de l'activation
        $this->notificationService->notifyPatientActivated($patient);

        // Logger l'activation réussie
        $this->logger->info('Patient activé avec succès', [
            'patient_id' => $patient->getId(),
            'patient_email' => $patient->getEmail()
        ]);

        return true;
    }


    /**
     * Vérifie la date de naissance (double facteur)
     */
    private function verifyDateNaissance(Patient $patient, string $dateNaissance): bool
    {
        $patientDateNaissance = $patient->getDateNaissance();
        if (!$patientDateNaissance) {
            return false;
        }

        $providedDate = \DateTime::createFromFormat('Y-m-d', $dateNaissance);
        if (!$providedDate) {
            return false;
        }

        return $patientDateNaissance->format('Y-m-d') === $providedDate->format('Y-m-d');
    }

    /**
     * Valide la politique de mot de passe
     */
    private function validatePassword(string $password): bool
    {
        return strlen($password) >= 12 && 
               preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d])[^\s]{12,}$/', $password);
    }

    /**
     * Nettoie les tokens expirés
     */
    public function cleanExpiredTokens(): int
    {
        $deleted = $this->tokenRepository->deleteExpiredTokens();
        
        if ($deleted > 0) {
            $this->logger->info('Tokens expirés supprimés', ['count' => $deleted]);
        }
        
        return $deleted;
    }
}
