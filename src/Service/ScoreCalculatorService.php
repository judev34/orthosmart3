<?php

namespace App\Service;

use App\Entity\Passation;
use App\Entity\TestIDE;
use App\Entity\ItemIDE;
use App\Repository\ItemIDERepository;

/**
 * Service de calcul des scores pour les tests orthophoniques
 */
class ScoreCalculatorService
{
    public function __construct(
        private ItemIDERepository $itemIDERepository
    ) {
    }

    /**
     * Calcule tous les scores pour une passation IDE
     */
    public function calculerScoresIDE(Passation $passation): array
    {
        $prescription = $passation->getPrescription();
        $test = $prescription->getTest();

        if (!$test instanceof TestIDE) {
            throw new \InvalidArgumentException('Ce service ne peut calculer que les scores des tests IDE');
        }

        $reponses = $passation->getReponses();
        if (empty($reponses)) {
            throw new \InvalidArgumentException('Aucune réponse trouvée pour cette passation');
        }

        // Récupérer tous les items du test
        $items = $this->itemIDERepository->findBy(['testIDE' => $test, 'actif' => true]);

        // Initialiser les scores par domaine et partie
        $scoresByDomain = $this->initialiserScores();

        // Calculer les scores
        foreach ($items as $item) {
            $itemId = $item->getIdentifiant();
            $reponse = $reponses[$itemId] ?? null;

            if ($reponse === 'oui') {
                $domaine = $item->getDomaine();
                $partie = $item->getPartie();

                // Incrémenter le score du domaine pour cette partie
                if (isset($scoresByDomain[$domaine][$partie])) {
                    $scoresByDomain[$domaine][$partie]++;
                }

                // Si l'item compte pour le DG, l'incrémenter aussi
                if ($item->isCompteDG()) {
                    $scoresByDomain['DG'][$partie]++;
                }
            }
        }

        // Calculer les totaux par domaine
        $scoresFinaux = $this->calculerTotaux($scoresByDomain);

        // Calculer les seuils de risque
        $ageChronologiqueMois = $passation->getAgeChronologiqueMois();
        $seuils = $test->calculerSeuilsRisque($ageChronologiqueMois);

        // Déterminer les niveaux de risque par domaine
        $scoresAvecRisque = $this->determinerNiveauxRisque($scoresFinaux, $seuils, $ageChronologiqueMois);

        return $scoresAvecRisque;
    }

    /**
     * Initialise la structure des scores selon la grille IDE
     */
    private function initialiserScores(): array
    {
        $domaines = TestIDE::DOMAINES;
        $parties = array_keys(TestIDE::PARTIES);
        
        $scores = [];
        
        foreach (array_keys($domaines) as $domaine) {
            $scores[$domaine] = [];
            foreach ($parties as $partie) {
                $scores[$domaine][$partie] = 0;
            }
        }

        return $scores;
    }

    /**
     * Calcule les totaux par domaine
     */
    private function calculerTotaux(array $scoresByDomain): array
    {
        $totaux = [];

        foreach ($scoresByDomain as $domaine => $scoresByPartie) {
            $total = array_sum($scoresByPartie);
            
            $totaux[$domaine] = [
                'parties' => $scoresByPartie,
                'total' => $total
            ];
        }

        return $totaux;
    }

    /**
     * Détermine les niveaux de risque selon les seuils IDE
     */
    private function determinerNiveauxRisque(array $scores, array $seuils, int $ageChronologiqueMois): array
    {
        $scoresAvecRisque = [];

        foreach ($scores as $domaine => $donnees) {
            $score = $donnees['total'];
            $niveauRisque = $this->calculerNiveauRisque($score, $ageChronologiqueMois, $seuils);

            $scoresAvecRisque[$domaine] = [
                'score' => $score,
                'parties' => $donnees['parties'],
                'seuil_hr' => $seuils['haut_risque'],
                'seuil_thr' => $seuils['tres_haut_risque'],
                'niveau_risque' => $niveauRisque,
                'age_chronologique' => $ageChronologiqueMois
            ];
        }

        return $scoresAvecRisque;
    }

    /**
     * Calcule le niveau de risque pour un score donné
     */
    private function calculerNiveauRisque(int $score, int $ageChronologiqueMois, array $seuils): string
    {
        $seuilTHR = $seuils['tres_haut_risque'];
        $seuilHR = $seuils['haut_risque'];

        if ($score <= $seuilTHR) {
            return 'tres_haut';
        } elseif ($score <= $seuilHR) {
            return 'haut';
        } elseif ($score < $ageChronologiqueMois * 0.95) {
            return 'modere';
        } else {
            return 'faible';
        }
    }

    /**
     * Génère l'interprétation automatique des résultats
     */
    public function genererInterpretation(array $scores, int $ageChronologiqueMois): string
    {
        $interpretation = [];
        
        // En-tête
        $ageFormate = $this->formaterAge($ageChronologiqueMois);
        $interpretation[] = "INTERPRÉTATION DES RÉSULTATS - TEST IDE";
        $interpretation[] = "Âge chronologique de l'enfant : {$ageFormate}";
        $interpretation[] = "";

        // Score DG global
        $scoreDG = $scores['DG']['score'] ?? 0;
        $niveauRisqueDG = $scores['DG']['niveau_risque'] ?? 'inconnu';
        
        $interpretation[] = "SCORE DE DÉVELOPPEMENT GÉNÉRAL (DG) : {$scoreDG}";
        $interpretation[] = "Niveau de risque global : " . $this->getNomNiveauRisque($niveauRisqueDG);
        $interpretation[] = "";

        // Analyse par domaine
        $interpretation[] = "ANALYSE PAR DOMAINE :";
        
        $domainesOrdonnes = ['SO', 'AU', 'MG', 'MF', 'LEX', 'LCO', 'LE', 'NBRE'];
        
        foreach ($domainesOrdonnes as $domaine) {
            if (!isset($scores[$domaine]) || $domaine === 'DG') {
                continue;
            }

            $donnees = $scores[$domaine];
            $nomDomaine = TestIDE::DOMAINES[$domaine];
            $score = $donnees['score'];
            $niveauRisque = $donnees['niveau_risque'];

            $interpretation[] = "• {$nomDomaine} : {$score} points - " . $this->getNomNiveauRisque($niveauRisque);
        }

        $interpretation[] = "";

        // Points forts et vigilance
        $pointsForts = $this->identifierPointsForts($scores);
        $pointsVigilance = $this->identifierPointsVigilance($scores);

        if (!empty($pointsForts)) {
            $interpretation[] = "POINTS FORTS IDENTIFIÉS :";
            foreach ($pointsForts as $point) {
                $interpretation[] = "• {$point}";
            }
            $interpretation[] = "";
        }

        if (!empty($pointsVigilance)) {
            $interpretation[] = "POINTS DE VIGILANCE :";
            foreach ($pointsVigilance as $point) {
                $interpretation[] = "• {$point}";
            }
            $interpretation[] = "";
        }

        // Recommandations générales
        $interpretation[] = "RECOMMANDATIONS :";
        $interpretation[] = $this->genererRecommandations($niveauRisqueDG, $pointsVigilance);

        return implode("\n", $interpretation);
    }

    /**
     * Identifie les points forts (domaines avec faible risque)
     */
    private function identifierPointsForts(array $scores): array
    {
        $pointsForts = [];

        foreach ($scores as $domaine => $donnees) {
            if ($domaine === 'DG') continue;

            if ($donnees['niveau_risque'] === 'faible') {
                $nomDomaine = TestIDE::DOMAINES[$domaine];
                $pointsForts[] = "{$nomDomaine} : développement adapté à l'âge";
            }
        }

        return $pointsForts;
    }

    /**
     * Identifie les points de vigilance (domaines à risque)
     */
    private function identifierPointsVigilance(array $scores): array
    {
        $pointsVigilance = [];

        foreach ($scores as $domaine => $donnees) {
            if ($domaine === 'DG') continue;

            $niveauRisque = $donnees['niveau_risque'];
            if (in_array($niveauRisque, ['haut', 'tres_haut'])) {
                $nomDomaine = TestIDE::DOMAINES[$domaine];
                $pointsVigilance[] = "{$nomDomaine} : " . $this->getNomNiveauRisque($niveauRisque);
            }
        }

        return $pointsVigilance;
    }

    /**
     * Génère des recommandations selon le niveau de risque
     */
    private function genererRecommandations(string $niveauRisqueGlobal, array $pointsVigilance): string
    {
        switch ($niveauRisqueGlobal) {
            case 'tres_haut':
                return "Le niveau de développement global indique un très haut risque. Une évaluation orthophonique approfondie est fortement recommandée, ainsi qu'une prise en charge précoce.";
                
            case 'haut':
                return "Le niveau de développement global indique un haut risque. Une surveillance étroite et une évaluation orthophonique sont recommandées.";
                
            case 'modere':
                return "Le développement global présente quelques points de vigilance. Un suivi régulier et des activités de stimulation ciblées sont conseillés.";
                
            case 'faible':
                return "Le développement global est adapté à l'âge. Continuer à stimuler l'enfant dans ses apprentissages quotidiens.";
                
            default:
                return "Évaluation complémentaire recommandée pour préciser le niveau de développement.";
        }
    }

    /**
     * Retourne le nom complet d'un niveau de risque
     */
    private function getNomNiveauRisque(string $niveau): string
    {
        return match($niveau) {
            'faible' => 'Faible risque',
            'modere' => 'Risque modéré',
            'haut' => 'Haut risque (HR)',
            'tres_haut' => 'Très haut risque (THR)',
            default => 'Niveau indéterminé'
        };
    }

    /**
     * Formate un âge en mois
     */
    private function formaterAge(int $mois): string
    {
        $annees = intval($mois / 12);
        $moisRestants = $mois % 12;

        if ($annees > 0) {
            return sprintf('%d an%s %d mois', $annees, $annees > 1 ? 's' : '', $moisRestants);
        }

        return sprintf('%d mois', $mois);
    }

    /**
     * Valide la cohérence des réponses
     */
    public function validerCoherenceReponses(Passation $passation): array
    {
        $erreurs = [];
        $avertissements = [];

        $reponses = $passation->getReponses();
        $nombreOui = $passation->getNombreReponsesOui();
        $nombreNon = $passation->getNombreReponsesNon();
        $total = $nombreOui + $nombreNon;

        // Vérifier le ratio oui/non
        if ($total > 0) {
            $ratioOui = $nombreOui / $total;
            
            if ($ratioOui > 0.95) {
                $avertissements[] = "Très peu de réponses 'non' ({$nombreNon}). Vérifier la compréhension des questions.";
            } elseif ($ratioOui < 0.05) {
                $avertissements[] = "Très peu de réponses 'oui' ({$nombreOui}). Vérifier l'âge de l'enfant ou la compréhension des questions.";
            }
        }

        // Vérifier la complétude
        $prescription = $passation->getPrescription();
        $test = $prescription->getTest();
        
        if ($test instanceof TestIDE) {
            $ageEnMois = $passation->getAgeChronologiqueMois();
            $itemsApplicables = $this->itemIDERepository->findByTestAndAge($test, $ageEnMois);
            $nombreItemsAttendu = count($itemsApplicables);
            
            if ($total < $nombreItemsAttendu * 0.8) {
                $erreurs[] = "Passation incomplète : {$total} réponses sur {$nombreItemsAttendu} attendues.";
            }
        }

        return [
            'valide' => empty($erreurs),
            'erreurs' => $erreurs,
            'avertissements' => $avertissements,
            'statistiques' => [
                'total_reponses' => $total,
                'reponses_oui' => $nombreOui,
                'reponses_non' => $nombreNon,
                'ratio_oui' => $total > 0 ? round($ratioOui * 100, 1) : 0
            ]
        ];
    }
}
