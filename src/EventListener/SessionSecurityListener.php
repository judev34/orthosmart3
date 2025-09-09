<?php

namespace App\EventListener;

use App\Service\SessionManagementService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest', priority: 10)]
#[AsEventListener(event: SecurityEvents::INTERACTIVE_LOGIN, method: 'onInteractiveLogin')]
#[AsEventListener(event: LogoutEvent::class, method: 'onLogout')]
class SessionSecurityListener
{
    public function __construct(
        private SessionManagementService $sessionService,
        private UrlGeneratorInterface $urlGenerator,
        private TokenStorageInterface $tokenStorage
    ) {
    }

    /**
     * Vérifie la sécurité de la session à chaque requête
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // Ignorer les routes publiques et les assets
        $route = $request->attributes->get('_route');
        if (!$route || $this->isPublicRoute($route)) {
            return;
        }

        // Vérifier si l'utilisateur est connecté
        $token = $this->tokenStorage->getToken();
        if (!$token || !$token->getUser()) {
            return;
        }

        // Mettre à jour la dernière activité
        $this->sessionService->updateLastActivity($request);

        // Vérifier l'âge de la session
        if (!$this->sessionService->checkSessionAge($request)) {
            $this->redirectToLogin($event, $request);
            return;
        }

        // Vérifier si la session est suspecte
        if ($this->sessionService->isSessionSuspicious($request)) {
            $this->sessionService->invalidateAllUserSessions($token->getUser());
            $this->redirectToLogin($event, $request);
            return;
        }
    }

    /**
     * Initialise la sécurité de session lors de la connexion
     */
    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $request = $event->getRequest();
        $user = $event->getAuthenticationToken()->getUser();

        // Initialiser les données de sécurité de session
        $this->sessionService->initializeSessionSecurity($request);
        
        // Logger la connexion
        $this->sessionService->logUserLogin($user, $request);
    }

    /**
     * Logger la déconnexion
     */
    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        $token = $event->getToken();
        
        if ($token && $token->getUser()) {
            $this->sessionService->logUserLogout($token->getUser(), $request);
        }
    }

    /**
     * Vérifie si une route est publique
     */
    private function isPublicRoute(string $route): bool
    {
        $publicRoutes = [
            'patient_login',
            'patient_activate',
            'practitioner_login',
            'home',
            '_profiler',
            '_wdt'
        ];

        foreach ($publicRoutes as $publicRoute) {
            if (str_starts_with($route, $publicRoute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Redirige vers la page de connexion appropriée
     */
    private function redirectToLogin(RequestEvent $event, $request): void
    {
        // Déterminer la route de connexion selon le contexte
        $loginRoute = str_starts_with($request->getPathInfo(), '/patient') 
            ? 'patient_login' 
            : 'practitioner_login';

        $loginUrl = $this->urlGenerator->generate($loginRoute);
        $response = new RedirectResponse($loginUrl);
        
        $event->setResponse($response);
    }
}
