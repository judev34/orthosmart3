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

/**
 * Contrôleur d'authentification et dashboard pour les patients
 */
class PatientAuthController extends AbstractController
{
    public function __construct(
        private TestService $testService,
        private PassationService $passationService,
        private BilanService $bilanService,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Page de connexion patient
     */
    #[Route('/patient/login', name: 'patient_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si déjà connecté, rediriger vers le dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('patient_dashboard');
        }

        // Récupérer l'erreur de connexion s'il y en a une
        $error = $authenticationUtils->getLastAuthenticationError();
        // Dernier email saisi par l'utilisateur
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('patient/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
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
    public function startTest(\App\Entity\Prescription $prescription): Response
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
                $passation = $this->passationService->demarrerPassation($prescription);
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
     * Page de consentement RGPD
     */
    #[Route('/patient/consent/{id}', name: 'patient_consent')]
    #[IsGranted('ROLE_PATIENT')]
    public function consent(\App\Entity\Prescription $prescription, Request $request): Response
    {
        /** @var Patient $patient */
        $patient = $this->getUser();

        // Vérifier que la prescription appartient au patient
        if ($prescription->getPatient() !== $patient) {
            throw $this->createAccessDeniedException('Cette prescription ne vous appartient pas');
        }

        if ($request->isMethod('POST')) {
            $consentement = $request->request->get('consentement');
            
            if ($consentement === 'accept') {
                $prescription->setConsentementRGPD(true);
                $prescription->setDateConsentement(new \DateTimeImmutable());
                
                $this->entityManager->flush();
                
                $this->addFlash('success', 'Consentement enregistré. Vous pouvez maintenant commencer le test.');
                
                return $this->redirectToRoute('patient_test_start', ['id' => $prescription->getId()]);
            } else {
                $this->addFlash('error', 'Le consentement est requis pour passer le test.');
            }
        }

        return $this->render('patient/consent.html.twig', [
            'prescription' => $prescription
        ]);
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
        if (!$bilan->isValide() && !$bilan->isFinalise()) {
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

        if ($request->isMethod('POST')) {
            $currentPassword = $request->request->get('current_password');
            $newPassword = $request->request->get('new_password');
            $confirmPassword = $request->request->get('confirm_password');

            // Vérifier le mot de passe actuel
            if (!$passwordHasher->isPasswordValid($patient, $currentPassword)) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect');
            } elseif ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les nouveaux mots de passe ne correspondent pas');
            } elseif (strlen($newPassword) < 6) {
                $this->addFlash('error', 'Le nouveau mot de passe doit contenir au moins 6 caractères');
            } else {
                // Changer le mot de passe
                $hashedPassword = $passwordHasher->hashPassword($patient, $newPassword);
                $patient->setPassword($hashedPassword);
                
                $this->entityManager->flush();
                
                $this->addFlash('success', 'Mot de passe modifié avec succès');
                
                return $this->redirectToRoute('patient_dashboard');
            }
        }

        return $this->render('patient/change_password.html.twig');
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
