<?php

namespace App\Service;

use App\Entity\Test;
use App\Entity\TestIDE;
use App\Entity\User;
use App\Entity\Patient;
use App\Entity\Prescription;
use App\Repository\TestIDERepository;
use App\Repository\PrescriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de gestion des tests orthophoniques
 */
class TestService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TestIDERepository $testIDERepository,
        private PrescriptionRepository $prescriptionRepository
    ) {
    }

    /**
     * Trouve les tests compatibles avec l'âge d'un patient
     */
    public function getTestsCompatiblesAge(Patient $patient): array
    {
        $ageEnMois = $patient->getAgeEnMois();
        
        return $this->testIDERepository->findCompatiblesAvecAge($ageEnMois);
    }

    /**
     * Prescrit un test à un patient
     */
    public function prescrireTest(
        User $praticien,
        Patient $patient,
        Test $test,
        ?string $instructions = null,
        ?\DateTimeInterface $dateLimite = null,
        int $priorite = 2
    ): Prescription {
        // Vérifier que le patient appartient au praticien
        if ($patient->getPraticien() !== $praticien) {
            throw new \InvalidArgumentException('Ce patient n\'appartient pas à ce praticien');
        }

        // Vérifier la compatibilité d'âge
        if ($test instanceof TestIDE) {
            $ageEnMois = $patient->getAgeEnMois();
            if ($ageEnMois < $test->getAgeMinMois() || $ageEnMois > $test->getAgeMaxMois()) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Ce test n\'est pas adapté à l\'âge du patient (%s). Âge requis: %d-%d mois.',
                        $patient->getAgeFormate(),
                        $test->getAgeMinMois(),
                        $test->getAgeMaxMois()
                    )
                );
            }
        }

        // Créer la prescription
        $prescription = new Prescription();
        $prescription->setPraticien($praticien);
        $prescription->setPatient($patient);
        $prescription->setTest($test);
        $prescription->setInstructions($instructions);
        $prescription->setDateLimite($dateLimite);
        $prescription->setPriorite($priorite);

        $this->entityManager->persist($prescription);
        $this->entityManager->flush();

        return $prescription;
    }

    /**
     * Prescrit plusieurs tests à un patient
     */
    public function prescrirePlusieursTests(
        User $praticien,
        Patient $patient,
        array $tests,
        ?string $instructions = null,
        ?\DateTimeInterface $dateLimite = null,
        int $priorite = 2
    ): array {
        $prescriptions = [];

        foreach ($tests as $test) {
            $prescriptions[] = $this->prescrireTest(
                $praticien,
                $patient,
                $test,
                $instructions,
                $dateLimite,
                $priorite
            );
        }

        return $prescriptions;
    }

    /**
     * Annule une prescription
     */
    public function annulerPrescription(Prescription $prescription, User $praticien): void
    {
        // Vérifier que la prescription appartient au praticien
        if ($prescription->getPraticien() !== $praticien) {
            throw new \InvalidArgumentException('Cette prescription n\'appartient pas à ce praticien');
        }

        // Vérifier que la prescription peut être annulée
        if ($prescription->getStatut() === Prescription::STATUT_VALIDE) {
            throw new \InvalidArgumentException('Une prescription validée ne peut pas être annulée');
        }

        $prescription->setStatut(Prescription::STATUT_ANNULE);
        $this->entityManager->flush();
    }

    /**
     * Valide une prescription terminée
     */
    public function validerPrescription(Prescription $prescription, User $praticien): void
    {
        // Vérifier que la prescription appartient au praticien
        if ($prescription->getPraticien() !== $praticien) {
            throw new \InvalidArgumentException('Cette prescription n\'appartient pas à ce praticien');
        }

        // Vérifier que la prescription peut être validée
        if (!$prescription->peutEtreValidee()) {
            throw new \InvalidArgumentException('Cette prescription ne peut pas être validée');
        }

        $prescription->setStatut(Prescription::STATUT_VALIDE);
        $this->entityManager->flush();
    }

    /**
     * Retourne les prescriptions en attente de validation pour un praticien
     */
    public function getPrescriptionsEnAttenteValidation(User $praticien): array
    {
        return $this->prescriptionRepository->findEnAttenteValidation($praticien);
    }

    /**
     * Retourne les prescriptions en retard
     */
    public function getPrescriptionsEnRetard(): array
    {
        return $this->prescriptionRepository->findEnRetard();
    }

    /**
     * Retourne les statistiques des prescriptions pour un praticien
     */
    public function getStatistiquesPrescriptions(User $praticien): array
    {
        $stats = $this->prescriptionRepository->getStatistiquesPraticien($praticien);
        
        // Ajouter les totaux et pourcentages
        $total = array_sum($stats);
        $statsAvecPourcentages = [];
        
        foreach (Prescription::STATUTS as $statut => $nom) {
            $nombre = $stats[$statut] ?? 0;
            $pourcentage = $total > 0 ? round(($nombre / $total) * 100, 1) : 0;
            
            $statsAvecPourcentages[$statut] = [
                'nombre' => $nombre,
                'pourcentage' => $pourcentage,
                'nom' => $nom
            ];
        }
        
        $statsAvecPourcentages['total'] = $total;
        
        return $statsAvecPourcentages;
    }

    /**
     * Vérifie si un patient peut passer un test (consentement RGPD, etc.)
     */
    public function peutPasserTest(Prescription $prescription): array
    {
        $erreurs = [];
        
        // Vérifier le consentement RGPD
        if (!$prescription->isConsentementRGPD()) {
            $erreurs[] = 'Le consentement RGPD n\'a pas été donné';
        }
        
        // Vérifier la date limite
        if ($prescription->isEnRetard()) {
            $erreurs[] = 'La date limite pour passer ce test est dépassée';
        }
        
        // Vérifier le statut
        if (!$prescription->peutEtreDemarree()) {
            $erreurs[] = 'Cette prescription ne peut pas être démarrée';
        }
        
        return [
            'peut_passer' => empty($erreurs),
            'erreurs' => $erreurs
        ];
    }

    /**
     * Retourne le dernier test IDE disponible
     */
    public function getDernierTestIDE(): ?TestIDE
    {
        return $this->testIDERepository->findDernierTest();
    }

    /**
     * Retourne tous les tests IDE actifs
     */
    public function getTestsIDEActifs(): array
    {
        return $this->testIDERepository->findBy(['actif' => true], ['version' => 'DESC']);
    }

    /**
     * Compte le nombre total de prescriptions actives pour un praticien
     */
    public function countPrescriptionsActives(User $praticien): int
    {
        return $this->prescriptionRepository->countByStatutForPraticien(
            $praticien,
            Prescription::STATUT_EN_ATTENTE
        ) + $this->prescriptionRepository->countByStatutForPraticien(
            $praticien,
            Prescription::STATUT_EN_COURS
        );
    }

    /**
     * Retourne les prescriptions d'un patient triées par priorité
     */
    public function getPrescriptionsPatient(Patient $patient): array
    {
        return $this->prescriptionRepository->findActivesForPatient($patient);
    }
}
