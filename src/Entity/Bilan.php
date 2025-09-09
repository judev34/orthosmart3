<?php

namespace App\Entity;

use App\Repository\BilanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BilanRepository::class)]
#[ORM\Table(name: 'bilan')]
#[ORM\HasLifecycleCallbacks]
class Bilan
{
    /**
     * Statuts possibles d'un bilan
     */
    public const STATUT_GENERE = 'genere';
    public const STATUT_EN_REVISION = 'en_revision';
    public const STATUT_VALIDE = 'valide';
    public const STATUT_FINALISE = 'finalise';

    public const STATUTS = [
        self::STATUT_GENERE => 'Généré automatiquement',
        self::STATUT_EN_REVISION => 'En révision',
        self::STATUT_VALIDE => 'Validé par le praticien',
        self::STATUT_FINALISE => 'Finalisé'
    ];

    /**
     * Niveaux de risque selon les seuils IDE
     */
    public const RISQUE_FAIBLE = 'faible';
    public const RISQUE_MODERE = 'modere';
    public const RISQUE_HAUT = 'haut';
    public const RISQUE_TRES_HAUT = 'tres_haut';

    public const NIVEAUX_RISQUE = [
        self::RISQUE_FAIBLE => 'Faible risque',
        self::RISQUE_MODERE => 'Risque modéré',
        self::RISQUE_HAUT => 'Haut risque (HR)',
        self::RISQUE_TRES_HAUT => 'Très haut risque (THR)'
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'bilans')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prescription $prescription = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = self::STATUT_GENERE;

    /**
     * Interprétation automatique générée par le système
     */
    #[ORM\Column(type: Types::TEXT)]
    private ?string $interpretationAutomatique = null;

    /**
     * Commentaires et observations du praticien
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentairesPraticien = null;

    /**
     * Recommandations du praticien
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $recommandations = null;

    /**
     * Scores détaillés par domaine stockés en JSON
     * Structure : {"domaine": {"score": X, "seuil_hr": Y, "seuil_thr": Z, "risque": "niveau"}}
     */
    #[ORM\Column(type: Types::JSON)]
    private ?array $scoresDetailles = [];

    /**
     * Score global de développement général (DG)
     */
    #[ORM\Column(nullable: true)]
    private ?int $scoreDG = null;

    /**
     * Niveau de risque global
     */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $niveauRisqueGlobal = null;

    /**
     * Âge de développement estimé en mois
     */
    #[ORM\Column(nullable: true)]
    private ?int $ageDeveloppementMois = null;

    /**
     * Profil graphique des scores (données pour graphique)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $profilGraphique = [];

    /**
     * Points forts identifiés
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $pointsForts = [];

    /**
     * Points de vigilance identifiés
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $pointsVigilance = [];

    /**
     * Date de génération du bilan
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateGeneration = null;

    /**
     * Date de validation par le praticien
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateValidation = null;

    /**
     * Chemin vers le fichier PDF généré
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cheminPDF = null;

    /**
     * Version du bilan (pour historique)
     */
    #[ORM\Column]
    private ?int $version = 1;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->dateGeneration = new \DateTime();
        $this->scoresDetailles = [];
        $this->profilGraphique = [];
        $this->pointsForts = [];
        $this->pointsVigilance = [];
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrescription(): ?Prescription
    {
        return $this->prescription;
    }

    public function setPrescription(?Prescription $prescription): static
    {
        $this->prescription = $prescription;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        // Mise à jour automatique de la date de validation
        if ($statut === self::STATUT_VALIDE && $this->dateValidation === null) {
            $this->dateValidation = new \DateTime();
        }

        return $this;
    }

    public function getInterpretationAutomatique(): ?string
    {
        return $this->interpretationAutomatique;
    }

    public function setInterpretationAutomatique(string $interpretationAutomatique): static
    {
        $this->interpretationAutomatique = $interpretationAutomatique;

        return $this;
    }

    public function getCommentairesPraticien(): ?string
    {
        return $this->commentairesPraticien;
    }

    public function setCommentairesPraticien(?string $commentairesPraticien): static
    {
        $this->commentairesPraticien = $commentairesPraticien;

        return $this;
    }

    public function getRecommandations(): ?string
    {
        return $this->recommandations;
    }

    public function setRecommandations(?string $recommandations): static
    {
        $this->recommandations = $recommandations;

        return $this;
    }

    public function getScoresDetailles(): ?array
    {
        return $this->scoresDetailles;
    }

    public function setScoresDetailles(array $scoresDetailles): static
    {
        $this->scoresDetailles = $scoresDetailles;

        return $this;
    }

    public function getScoreDG(): ?int
    {
        return $this->scoreDG;
    }

    public function setScoreDG(?int $scoreDG): static
    {
        $this->scoreDG = $scoreDG;

        return $this;
    }

    public function getNiveauRisqueGlobal(): ?string
    {
        return $this->niveauRisqueGlobal;
    }

    public function setNiveauRisqueGlobal(?string $niveauRisqueGlobal): static
    {
        $this->niveauRisqueGlobal = $niveauRisqueGlobal;

        return $this;
    }

    public function getAgeDeveloppementMois(): ?int
    {
        return $this->ageDeveloppementMois;
    }

    public function setAgeDeveloppementMois(?int $ageDeveloppementMois): static
    {
        $this->ageDeveloppementMois = $ageDeveloppementMois;

        return $this;
    }

    public function getProfilGraphique(): ?array
    {
        return $this->profilGraphique;
    }

    public function setProfilGraphique(?array $profilGraphique): static
    {
        $this->profilGraphique = $profilGraphique;

        return $this;
    }

    public function getPointsForts(): ?array
    {
        return $this->pointsForts;
    }

    public function setPointsForts(?array $pointsForts): static
    {
        $this->pointsForts = $pointsForts;

        return $this;
    }

    public function getPointsVigilance(): ?array
    {
        return $this->pointsVigilance;
    }

    public function setPointsVigilance(?array $pointsVigilance): static
    {
        $this->pointsVigilance = $pointsVigilance;

        return $this;
    }

    public function getDateGeneration(): ?\DateTimeInterface
    {
        return $this->dateGeneration;
    }

    public function setDateGeneration(\DateTimeInterface $dateGeneration): static
    {
        $this->dateGeneration = $dateGeneration;

        return $this;
    }

    public function getDateValidation(): ?\DateTimeInterface
    {
        return $this->dateValidation;
    }

    public function setDateValidation(?\DateTimeInterface $dateValidation): static
    {
        $this->dateValidation = $dateValidation;

        return $this;
    }

    public function getCheminPDF(): ?string
    {
        return $this->cheminPDF;
    }

    public function setCheminPDF(?string $cheminPDF): static
    {
        $this->cheminPDF = $cheminPDF;

        return $this;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function setVersion(int $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Retourne le nom du statut
     */
    public function getNomStatut(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    /**
     * Retourne le nom du niveau de risque global
     */
    public function getNomNiveauRisque(): string
    {
        return self::NIVEAUX_RISQUE[$this->niveauRisqueGlobal] ?? $this->niveauRisqueGlobal;
    }

    /**
     * Vérifie si le bilan est validé
     */
    public function isValide(): bool
    {
        return in_array($this->statut, [self::STATUT_VALIDE, self::STATUT_FINALISE]);
    }

    /**
     * Vérifie si le bilan peut être modifié
     */
    public function peutEtreModifie(): bool
    {
        return $this->statut !== self::STATUT_FINALISE;
    }

    /**
     * Retourne le score d'un domaine spécifique
     */
    public function getScoreDomaine(string $domaine): ?int
    {
        return $this->scoresDetailles[$domaine]['score'] ?? null;
    }

    /**
     * Retourne le niveau de risque d'un domaine spécifique
     */
    public function getNiveauRisqueDomaine(string $domaine): ?string
    {
        return $this->scoresDetailles[$domaine]['risque'] ?? null;
    }

    /**
     * Ajoute un point fort
     */
    public function ajouterPointFort(string $domaine, string $description): static
    {
        if (!is_array($this->pointsForts)) {
            $this->pointsForts = [];
        }
        
        $this->pointsForts[] = [
            'domaine' => $domaine,
            'description' => $description
        ];

        return $this;
    }

    /**
     * Ajoute un point de vigilance
     */
    public function ajouterPointVigilance(string $domaine, string $description): static
    {
        if (!is_array($this->pointsVigilance)) {
            $this->pointsVigilance = [];
        }
        
        $this->pointsVigilance[] = [
            'domaine' => $domaine,
            'description' => $description
        ];

        return $this;
    }

    /**
     * Retourne l'âge de développement formaté
     */
    public function getAgeDeveloppementFormate(): string
    {
        if ($this->ageDeveloppementMois === null) {
            return 'Non calculé';
        }

        $annees = intval($this->ageDeveloppementMois / 12);
        $mois = $this->ageDeveloppementMois % 12;

        if ($annees > 0) {
            return sprintf('%d an%s %d mois', $annees, $annees > 1 ? 's' : '', $mois);
        }

        return sprintf('%d mois', $mois);
    }

    /**
     * Retourne la classe CSS pour le niveau de risque
     */
    public function getClasseRisque(): string
    {
        return match($this->niveauRisqueGlobal) {
            self::RISQUE_FAIBLE => 'text-green-600 bg-green-50',
            self::RISQUE_MODERE => 'text-yellow-600 bg-yellow-50',
            self::RISQUE_HAUT => 'text-orange-600 bg-orange-50',
            self::RISQUE_TRES_HAUT => 'text-red-600 bg-red-50',
            default => 'text-gray-600 bg-gray-50'
        };
    }

    /**
     * Génère un résumé du bilan
     */
    public function genererResume(): string
    {
        $patient = $this->prescription->getPatient();
        $test = $this->prescription->getTest();
        
        $resume = sprintf(
            "Bilan %s pour %s %s (né(e) le %s)\n",
            $test->getNom(),
            $patient->getPrenom(),
            $patient->getNom(),
            $patient->getDateNaissance()?->format('d/m/Y')
        );
        
        $resume .= sprintf(
            "Score DG: %d - Niveau de risque: %s\n",
            $this->scoreDG,
            $this->getNomNiveauRisque()
        );
        
        if (!empty($this->pointsForts)) {
            $resume .= "\nPoints forts: " . count($this->pointsForts) . " identifiés\n";
        }
        
        if (!empty($this->pointsVigilance)) {
            $resume .= "Points de vigilance: " . count($this->pointsVigilance) . " identifiés\n";
        }
        
        return $resume;
    }
}
