<?php

namespace App\Service;

use App\Entity\Passation;
use App\Entity\Bilan;
use App\Entity\User;
use App\Repository\BilanRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de gestion des bilans orthophoniques
 */
class BilanService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BilanRepository $bilanRepository,
        private ScoreCalculatorService $scoreCalculator
    ) {
    }

    /**
     * Génère automatiquement un bilan à partir d'une passation terminée
     */
    public function genererBilanAutomatique(Passation $passation): Bilan
    {
        if (!$passation->isTerminee()) {
            throw new \InvalidArgumentException('La passation doit être terminée pour générer un bilan');
        }

        $prescription = $passation->getPrescription();

        // Vérifier s'il n'y a pas déjà un bilan pour cette prescription
        $bilanExistant = $this->bilanRepository->findLastByPrescription($prescription);
        if ($bilanExistant !== null) {
            // Créer une nouvelle version
            $version = $bilanExistant->getVersion() + 1;
        } else {
            $version = 1;
        }

        // Calculer les scores
        $scores = $this->scoreCalculator->calculerScoresIDE($passation);
        
        // Valider la cohérence des réponses
        $validation = $this->scoreCalculator->validerCoherenceReponses($passation);

        // Créer le bilan
        $bilan = new Bilan();
        $bilan->setPrescription($prescription);
        $bilan->setVersion($version);
        $bilan->setScoresDetailles($scores);

        // Score DG et niveau de risque global
        $scoreDG = $scores['DG']['score'] ?? 0;
        $niveauRisqueGlobal = $this->determinerNiveauRisqueGlobal($scores);
        
        $bilan->setScoreDG($scoreDG);
        $bilan->setNiveauRisqueGlobal($niveauRisqueGlobal);

        // Âge de développement estimé (basé sur le score DG)
        $ageDeveloppement = $this->estimerAgeDeveloppement($scoreDG, $passation->getAgeChronologiqueMois());
        $bilan->setAgeDeveloppementMois($ageDeveloppement);

        // Génération de l'interprétation automatique
        $interpretation = $this->scoreCalculator->genererInterpretation(
            $scores, 
            $passation->getAgeChronologiqueMois()
        );
        $bilan->setInterpretationAutomatique($interpretation);

        // Identifier les points forts et de vigilance
        $this->identifierPointsFortsEtVigilance($bilan, $scores);

        // Générer le profil graphique
        $profilGraphique = $this->genererProfilGraphique($scores);
        $bilan->setProfilGraphique($profilGraphique);

        // Ajouter les avertissements de validation s'il y en a
        if (!empty($validation['avertissements'])) {
            $interpretationAvecAvertissements = $interpretation . "\n\nAVERTISSEMENTS :\n" . 
                implode("\n", array_map(fn($a) => "• $a", $validation['avertissements']));
            $bilan->setInterpretationAutomatique($interpretationAvecAvertissements);
        }

        $this->entityManager->persist($bilan);
        $this->entityManager->flush();

        return $bilan;
    }

    /**
     * Valide un bilan par un praticien
     */
    public function validerBilan(Bilan $bilan, User $praticien, ?string $commentaires = null): void
    {
        // Vérifier que le bilan appartient au praticien
        if ($bilan->getPrescription()->getPraticien() !== $praticien) {
            throw new \InvalidArgumentException('Ce bilan n\'appartient pas à ce praticien');
        }

        // Vérifier que le bilan peut être validé
        if (!$bilan->peutEtreModifie()) {
            throw new \InvalidArgumentException('Ce bilan ne peut plus être modifié');
        }

        $bilan->setStatut(Bilan::STATUT_VALIDE);
        
        if ($commentaires !== null) {
            $bilan->setCommentairesPraticien($commentaires);
        }

        $this->entityManager->flush();
    }

    /**
     * Finalise un bilan (plus de modifications possibles)
     */
    public function finaliserBilan(Bilan $bilan, User $praticien): void
    {
        // Vérifier que le bilan appartient au praticien
        if ($bilan->getPrescription()->getPraticien() !== $praticien) {
            throw new \InvalidArgumentException('Ce bilan n\'appartient pas à ce praticien');
        }

        if (!$bilan->isValide()) {
            throw new \InvalidArgumentException('Le bilan doit être validé avant d\'être finalisé');
        }

        $bilan->setStatut(Bilan::STATUT_FINALISE);
        $this->entityManager->flush();
    }

    /**
     * Ajoute des commentaires praticien à un bilan
     */
    public function ajouterCommentaires(
        Bilan $bilan, 
        User $praticien, 
        string $commentaires,
        ?string $recommandations = null
    ): void {
        // Vérifier que le bilan appartient au praticien
        if ($bilan->getPrescription()->getPraticien() !== $praticien) {
            throw new \InvalidArgumentException('Ce bilan n\'appartient pas à ce praticien');
        }

        if (!$bilan->peutEtreModifie()) {
            throw new \InvalidArgumentException('Ce bilan ne peut plus être modifié');
        }

        $bilan->setCommentairesPraticien($commentaires);
        
        if ($recommandations !== null) {
            $bilan->setRecommandations($recommandations);
        }

        $bilan->setStatut(Bilan::STATUT_EN_REVISION);
        $this->entityManager->flush();
    }

    /**
     * Retourne les bilans en attente de validation pour un praticien
     */
    public function getBilansEnAttenteValidation(User $praticien): array
    {
        return $this->bilanRepository->findEnAttenteValidation($praticien);
    }

    /**
     * Retourne les bilans d'un patient
     */
    public function getBilansPatient(\App\Entity\Patient $patient): array
    {
        return $this->bilanRepository->findByPatient($patient);
    }

    /**
     * Retourne les statistiques des bilans pour un praticien
     */
    public function getStatistiquesBilans(User $praticien): array
    {
        $bilans = $this->bilanRepository->findByPraticien($praticien);
        
        $stats = [
            'total' => count($bilans),
            'par_statut' => [],
            'par_niveau_risque' => [],
            'score_dg_moyen' => 0
        ];

        $sommeDG = 0;
        $nombreAvecDG = 0;

        foreach ($bilans as $bilan) {
            // Statistiques par statut
            $statut = $bilan->getStatut();
            $stats['par_statut'][$statut] = ($stats['par_statut'][$statut] ?? 0) + 1;

            // Statistiques par niveau de risque
            $niveauRisque = $bilan->getNiveauRisqueGlobal();
            if ($niveauRisque) {
                $stats['par_niveau_risque'][$niveauRisque] = ($stats['par_niveau_risque'][$niveauRisque] ?? 0) + 1;
            }

            // Score DG moyen
            if ($bilan->getScoreDG() !== null) {
                $sommeDG += $bilan->getScoreDG();
                $nombreAvecDG++;
            }
        }

        if ($nombreAvecDG > 0) {
            $stats['score_dg_moyen'] = round($sommeDG / $nombreAvecDG, 1);
        }

        return $stats;
    }

    /**
     * Détermine le niveau de risque global à partir des scores
     */
    private function determinerNiveauRisqueGlobal(array $scores): string
    {
        // Le niveau de risque global est basé sur le score DG
        $scoreDG = $scores['DG'] ?? null;
        
        if ($scoreDG === null) {
            return Bilan::RISQUE_MODERE;
        }

        return $scoreDG['niveau_risque'] ?? Bilan::RISQUE_MODERE;
    }

    /**
     * Estime l'âge de développement basé sur le score DG
     */
    private function estimerAgeDeveloppement(int $scoreDG, int $ageChronologiqueMois): int
    {
        // Formule simplifiée : âge de développement = score DG
        // Dans la réalité, cela devrait être basé sur des normes statistiques
        return min($scoreDG, $ageChronologiqueMois + 6); // Maximum 6 mois d'avance
    }

    /**
     * Identifie les points forts et de vigilance
     */
    private function identifierPointsFortsEtVigilance(Bilan $bilan, array $scores): void
    {
        foreach ($scores as $domaine => $donnees) {
            if ($domaine === 'DG') continue;

            $niveauRisque = $donnees['niveau_risque'];
            $nomDomaine = \App\Entity\TestIDE::DOMAINES[$domaine] ?? $domaine;

            if ($niveauRisque === 'faible') {
                $bilan->ajouterPointFort($domaine, "Développement adapté à l'âge en {$nomDomaine}");
            } elseif (in_array($niveauRisque, ['haut', 'tres_haut'])) {
                $description = $niveauRisque === 'tres_haut' ? 
                    "Très haut risque identifié en {$nomDomaine}" : 
                    "Haut risque identifié en {$nomDomaine}";
                $bilan->ajouterPointVigilance($domaine, $description);
            }
        }
    }

    /**
     * Génère le profil graphique pour les scores
     */
    private function genererProfilGraphique(array $scores): array
    {
        $profil = [];

        foreach ($scores as $domaine => $donnees) {
            if ($domaine === 'DG') continue;

            $profil[] = [
                'domaine' => $domaine,
                'nom' => \App\Entity\TestIDE::DOMAINES[$domaine] ?? $domaine,
                'score' => $donnees['score'],
                'seuil_hr' => $donnees['seuil_hr'],
                'seuil_thr' => $donnees['seuil_thr'],
                'niveau_risque' => $donnees['niveau_risque'],
                'couleur' => $this->getCouleurNiveauRisque($donnees['niveau_risque'])
            ];
        }

        return $profil;
    }

    /**
     * Retourne la couleur associée à un niveau de risque
     */
    private function getCouleurNiveauRisque(string $niveau): string
    {
        return match($niveau) {
            'faible' => '#10B981',      // Vert
            'modere' => '#F59E0B',      // Orange
            'haut' => '#EF4444',        // Rouge
            'tres_haut' => '#DC2626',   // Rouge foncé
            default => '#6B7280'        // Gris
        };
    }

    /**
     * Génère un résumé exécutif du bilan
     */
    public function genererResumeExecutif(Bilan $bilan): string
    {
        $prescription = $bilan->getPrescription();
        $patient = $prescription->getPatient();
        $test = $prescription->getTest();

        $resume = [];
        $resume[] = "RÉSUMÉ EXÉCUTIF - BILAN ORTHOPHONIQUE";
        $resume[] = str_repeat("=", 50);
        $resume[] = "";
        $resume[] = "Patient : " . $patient->getNomComplet();
        $resume[] = "Âge : " . $patient->getAgeFormate();
        $resume[] = "Test : " . $test->getNom();
        $resume[] = "Date du bilan : " . $bilan->getDateGeneration()->format('d/m/Y');
        $resume[] = "";
        $resume[] = "RÉSULTATS PRINCIPAUX :";
        $resume[] = "• Score DG : " . $bilan->getScoreDG();
        $resume[] = "• Niveau de risque : " . $bilan->getNomNiveauRisque();
        $resume[] = "• Âge de développement estimé : " . $bilan->getAgeDeveloppementFormate();
        $resume[] = "";

        $pointsForts = $bilan->getPointsForts();
        if (!empty($pointsForts)) {
            $resume[] = "POINTS FORTS (" . count($pointsForts) . ") :";
            foreach (array_slice($pointsForts, 0, 3) as $point) {
                $resume[] = "• " . $point['description'];
            }
            $resume[] = "";
        }

        $pointsVigilance = $bilan->getPointsVigilance();
        if (!empty($pointsVigilance)) {
            $resume[] = "POINTS DE VIGILANCE (" . count($pointsVigilance) . ") :";
            foreach (array_slice($pointsVigilance, 0, 3) as $point) {
                $resume[] = "• " . $point['description'];
            }
            $resume[] = "";
        }

        if ($bilan->getCommentairesPraticien()) {
            $resume[] = "COMMENTAIRES DU PRATICIEN :";
            $resume[] = $bilan->getCommentairesPraticien();
            $resume[] = "";
        }

        return implode("\n", $resume);
    }

    /**
     * Compare deux bilans d'un même patient
     */
    public function comparerBilans(Bilan $bilan1, Bilan $bilan2): array
    {
        if ($bilan1->getPrescription()->getPatient() !== $bilan2->getPrescription()->getPatient()) {
            throw new \InvalidArgumentException('Les bilans doivent concerner le même patient');
        }

        $scores1 = $bilan1->getScoresDetailles();
        $scores2 = $bilan2->getScoresDetailles();

        $comparaison = [
            'evolution_dg' => ($scores2['DG']['score'] ?? 0) - ($scores1['DG']['score'] ?? 0),
            'evolution_par_domaine' => [],
            'ameliorations' => [],
            'deteriorations' => []
        ];

        foreach ($scores1 as $domaine => $donnees1) {
            if ($domaine === 'DG' || !isset($scores2[$domaine])) continue;

            $score1 = $donnees1['score'];
            $score2 = $scores2[$domaine]['score'];
            $evolution = $score2 - $score1;

            $comparaison['evolution_par_domaine'][$domaine] = [
                'score_initial' => $score1,
                'score_final' => $score2,
                'evolution' => $evolution,
                'nom_domaine' => \App\Entity\TestIDE::DOMAINES[$domaine] ?? $domaine
            ];

            if ($evolution > 2) {
                $comparaison['ameliorations'][] = $domaine;
            } elseif ($evolution < -2) {
                $comparaison['deteriorations'][] = $domaine;
            }
        }

        return $comparaison;
    }
}
