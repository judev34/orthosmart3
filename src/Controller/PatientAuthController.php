<?php

namespace App\Controller;

use App\Entity\Patient;
use App\Service\TestService;
use App\Service\PassationService;
use App\Service\BilanService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Form\PatientPasswordType;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use App\Service\SessionManagementService;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;

/**
 * Contrôleur d'authentification et dashboard pour les patients
 */
class PatientAuthController extends AbstractController
{
    public function __construct(
        private TestService $testService,
        private PassationService $passationService,
        private BilanService $bilanService,
        private RateLimiterFactory $loginLimiter,
        private RateLimiterFactory $passwordChangeLimiter,
        private SessionManagementService $sessionService,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Page de connexion patient
     */
    #[Route('/patient/login', name: 'patient_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request, RateLimiterFactory $loginAttemptsLimiter): Response
    {
        // Si déjà connecté, rediriger vers le dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('patient_dashboard');
        }

        // Rate limiting par IP
        $limiter = $loginAttemptsLimiter->create($request->getClientIp());
        
        // Vérifier si la limite est atteinte
        if (!$limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Trop de tentatives de connexion. Veuillez réessayer dans 15 minutes.');
            return $this->render('patient/login.html.twig', [
                'last_username' => '',
                'error' => null,
                'rate_limited' => true,
            ]);
        }

        // Récupérer l'erreur de connexion s'il y en a une
        $error = $authenticationUtils->getLastAuthenticationError();
        // Dernier email saisi par l'utilisateur
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('patient/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'rate_limited' => false,
        ]);
    }

    /**
     * Dashboard patient - Vue d'ensemble
     */
    #[Route('/patient/dashboard', name: 'patient_dashboard')]
    #[IsGranted('ROLE_PATIENT')]
    public function dashboard(): Response
    {
        /** @var Patient $patient */
        $patient = $this->getUser();

        // Récupérer les prescriptions du patient (version simplifiée)
        $prescriptions = $patient->getPrescriptions();

        // Statistiques basiques
        $stats = [
            'tests_prescrits' => count($prescriptions),
            'tests_en_cours' => 0, // TODO: à implémenter plus tard
            'bilans_disponibles' => 0 // TODO: à implémenter plus tard
        ];

        return $this->render('patient/dashboard.html.twig', [
            'patient' => $patient,
            'prescriptions' => $prescriptions,
            'passations_en_cours' => [], // TODO: à implémenter plus tard
            'bilans_recents' => [], // TODO: à implémenter plus tard
            'stats' => $stats
        ]);
    }

    /**
     * Liste des tests prescrits au patient
     */
    #[Route('/patient/tests', name: 'patient_tests')]
    #[IsGranted('ROLE_PATIENT')]
    public function tests(): Response
    {
        /** @var Patient $patient */
        $patient = $this->getUser();

        $prescriptions = $this->entityManager
            ->getRepository(\App\Entity\Prescription::class)
            ->findBy(['patient' => $patient], ['dateCreation' => 'DESC']);

        return $this->render('patient/tests.html.twig', [
            'prescriptions' => $prescriptions
        ]);
    }

    /**
     * Démarrer ou reprendre une passation de test
     */
    #[Route('/patient/test/{id}/start', name: 'patient_test_start')]
    #[IsGranted('ROLE_PATIENT')]
    public function startTest(\App\Entity\Prescription $prescription, Request $request): Response
    {
        /** @var Patient $patient */
        $patient = $this->getUser();

        // Vérifier que la prescription appartient au patient
        if ($prescription->getPatient() !== $patient) {
            throw $this->createAccessDeniedException('Cette prescription ne vous appartient pas');
        }

        // TODO: Vérifier que la prescription est active (méthode à implémenter)
        // if (!$prescription->isActive()) {
        //     $this->addFlash('error', 'Cette prescription n\'est plus active');
        //     return $this->redirectToRoute('patient_tests');
        // }

        // TODO: Vérifier le consentement RGPD (méthode à implémenter)
        // if (!$prescription->hasConsentementRGPD()) {
        //     return $this->redirectToRoute('patient_consent', ['id' => $prescription->getId()]);
        // }

        try {
            // Chercher une passation existante ou en créer une nouvelle
            $passation = $this->entityManager
                ->getRepository(\App\Entity\Passation::class)
                ->findLastByPrescription($prescription);

            if ($passation === null || $passation->isTerminee() || $passation->isAbandonee()) {
                // Créer une nouvelle passation
                $passation = $this->passationService->demarrerPassation($prescription, $patient->getDateNaissance(), $request);
            } elseif ($passation->isSuspendue()) {
                // Reprendre la passation suspendue
                $this->passationService->reprendrePassation($passation);
            }

            return $this->redirectToRoute('patient_test_passation', [
                'id' => $passation->getId()
            ]);

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du démarrage du test : ' . $e->getMessage());
            return $this->redirectToRoute('patient_tests');
        }
    }


    /**
     * Mes bilans - Consultation des résultats
     */
    #[Route('/patient/bilans', name: 'patient_bilans')]
    #[IsGranted('ROLE_PATIENT')]
    public function bilans(): Response
    {
        /** @var Patient $patient */
        $patient = $this->getUser();

        $bilans = $this->bilanService->getBilansPatient($patient);

        return $this->render('patient/bilans.html.twig', [
            'bilans' => $bilans
        ]);
    }

    /**
     * Consultation d'un bilan spécifique
     */
    #[Route('/patient/bilan/{id}', name: 'patient_bilan_view')]
    #[IsGranted('ROLE_PATIENT')]
    public function viewBilan(\App\Entity\Bilan $bilan): Response
    {
        /** @var Patient $patient */
        $patient = $this->getUser();

        // Vérifier que le bilan appartient au patient
        if ($bilan->getPrescription()->getPatient() !== $patient) {
            throw $this->createAccessDeniedException('Ce bilan ne vous appartient pas');
        }

        // Seuls les bilans validés peuvent être consultés par le patient
        if (!$bilan->isValide()) {
            $this->addFlash('info', 'Ce bilan n\'est pas encore disponible. Votre praticien doit le valider.');
            return $this->redirectToRoute('patient_bilans');
        }

        return $this->render('patient/bilan_view.html.twig', [
            'bilan' => $bilan
        ]);
    }

    /**
     * Changement de mot de passe patient
     */
    #[Route('/patient/change-password', name: 'patient_change_password')]
    #[IsGranted('ROLE_PATIENT')]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        /** @var Patient $patient */
        $patient = $this->getUser();

        // Rate limiting pour changement de mot de passe
        $limiter = $this->passwordChangeLimiter->create($request->getClientIp());
        
        if (!$limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Trop de tentatives de changement de mot de passe. Veuillez réessayer dans 30 minutes.');
            return $this->redirectToRoute('patient_dashboard');
        }

        $form = $this->createForm(PatientPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();
            $newPassword = $form->get('plainPassword')->getData();

            // Vérifier le mot de passe actuel
            if (!$passwordHasher->isPasswordValid($patient, $currentPassword)) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect');
            } else {
                // Changer le mot de passe
                $hashedPassword = $passwordHasher->hashPassword($patient, $newPassword);
                $patient->setPassword($hashedPassword);
                
                $this->entityManager->flush();
                
                // Invalider toutes les sessions après changement de mot de passe
                $this->sessionService->invalidateAllUserSessions($patient);
                
                $this->addFlash('success', 'Mot de passe modifié avec succès. Vous devez vous reconnecter.');
                
                return $this->redirectToRoute('patient_login');
            }
        }

        return $this->render('patient/change_password.html.twig', [
            'passwordForm' => $form->createView()
        ]);
    }

    /**
     * Déconnexion patient
     */
    #[Route('/patient/logout', name: 'patient_logout')]
    public function logout(): void
    {
        // Cette méthode peut rester vide,
        // elle sera interceptée par la configuration de sécurité
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
