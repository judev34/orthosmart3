<?php

namespace App\Controller;

use App\Entity\Patient;
use App\Form\PatientActivationType;
use App\Service\PatientActivationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Psr\Log\LoggerInterface;

class PatientActivationController extends AbstractController
{
    public function __construct(
        private PatientActivationService $activationService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Page d'activation du compte patient
     */
    #[Route('/patient/activate/{token}', name: 'patient_activate')]
    public function activate(string $token, Request $request, RateLimiterFactory $globalIpLimiter): Response
    {
        $logger = $this->logger;
        
        $logger->info('Accès à la route d\'activation', [
            'token_length' => strlen($token),
            'token_preview' => substr($token, 0, 8) . '...',
            'ip' => $request->getClientIp(),
            'method' => $request->getMethod()
        ]);
        
        // Rate limiting global
        $limiter = $globalIpLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            $logger->warning('Rate limit dépassé pour activation', [
                'ip' => $request->getClientIp()
            ]);
            $this->addFlash('error', 'Trop de requêtes. Veuillez réessayer plus tard.');
            return $this->redirectToRoute('app_home');
        }

        // Vérifier si le token est valide
        $activationToken = $this->activationService->validateActivationToken($token);
        
        if (!$activationToken) {
            $logger->warning('Token d\'activation invalide ou expiré', [
                'token_preview' => substr($token, 0, 8) . '...',
                'ip' => $request->getClientIp()
            ]);
            $this->addFlash('error', 'Ce lien d\'activation est invalide ou a expiré. Contactez votre praticien pour obtenir un nouveau lien.');
            return $this->redirectToRoute('app_home');
        }

        $patient = $activationToken->getPatient();
        $form = $this->createForm(PatientActivationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dateNaissance = $form->get('dateNaissance')->getData()->format('Y-m-d');
            $newPassword = $form->get('plainPassword')->getData();

            if ($this->activationService->activatePatient($token, $dateNaissance, $newPassword)) {
                $this->addFlash('success', 'Votre compte a été activé avec succès ! Vous pouvez maintenant vous connecter.');
                return $this->redirectToRoute('patient_login');
            } else {
                $this->addFlash('error', 'Erreur lors de l\'activation. Vérifiez votre date de naissance et votre mot de passe.');
            }
        }

        return $this->render('patient/activate.html.twig', [
            'form' => $form->createView(),
            'patient' => $patient,
            'token' => $token
        ]);
    }

    /**
     * Renvoyer un email d'activation (pour les praticiens)
     */
    #[Route('/practitioner/patient/{id}/resend-activation', name: 'practitioner_resend_activation')]
    public function resendActivation(Patient $patient, Request $request): Response
    {
        // TODO: Vérifier que le praticien a accès à ce patient
        // TODO: Ajouter rate limiting spécifique
        
        try {
            $this->activationService->generateAndSendActivationToken($patient, $request);
            $this->addFlash('success', 'Email d\'activation renvoyé à ' . $patient->getEmail());
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email d\'activation.');
        }

        return $this->redirectToRoute('practitioner_patients_show', ['id' => $patient->getId()]);
    }
}
