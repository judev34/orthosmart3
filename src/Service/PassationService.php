<?php

namespace App\Service;

use App\Entity\Prescription;
use App\Entity\Passation;
use App\Entity\Patient;
use App\Repository\PassationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Service de gestion des passations de tests
 */
class PassationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PassationRepository $passationRepository
    ) {
    }

    /**
     * Démarre une nouvelle passation pour une prescription
     */
    public function demarrerPassation(
        Prescription $prescription,
        \DateTimeInterface $dateNaissance,
        ?Request $request = null
    ): Passation {
        // Vérifier que la prescription peut être démarrée
        if (!$prescription->peutEtreDemarree()) {
            throw new \InvalidArgumentException('Cette prescription ne peut pas être démarrée');
        }

        // Vérifier s'il n'y a pas déjà une passation en cours
        $passationEnCours = $this->getPassationEnCours($prescription);
        if ($passationEnCours !== null) {
            throw new \InvalidArgumentException('Une passation est déjà en cours pour cette prescription');
        }

        // Créer la nouvelle passation
        $passation = new Passation();
        $passation->setPrescription($prescription);
        $passation->setDateNaissance($dateNaissance);
        $passation->setStatut(Passation::STATUT_DEMARREE);

        // Informations de traçabilité si disponibles
        if ($request !== null) {
            $passation->setAdresseIP($request->getClientIp());
            $passation->setUserAgent($request->headers->get('User-Agent'));
        }

        // Mettre à jour le statut de la prescription
        $prescription->setStatut(Prescription::STATUT_EN_COURS);

        $this->entityManager->persist($passation);
        $this->entityManager->flush();

        return $passation;
    }

    /**
     * Reprend une passation suspendue
     */
    public function reprendrePassation(Passation $passation): Passation
    {
        if (!$passation->peutEtreReprise()) {
            throw new \InvalidArgumentException('Cette passation ne peut pas être reprise');
        }

        $passation->setStatut(Passation::STATUT_EN_COURS);
        $this->entityManager->flush();

        return $passation;
    }

    /**
     * Suspend une passation en cours
     */
    public function suspendrePassation(Passation $passation): void
    {
        if ($passation->getStatut() !== Passation::STATUT_EN_COURS) {
            throw new \InvalidArgumentException('Seule une passation en cours peut être suspendue');
        }

        $passation->setStatut(Passation::STATUT_SUSPENDUE);
        $this->entityManager->flush();
    }

    /**
     * Abandonne une passation
     */
    public function abandonnerPassation(Passation $passation): void
    {
        if ($passation->isTerminee()) {
            throw new \InvalidArgumentException('Une passation terminée ne peut pas être abandonnée');
        }

        $passation->setStatut(Passation::STATUT_ABANDONNEE);
        
        // Remettre la prescription en attente
        $prescription = $passation->getPrescription();
        $prescription->setStatut(Prescription::STATUT_EN_ATTENTE);

        $this->entityManager->flush();
    }

    /**
     * Sauvegarde une réponse dans la passation
     */
    public function sauvegarderReponse(
        Passation $passation,
        string $itemId,
        string $reponse,
        ?string $partieCourante = null
    ): void {
        if (!in_array($reponse, ['oui', 'non'])) {
            throw new \InvalidArgumentException('La réponse doit être "oui" ou "non"');
        }

        $passation->ajouterReponse($itemId, $reponse);
        
        if ($partieCourante !== null) {
            $passation->setPartieCourante($partieCourante);
        }

        // Calculer la progression
        $this->calculerProgression($passation);

        $this->entityManager->flush();
    }

    /**
     * Sauvegarde plusieurs réponses en une fois
     */
    public function sauvegarderReponses(
        Passation $passation,
        array $reponses,
        ?string $partieCourante = null
    ): void {
        foreach ($reponses as $itemId => $reponse) {
            if (!in_array($reponse, ['oui', 'non'])) {
                throw new \InvalidArgumentException("La réponse pour l'item {$itemId} doit être \"oui\" ou \"non\"");
            }
            
            $passation->ajouterReponse($itemId, $reponse);
        }

        if ($partieCourante !== null) {
            $passation->setPartieCourante($partieCourante);
        }

        // Calculer la progression
        $this->calculerProgression($passation);

        $this->entityManager->flush();
    }

    /**
     * Termine une passation
     */
    public function terminerPassation(Passation $passation): void
    {
        if ($passation->isTerminee()) {
            throw new \InvalidArgumentException('Cette passation est déjà terminée');
        }

        $passation->setStatut(Passation::STATUT_TERMINEE);
        $passation->setProgression(100);

        // Mettre à jour le statut de la prescription
        $prescription = $passation->getPrescription();
        $prescription->setStatut(Prescription::STATUT_TERMINE);

        $this->entityManager->flush();
    }

    /**
     * Retourne la passation en cours pour une prescription
     */
    public function getPassationEnCours(Prescription $prescription): ?Passation
    {
        $passations = $this->passationRepository->findByPrescription($prescription);
        
        foreach ($passations as $passation) {
            if (in_array($passation->getStatut(), [
                Passation::STATUT_DEMARREE,
                Passation::STATUT_EN_COURS,
                Passation::STATUT_SUSPENDUE
            ])) {
                return $passation;
            }
        }

        return null;
    }

    /**
     * Retourne la dernière passation d'une prescription
     */
    public function getDernierePassation(Prescription $prescription): ?Passation
    {
        return $this->passationRepository->findLastByPrescription($prescription);
    }

    /**
     * Retourne les passations terminées d'un patient
     */
    public function getPassationsTerminees(Patient $patient): array
    {
        return $this->passationRepository->findTermineesForPatient($patient);
    }

    /**
     * Calcule la progression d'une passation
     */
    private function calculerProgression(Passation $passation): void
    {
        $prescription = $passation->getPrescription();
        $test = $prescription->getTest();
        
        // Pour l'instant, calcul simple basé sur le nombre de réponses
        // TODO: Améliorer avec la logique spécifique à chaque type de test
        $nombreReponses = $passation->getNombreReponses();
        
        // Estimation basée sur un test IDE complet (environ 200 items)
        $nombreItemsEstime = 200;
        $progression = min(100, round(($nombreReponses / $nombreItemsEstime) * 100));
        
        $passation->setProgression($progression);
    }

    /**
     * Retourne les statistiques des passations
     */
    public function getStatistiquesPassations(): array
    {
        $stats = $this->passationRepository->getStatistiquesParStatut();
        $dureeMoyenne = $this->passationRepository->getDureeMoyenneTerminees();
        
        return [
            'par_statut' => $stats,
            'duree_moyenne_minutes' => $dureeMoyenne,
            'duree_moyenne_formatee' => $dureeMoyenne ? sprintf('%dh %02dm', 
                intval($dureeMoyenne / 60), 
                $dureeMoyenne % 60
            ) : 'Non calculée'
        ];
    }

    /**
     * Nettoie les passations abandonnées anciennes
     */
    public function nettoyerPassationsAbandonneesAnciennes(int $joursLimite = 30): int
    {
        $passationsAbandonnes = $this->passationRepository->findAbandonneesDepuis($joursLimite);
        
        $count = 0;
        foreach ($passationsAbandonnes as $passation) {
            $this->entityManager->remove($passation);
            $count++;
        }
        
        if ($count > 0) {
            $this->entityManager->flush();
        }
        
        return $count;
    }

    /**
     * Relance les passations suspendues depuis longtemps
     */
    public function getPassationsSuspenduesARelancer(int $joursLimite = 7): array
    {
        return $this->passationRepository->findSuspenduesDepuis($joursLimite);
    }

    /**
     * Vérifie si une passation peut être démarrée pour un patient
     */
    public function peutDemarrerPassation(Prescription $prescription): array
    {
        $erreurs = [];
        
        // Vérifier le consentement RGPD
        if (!$prescription->isConsentementRGPD()) {
            $erreurs[] = 'Le consentement RGPD n\'a pas été donné';
        }
        
        // Vérifier qu'il n'y a pas déjà une passation en cours
        if ($this->getPassationEnCours($prescription) !== null) {
            $erreurs[] = 'Une passation est déjà en cours pour cette prescription';
        }
        
        // Vérifier la date limite
        if ($prescription->isEnRetard()) {
            $erreurs[] = 'La date limite pour passer ce test est dépassée';
        }
        
        return [
            'peut_demarrer' => empty($erreurs),
            'erreurs' => $erreurs
        ];
    }
}
