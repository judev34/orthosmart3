<?php

namespace App\Entity;

use App\Repository\PrescriptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PrescriptionRepository::class)]
#[ORM\Table(name: 'prescription')]
#[ORM\HasLifecycleCallbacks]
class Prescription
{
    /**
     * Statuts possibles d'une prescription
     */
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_EN_COURS = 'en_cours';
    public const STATUT_TERMINE = 'termine';
    public const STATUT_VALIDE = 'valide';
    public const STATUT_ANNULE = 'annule';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE => 'En attente',
        self::STATUT_EN_COURS => 'En cours',
        self::STATUT_TERMINE => 'Terminé',
        self::STATUT_VALIDE => 'Validé',
        self::STATUT_ANNULE => 'Annulé'
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'prescriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $praticien = null;

    #[ORM\ManyToOne(inversedBy: 'prescriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Patient $patient = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Test $test = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = self::STATUT_EN_ATTENTE;

    /**
     * Instructions spéciales du praticien pour cette prescription
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $instructions = null;

    /**
     * Commentaires du praticien
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaires = null;

    /**
     * Date limite pour passer le test (optionnel)
     */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateLimite = null;

    /**
     * Priorité de la prescription (1 = haute, 2 = normale, 3 = basse)
     */
    #[ORM\Column]
    private ?int $priorite = 2;

    /**
     * Indique si le patient/parent a donné son consentement RGPD
     */
    #[ORM\Column]
    private ?bool $consentementRGPD = false;

    /**
     * Date du consentement RGPD
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateConsentement = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, Passation>
     */
    #[ORM\OneToMany(mappedBy: 'prescription', targetEntity: Passation::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $passations;

    /**
     * @var Collection<int, Bilan>
     */
    #[ORM\OneToMany(mappedBy: 'prescription', targetEntity: Bilan::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $bilans;

    public function __construct()
    {
        $this->passations = new ArrayCollection();
        $this->bilans = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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

    public function getPraticien(): ?User
    {
        return $this->praticien;
    }

    public function setPraticien(?User $praticien): static
    {
        $this->praticien = $praticien;

        return $this;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): static
    {
        $this->patient = $patient;

        return $this;
    }

    public function getTest(): ?Test
    {
        return $this->test;
    }

    public function setTest(?Test $test): static
    {
        $this->test = $test;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    public function setInstructions(?string $instructions): static
    {
        $this->instructions = $instructions;

        return $this;
    }

    public function getCommentaires(): ?string
    {
        return $this->commentaires;
    }

    public function setCommentaires(?string $commentaires): static
    {
        $this->commentaires = $commentaires;

        return $this;
    }

    public function getDateLimite(): ?\DateTimeInterface
    {
        return $this->dateLimite;
    }

    public function setDateLimite(?\DateTimeInterface $dateLimite): static
    {
        $this->dateLimite = $dateLimite;

        return $this;
    }

    public function getPriorite(): ?int
    {
        return $this->priorite;
    }

    public function setPriorite(int $priorite): static
    {
        $this->priorite = $priorite;

        return $this;
    }

    public function isConsentementRGPD(): ?bool
    {
        return $this->consentementRGPD;
    }

    public function setConsentementRGPD(bool $consentementRGPD): static
    {
        $this->consentementRGPD = $consentementRGPD;
        
        if ($consentementRGPD && $this->dateConsentement === null) {
            $this->dateConsentement = new \DateTime();
        }

        return $this;
    }

    public function getDateConsentement(): ?\DateTimeInterface
    {
        return $this->dateConsentement;
    }

    public function setDateConsentement(?\DateTimeInterface $dateConsentement): static
    {
        $this->dateConsentement = $dateConsentement;

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
     * @return Collection<int, Passation>
     */
    public function getPassations(): Collection
    {
        return $this->passations;
    }

    public function addPassation(Passation $passation): static
    {
        if (!$this->passations->contains($passation)) {
            $this->passations->add($passation);
            $passation->setPrescription($this);
        }

        return $this;
    }

    public function removePassation(Passation $passation): static
    {
        if ($this->passations->removeElement($passation)) {
            // set the owning side to null (unless already changed)
            if ($passation->getPrescription() === $this) {
                $passation->setPrescription(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Bilan>
     */
    public function getBilans(): Collection
    {
        return $this->bilans;
    }

    public function addBilan(Bilan $bilan): static
    {
        if (!$this->bilans->contains($bilan)) {
            $this->bilans->add($bilan);
            $bilan->setPrescription($this);
        }

        return $this;
    }

    public function removeBilan(Bilan $bilan): static
    {
        if ($this->bilans->removeElement($bilan)) {
            // set the owning side to null (unless already changed)
            if ($bilan->getPrescription() === $this) {
                $bilan->setPrescription(null);
            }
        }

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
     * Retourne la dernière passation
     */
    public function getDernierePassation(): ?Passation
    {
        return $this->passations->first() ?: null;
    }

    /**
     * Retourne le dernier bilan
     */
    public function getDernierBilan(): ?Bilan
    {
        return $this->bilans->first() ?: null;
    }

    /**
     * Vérifie si la prescription est en retard
     */
    public function isEnRetard(): bool
    {
        return $this->dateLimite !== null 
            && $this->dateLimite < new \DateTime() 
            && !in_array($this->statut, [self::STATUT_TERMINE, self::STATUT_VALIDE, self::STATUT_ANNULE]);
    }

    /**
     * Vérifie si la prescription peut être démarrée
     */
    public function peutEtreDemarree(): bool
    {
        return $this->statut === self::STATUT_EN_ATTENTE && $this->consentementRGPD;
    }

    /**
     * Vérifie si la prescription peut être validée
     */
    public function peutEtreValidee(): bool
    {
        return $this->statut === self::STATUT_TERMINE && $this->getDernierePassation()?->isTerminee();
    }

    /**
     * Retourne le niveau de priorité en texte
     */
    public function getNiveauPriorite(): string
    {
        return match($this->priorite) {
            1 => 'Haute',
            2 => 'Normale',
            3 => 'Basse',
            default => 'Normale'
        };
    }

    /**
     * Retourne la classe CSS pour la priorité
     */
    public function getClassePriorite(): string
    {
        return match($this->priorite) {
            1 => 'text-red-600 bg-red-50',
            2 => 'text-blue-600 bg-blue-50',
            3 => 'text-gray-600 bg-gray-50',
            default => 'text-blue-600 bg-blue-50'
        };
    }
}
