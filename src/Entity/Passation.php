<?php

namespace App\Entity;

use App\Repository\PassationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PassationRepository::class)]
#[ORM\Table(name: 'passation')]
#[ORM\HasLifecycleCallbacks]
class Passation
{
    /**
     * Statuts possibles d'une passation
     */
    public const STATUT_DEMARREE = 'demarree';
    public const STATUT_EN_COURS = 'en_cours';
    public const STATUT_SUSPENDUE = 'suspendue';
    public const STATUT_TERMINEE = 'terminee';
    public const STATUT_ABANDONNEE = 'abandonnee';

    public const STATUTS = [
        self::STATUT_DEMARREE => 'Démarrée',
        self::STATUT_EN_COURS => 'En cours',
        self::STATUT_SUSPENDUE => 'Suspendue',
        self::STATUT_TERMINEE => 'Terminée',
        self::STATUT_ABANDONNEE => 'Abandonnée'
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prescription $prescription = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = self::STATUT_DEMARREE;

    /**
     * Réponses du patient/parent stockées en JSON
     * Structure : {"partie_domaine_ordre": "oui|non", ...}
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $reponses = [];

    /**
     * Scores calculés stockés en JSON
     * Structure : {"domaine": {"partie": score, "total": score}, ...}
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $scores = [];

    /**
     * Progression de la passation (pourcentage)
     */
    #[ORM\Column]
    private ?int $progression = 0;

    /**
     * Partie courante en cours de passation
     */
    #[ORM\Column(length: 2, nullable: true)]
    private ?string $partieCourante = null;

    /**
     * Âge chronologique de l'enfant en mois au moment de la passation
     */
    #[ORM\Column]
    private ?int $ageChronologiqueMois = null;

    /**
     * Date de naissance de l'enfant (pour calcul précis de l'âge)
     */
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateNaissance = null;

    /**
     * Date de début de la passation
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateDebut = null;

    /**
     * Date de fin de la passation
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    /**
     * Durée totale de passation en minutes
     */
    #[ORM\Column(nullable: true)]
    private ?int $dureeMinutes = null;

    /**
     * Commentaires ou observations pendant la passation
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observations = null;

    /**
     * Adresse IP de la passation (pour traçabilité)
     */
    #[ORM\Column(length: 45, nullable: true)]
    private ?string $adresseIP = null;

    /**
     * User-Agent du navigateur (pour traçabilité)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->reponses = [];
        $this->scores = [];
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

        // Mise à jour automatique des dates
        if ($statut === self::STATUT_DEMARREE && $this->dateDebut === null) {
            $this->dateDebut = new \DateTime();
        }
        
        if ($statut === self::STATUT_TERMINEE && $this->dateFin === null) {
            $this->dateFin = new \DateTime();
            $this->calculerDuree();
        }

        return $this;
    }

    public function getReponses(): ?array
    {
        return $this->reponses;
    }

    public function setReponses(?array $reponses): static
    {
        $this->reponses = $reponses;

        return $this;
    }

    public function getScores(): ?array
    {
        return $this->scores;
    }

    public function setScores(?array $scores): static
    {
        $this->scores = $scores;

        return $this;
    }

    public function getProgression(): ?int
    {
        return $this->progression;
    }

    public function setProgression(int $progression): static
    {
        $this->progression = max(0, min(100, $progression));

        return $this;
    }

    public function getPartieCourante(): ?string
    {
        return $this->partieCourante;
    }

    public function setPartieCourante(?string $partieCourante): static
    {
        $this->partieCourante = $partieCourante;

        return $this;
    }

    public function getAgeChronologiqueMois(): ?int
    {
        return $this->ageChronologiqueMois;
    }

    public function setAgeChronologiqueMois(int $ageChronologiqueMois): static
    {
        $this->ageChronologiqueMois = $ageChronologiqueMois;

        return $this;
    }

    public function getDateNaissance(): ?\DateTimeInterface
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(\DateTimeInterface $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;
        
        // Calcul automatique de l'âge chronologique
        $this->calculerAgeChronologique();

        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;
        
        if ($dateFin !== null) {
            $this->calculerDuree();
        }

        return $this;
    }

    public function getDureeMinutes(): ?int
    {
        return $this->dureeMinutes;
    }

    public function setDureeMinutes(?int $dureeMinutes): static
    {
        $this->dureeMinutes = $dureeMinutes;

        return $this;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function setObservations(?string $observations): static
    {
        $this->observations = $observations;

        return $this;
    }

    public function getAdresseIP(): ?string
    {
        return $this->adresseIP;
    }

    public function setAdresseIP(?string $adresseIP): static
    {
        $this->adresseIP = $adresseIP;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

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
     * Vérifie si la passation est terminée
     */
    public function isTerminee(): bool
    {
        return $this->statut === self::STATUT_TERMINEE;
    }

    /**
     * Vérifie si la passation est abandonnée
     */
    public function isAbandonee(): bool
    {
        return $this->statut === self::STATUT_ABANDONNEE;
    }

    /**
     * Vérifie si la passation est suspendue
     */
    public function isSuspendue(): bool
    {
        return $this->statut === self::STATUT_SUSPENDUE;
    }

    /**
     * Vérifie si la passation est en cours
     */
    public function isEnCours(): bool
    {
        return in_array($this->statut, [self::STATUT_DEMARREE, self::STATUT_EN_COURS]);
    }

    /**
     * Vérifie si la passation peut être reprise
     */
    public function peutEtreReprise(): bool
    {
        return in_array($this->statut, [self::STATUT_EN_COURS, self::STATUT_SUSPENDUE]);
    }

    /**
     * Ajoute ou met à jour une réponse
     */
    public function ajouterReponse(string $itemId, string $reponse): static
    {
        if (!is_array($this->reponses)) {
            $this->reponses = [];
        }
        
        $this->reponses[$itemId] = $reponse;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    /**
     * Retourne une réponse spécifique
     */
    public function getReponse(string $itemId): ?string
    {
        return $this->reponses[$itemId] ?? null;
    }

    /**
     * Calcule l'âge chronologique en mois
     */
    private function calculerAgeChronologique(): void
    {
        if ($this->dateNaissance === null) {
            return;
        }

        $dateReference = $this->dateDebut ?? new \DateTime();
        $interval = $this->dateNaissance->diff($dateReference);
        
        $this->ageChronologiqueMois = ($interval->y * 12) + $interval->m;
    }

    /**
     * Calcule la durée de la passation
     */
    private function calculerDuree(): void
    {
        if ($this->dateDebut === null || $this->dateFin === null) {
            return;
        }

        $interval = $this->dateDebut->diff($this->dateFin);
        $this->dureeMinutes = ($interval->h * 60) + $interval->i;
    }

    /**
     * Retourne le nombre total de réponses
     */
    public function getNombreReponses(): int
    {
        return count($this->reponses ?? []);
    }

    /**
     * Retourne le nombre de réponses "oui"
     */
    public function getNombreReponsesOui(): int
    {
        return count(array_filter($this->reponses ?? [], fn($reponse) => $reponse === 'oui'));
    }

    /**
     * Retourne le nombre de réponses "non"
     */
    public function getNombreReponsesNon(): int
    {
        return count(array_filter($this->reponses ?? [], fn($reponse) => $reponse === 'non'));
    }

    /**
     * Retourne la durée formatée
     */
    public function getDureeFormatee(): string
    {
        if ($this->dureeMinutes === null) {
            return 'Non calculée';
        }

        $heures = intval($this->dureeMinutes / 60);
        $minutes = $this->dureeMinutes % 60;

        if ($heures > 0) {
            return sprintf('%dh %02dm', $heures, $minutes);
        }

        return sprintf('%dm', $minutes);
    }

    /**
     * Retourne la dernière activité (date de mise à jour)
     */
    public function getDerniereActivite(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Vérifie si la passation peut être modifiée
     */
    public function peutEtreModifiee(): bool
    {
        return !in_array($this->statut, [self::STATUT_TERMINEE, self::STATUT_ABANDONNEE]);
    }
}
