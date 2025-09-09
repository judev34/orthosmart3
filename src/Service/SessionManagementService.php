<?php

namespace App\Service;

use App\Entity\Patient;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Psr\Log\LoggerInterface;

class SessionManagementService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Invalide toutes les sessions actives d'un utilisateur après changement de mot de passe
     */
    public function invalidateAllUserSessions(Patient|User $user): void
    {
        // Forcer la déconnexion en invalidant le token actuel
        $this->tokenStorage->setToken(null);
        
        // Invalider la session courante
        $request = $this->requestStack->getCurrentRequest();
        if ($request && $request->hasSession()) {
            $session = $request->getSession();
            $session->invalidate();
            
            // Supprimer les cookies remember_me
            $this->clearRememberMeCookies();
        }

        // Logger l'action de sécurité
        $this->logger->info('Sessions invalidées après changement de mot de passe', [
            'user_type' => $user instanceof Patient ? 'patient' : 'practitioner',
            'user_id' => $user->getId(),
            'user_email' => $user->getEmail(),
            'ip' => $request?->getClientIp(),
            'user_agent' => $request?->headers->get('User-Agent')
        ]);
    }

    /**
     * Enregistre une connexion utilisateur pour audit
     */
    public function logUserLogin(Patient|User $user, Request $request): void
    {
        $this->logger->info('Connexion utilisateur', [
            'user_type' => $user instanceof Patient ? 'patient' : 'practitioner',
            'user_id' => $user->getId(),
            'user_email' => $user->getEmail(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'session_id' => $request->getSession()->getId()
        ]);
    }

    /**
     * Enregistre une déconnexion utilisateur pour audit
     */
    public function logUserLogout(Patient|User $user, Request $request): void
    {
        $this->logger->info('Déconnexion utilisateur', [
            'user_type' => $user instanceof Patient ? 'patient' : 'practitioner',
            'user_id' => $user->getId(),
            'user_email' => $user->getEmail(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'session_id' => $request->getSession()->getId()
        ]);
    }

    /**
     * Vérifie si une session est suspecte (changement d'IP, User-Agent, etc.)
     */
    public function isSessionSuspicious(Request $request): bool
    {
        $session = $request->getSession();
        
        // Vérifier si l'IP a changé
        $currentIp = $request->getClientIp();
        $sessionIp = $session->get('_security_ip');
        
        if ($sessionIp && $sessionIp !== $currentIp) {
            $this->logger->warning('Changement d\'IP détecté dans la session', [
                'session_id' => $session->getId(),
                'original_ip' => $sessionIp,
                'current_ip' => $currentIp
            ]);
            return true;
        }

        // Vérifier si le User-Agent a changé
        $currentUserAgent = $request->headers->get('User-Agent');
        $sessionUserAgent = $session->get('_security_user_agent');
        
        if ($sessionUserAgent && $sessionUserAgent !== $currentUserAgent) {
            $this->logger->warning('Changement de User-Agent détecté dans la session', [
                'session_id' => $session->getId(),
                'original_user_agent' => $sessionUserAgent,
                'current_user_agent' => $currentUserAgent
            ]);
            return true;
        }

        return false;
    }

    /**
     * Initialise les données de sécurité de la session
     */
    public function initializeSessionSecurity(Request $request): void
    {
        $session = $request->getSession();
        
        // Stocker l'IP et User-Agent pour détection de changements
        $session->set('_security_ip', $request->getClientIp());
        $session->set('_security_user_agent', $request->headers->get('User-Agent'));
        $session->set('_security_created_at', time());
    }

    /**
     * Nettoie les cookies remember_me
     */
    private function clearRememberMeCookies(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        // Supprimer les cookies remember_me potentiels
        $cookiesToClear = ['REMEMBERME', 'remember_me', 'PHPSESSID'];
        
        foreach ($cookiesToClear as $cookieName) {
            if ($request->cookies->has($cookieName)) {
                setcookie($cookieName, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true]);
            }
        }
    }

    /**
     * Vérifie l'âge de la session et force la reconnexion si nécessaire
     */
    public function checkSessionAge(Request $request, int $maxAge = 28800): bool // 8 heures par défaut
    {
        $session = $request->getSession();
        $createdAt = $session->get('_security_created_at');
        
        if (!$createdAt) {
            return false;
        }

        $sessionAge = time() - $createdAt;
        
        if ($sessionAge > $maxAge) {
            $this->logger->info('Session expirée par âge', [
                'session_id' => $session->getId(),
                'age_seconds' => $sessionAge,
                'max_age_seconds' => $maxAge
            ]);
            
            $session->invalidate();
            return false;
        }

        return true;
    }

    /**
     * Met à jour le timestamp de dernière activité
     */
    public function updateLastActivity(Request $request): void
    {
        $session = $request->getSession();
        $session->set('_security_last_activity', time());
    }

    /**
     * Génère un rapport de sécurité de session
     */
    public function getSessionSecurityReport(Request $request): array
    {
        $session = $request->getSession();
        
        return [
            'session_id' => $session->getId(),
            'created_at' => $session->get('_security_created_at'),
            'last_activity' => $session->get('_security_last_activity'),
            'ip' => $session->get('_security_ip'),
            'user_agent' => $session->get('_security_user_agent'),
            'current_ip' => $request->getClientIp(),
            'current_user_agent' => $request->headers->get('User-Agent'),
            'age_seconds' => $session->get('_security_created_at') ? time() - $session->get('_security_created_at') : null,
            'idle_seconds' => $session->get('_security_last_activity') ? time() - $session->get('_security_last_activity') : null
        ];
    }
}
