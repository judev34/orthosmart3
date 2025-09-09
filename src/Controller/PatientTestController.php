<?php

namespace App\Controller;

use App\Entity\Passation;
use App\Entity\Patient;
use App\Service\PassationService;
use App\Service\ScoreCalculatorService;
use App\Service\BilanService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur pour la passation des tests par les patients
 */
#[Route('/patient/passation')]
#[IsGranted('ROLE_PATIENT')]
class PatientTestController extends AbstractController
{
    public function __construct(
        private PassationService $passationService,
        private ScoreCalculatorService $scoreCalculator,
        private BilanService $bilanService,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Interface de passation d'un test
     */
    #[Route('/{id}', name: 'patient_test_passation')]
    public function passation(Passation $passation): Response
    {
        /** @var Patient $patient */
        $patient = $this->getUser();

        // Vérifier que la passation appartient au patient
        if ($passation->getPrescription()->getPatient() !== $patient) {
            throw $this->createAccessDeniedException('Cette passation ne vous appartient pas');
        }

        // Vérifier que la passation peut être continuée
        if ($passation->isTerminee() || $passation->isAbandonee()) {
            $this->addFlash('info', 'Cette passation est déjà terminée');
            return $this->redirectToRoute('patient_tests');
        }

        $prescription = $passation->getPrescription();
        $test = $prescription->getTest();

        // Récupérer les items du test selon l'âge du patient
        $items = $this->entityManager
            ->getRepository(\App\Entity\ItemIDE::class)
            ->findApplicableItems($test, $passation->getAgeChronologiqueMois());

        // Organiser les items par parties
        $itemsParParties = [];
        foreach ($items as $item) {
            $partie = $item->getPartie();
            if (!isset($itemsParParties[$partie])) {
                $itemsParParties[$partie] = [];
            }
            $itemsParParties[$partie][] = $item;
        }

        // Récupérer les réponses déjà données
        $reponses = $passation->getReponses();

        return $this->render('patient/passation/test.html.twig', [
            'passation' => $passation,
            'prescription' => $prescription,
            'test' => $test,
            'items_par_parties' => $itemsParParties,
            'reponses' => $reponses,
            'progression' => $passation->getProgression()
        ]);
    }

    /**
     * Sauvegarde d'une réponse (AJAX)
     */
    #[Route('/{id}/save-response', name: 'patient_save_response', methods: ['POST'])]
    public function saveResponse(Passation $passation, Request $request): JsonResponse
    {
        /** @var Patient $patient */
        $patient = $this->getUser();

        // Vérifier que la passation appartient au patient
        if ($passation->getPrescription()->getPatient() !== $patient) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }

        // Vérifier que la passation peut être modifiée
        if ($passation->isTerminee() || $passation->isAbandonee()) {
            return new JsonResponse(['error' => 'Cette passation ne peut plus être modifiée'], 400);
        }

        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['item_id']) || !isset($data['reponse'])) {
                return new JsonResponse(['error' => 'Données manquantes'], 400);
            }

            $itemId = (int) $data['item_id'];
            $reponse = $data['reponse'];

            // Valider la réponse (0, 1, 2 pour IDE)
            if (!in_array($reponse, [0, 1, 2], true)) {
                return new JsonResponse(['error' => 'Réponse invalide'], 400);
            }

            // Sauvegarder la réponse
            $this->passationService->sauvegarderReponse($passation, $itemId, $reponse);

            // Calculer la nouvelle progression
            $progression = $passation->getProgression();

            return new JsonResponse([
                'success' => true,
                'progression' => $progression,
                'message' => 'Réponse sauvegardée'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sauvegarde multiple de réponses (AJAX)
     */
    #[Route('/{id}/save-responses', name: 'patient_save_responses', methods: ['POST'])]
    public function saveResponses(Passation $passation, Request $request): JsonResponse
    {
        /** @var Patient $patient */
        $patient = $this->getUser();

        // Vérifier que la passation appartient au patient
        if ($passation->getPrescription()->getPatient() !== $patient) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }

        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['reponses']) || !is_array($data['reponses'])) {
                return new JsonResponse(['error' => 'Format de données invalide'], 400);
            }

            // Valider et sauvegarder toutes les réponses
            $reponsesValidees = [];
            foreach ($data['reponses'] as $itemId => $reponse) {
                if (in_array($reponse, [0, 1, 2], true)) {
                    $reponsesValidees[(int) $itemId] = $reponse;
                }
            }

            $this->passationService->sauvegarderReponses($passation, $reponsesValidees);

            return new JsonResponse([
                'success' => true,
                'progression' => $passation->getProgression(),
                'message' => count($reponsesValidees) . ' réponses sauvegardées'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Suspension de la passation
     */
    #[Route('/{id}/suspend', name: 'patient_suspend_test', methods: ['POST'])]
    public function suspendTest(Passation $passation): Response
    {
        /** @var Patient $patient */
        $patient = $this->getUser();

        // Vérifier que la passation appartient au patient
        if ($passation->getPrescription()->getPatient() !== $patient) {
            throw $this->createAccessDeniedException('Cette passation ne vous appartient pas');
        }

        try {
            $this->passationService->suspendrePassation($passation);
            $this->addFlash('success', 'Test suspendu. Vous pourrez le reprendre plus tard.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suspension : ' . $e->getMessage());
        }

        return $this->redirectToRoute('patient_dashboard');
    }

    /**
     * Finalisation de la passation
     */
    #[Route('/{id}/finish', name: 'patient_finish_test', methods: ['POST'])]
    public function finishTest(Passation $passation, Request $request): Response
    {
        /** @var Patient $patient */
        $patient = $this->getUser();

        // Vérifier que la passation appartient au patient
        if ($passation->getPrescription()->getPatient() !== $patient) {
            throw $this->createAccessDeniedException('Cette passation ne vous appartient pas');
        }

        try {
            // Sauvegarder les dernières réponses si présentes
            $data = json_decode($request->getContent(), true);
            if (isset($data['reponses']) && is_array($data['reponses'])) {
                $reponsesValidees = [];
                foreach ($data['reponses'] as $itemId => $reponse) {
                    if (in_array($reponse, [0, 1, 2], true)) {
                        $reponsesValidees[(int) $itemId] = $reponse;
                    }
                }
                if (!empty($reponsesValidees)) {
                    $this->passationService->sauvegarderReponses($passation, $reponsesValidees);
                }
            }

            // Terminer la passation
            $this->passationService->terminerPassation($passation);

            // Générer automatiquement le bilan
            $bilan = $this->bilanService->genererBilanAutomatique($passation);

            $this->addFlash('success', 'Test terminé avec succès ! Le bilan a été généré et sera disponible après validation par votre praticien.');

            return new JsonResponse([
                'success' => true,
                'redirect' => $this->generateUrl('patient_dashboard')
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Abandon de la passation
     */
    #[Route('/{id}/abandon', name: 'patient_abandon_test', methods: ['POST'])]
    public function abandonTest(Passation $passation): Response
    {
        /** @var Patient $patient */
        $patient = $this->getUser();

        // Vérifier que la passation appartient au patient
        if ($passation->getPrescription()->getPatient() !== $patient) {
            throw $this->createAccessDeniedException('Cette passation ne vous appartient pas');
        }

        try {
            $this->passationService->abandonnerPassation($passation);
            $this->addFlash('info', 'Test abandonné. Vous pourrez recommencer une nouvelle passation si nécessaire.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'abandon : ' . $e->getMessage());
        }

        return $this->redirectToRoute('patient_tests');
    }

    /**
     * Récupération du statut de la passation (AJAX)
     */
    #[Route('/{id}/status', name: 'patient_test_status', methods: ['GET'])]
    public function getStatus(Passation $passation): JsonResponse
    {
        /** @var Patient $patient */
        $patient = $this->getUser();

        // Vérifier que la passation appartient au patient
        if ($passation->getPrescription()->getPatient() !== $patient) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }

        return new JsonResponse([
            'statut' => $passation->getStatut(),
            'progression' => $passation->getProgression(),
            'nb_reponses' => count($passation->getReponses()),
            'duree_minutes' => $passation->getDureeMinutes(),
            'derniere_activite' => $passation->getDerniereActivite()?->format('c')
        ]);
    }

    /**
     * Aperçu des résultats avant finalisation
     */
    #[Route('/{id}/preview', name: 'patient_test_preview')]
    public function previewResults(Passation $passation): Response
    {
        /** @var Patient $patient */
        $patient = $this->getUser();

        // Vérifier que la passation appartient au patient
        if ($passation->getPrescription()->getPatient() !== $patient) {
            throw $this->createAccessDeniedException('Cette passation ne vous appartient pas');
        }

        // Calculer un aperçu des scores (sans sauvegarder)
        try {
            $scores = $this->scoreCalculator->calculerScoresIDE($passation);
            $interpretation = $this->scoreCalculator->genererInterpretation(
                $scores, 
                $passation->getAgeChronologiqueMois()
            );

            return $this->render('patient/passation/preview.html.twig', [
                'passation' => $passation,
                'scores' => $scores,
                'interpretation' => $interpretation
            ]);

        } catch (\Exception $e) {
            $this->addFlash('error', 'Impossible de calculer l\'aperçu : ' . $e->getMessage());
            return $this->redirectToRoute('patient_test_passation', ['id' => $passation->getId()]);
        }
    }
}
