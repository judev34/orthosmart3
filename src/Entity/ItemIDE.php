<?php

namespace App\Entity;

use App\Repository\ItemIDERepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ItemIDERepository::class)]
#[ORM\Table(name: 'item_ide')]
class ItemIDE
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TestIDE $testIDE = null;

    /**
     * Partie du questionnaire (AP, A, B, C, D, E)
     */
    #[ORM\Column(length: 2)]
    private ?string $partie = null;

    /**
     * Domaine d'évaluation (SO, AU, MG, MF, LEX, LCO, LE, NBRE, DG)
     */
    #[ORM\Column(length: 5)]
    private ?string $domaine = null;

    /**
     * Ordre d'affichage dans la partie
     */
    #[ORM\Column]
    private ?int $ordre = null;

    /**
     * Texte de la question/item
     */
    #[ORM\Column(type: Types::TEXT)]
    private ?string $texte = null;

    /**
     * Indique si cet item compte pour le Développement Général (DG)
     * Correspond aux items marqués ▬—▬ dans le questionnaire
     */
    #[ORM\Column]
    private ?bool $compteDG = false;

    /**
     * Âge minimum en mois pour cet item
     */
    #[ORM\Column(nullable: true)]
    private ?int $ageMinMois = null;

    /**
     * Âge maximum en mois pour cet item
     */
    #[ORM\Column(nullable: true)]
    private ?int $ageMaxMois = null;

    /**
     * Instructions spéciales ou notes pour cet item
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $instructions = null;

    /**
     * Indique si l'item est actif
     */
    #[ORM\Column]
    private ?bool $actif = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
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

    public function getTestIDE(): ?TestIDE
    {
        return $this->testIDE;
    }

    public function setTestIDE(?TestIDE $testIDE): static
    {
        $this->testIDE = $testIDE;

        return $this;
    }

    public function getPartie(): ?string
    {
        return $this->partie;
    }

    public function setPartie(string $partie): static
    {
        $this->partie = $partie;

        return $this;
    }

    public function getDomaine(): ?string
    {
        return $this->domaine;
    }

    public function setDomaine(string $domaine): static
    {
        $this->domaine = $domaine;

        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function getTexte(): ?string
    {
        return $this->texte;
    }

    public function setTexte(string $texte): static
    {
        $this->texte = $texte;

        return $this;
    }

    public function isCompteDG(): ?bool
    {
        return $this->compteDG;
    }

    public function setCompteDG(bool $compteDG): static
    {
        $this->compteDG = $compteDG;

        return $this;
    }

    public function getAgeMinMois(): ?int
    {
        return $this->ageMinMois;
    }

    public function setAgeMinMois(?int $ageMinMois): static
    {
        $this->ageMinMois = $ageMinMois;

        return $this;
    }

    public function getAgeMaxMois(): ?int
    {
        return $this->ageMaxMois;
    }

    public function setAgeMaxMois(?int $ageMaxMois): static
    {
        $this->ageMaxMois = $ageMaxMois;

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

    public function isActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

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
     * Retourne le nom complet du domaine
     */
    public function getNomDomaine(): string
    {
        return TestIDE::DOMAINES[$this->domaine] ?? $this->domaine;
    }

    /**
     * Retourne le nom complet de la partie
     */
    public function getNomPartie(): string
    {
        return TestIDE::PARTIES[$this->partie] ?? $this->partie;
    }

    /**
     * Vérifie si l'item est applicable pour un âge donné
     */
    public function isApplicableAge(int $ageEnMois): bool
    {
        if ($this->ageMinMois !== null && $ageEnMois < $this->ageMinMois) {
            return false;
        }
        
        if ($this->ageMaxMois !== null && $ageEnMois > $this->ageMaxMois) {
            return false;
        }
        
        return true;
    }

    /**
     * Retourne un identifiant unique pour cet item
     */
    public function getIdentifiant(): string
    {
        return sprintf('%s_%s_%d', $this->partie, $this->domaine, $this->ordre);
    }
}
