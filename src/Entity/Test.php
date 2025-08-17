<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'test')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap([
    'IDE' => TestIDE::class
])]
abstract class Test
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom du test est obligatoire')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]
    private string $nom;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank(message: 'La version est obligatoire')]
    #[Assert\Length(max: 10, maxMessage: 'La version ne peut pas dépasser {{ limit }} caractères')]
    private string $version;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'L\'âge minimum est obligatoire')]
    #[Assert\PositiveOrZero(message: 'L\'âge minimum doit être positif ou zéro')]
    private int $ageMinMois;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'L\'âge maximum est obligatoire')]
    #[Assert\Positive(message: 'L\'âge maximum doit être positif')]
    private int $ageMaxMois;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    private string $description;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column]
    private bool $actif = true;

    /**
     * @var Collection<int, Prescription>
     */
    #[ORM\OneToMany(mappedBy: 'test', targetEntity: Prescription::class)]
    private Collection $prescriptions;

    public function __construct()
    {
        $this->prescriptions = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): static
    {
        $this->version = $version;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getAgeMinMois(): int
    {
        return $this->ageMinMois;
    }

    public function setAgeMinMois(int $ageMinMois): static
    {
        $this->ageMinMois = $ageMinMois;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getAgeMaxMois(): int
    {
        return $this->ageMaxMois;
    }

    public function setAgeMaxMois(int $ageMaxMois): static
    {
        $this->ageMaxMois = $ageMaxMois;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * @return Collection<int, Prescription>
     */
    public function getPrescriptions(): Collection
    {
        return $this->prescriptions;
    }

    public function addPrescription(Prescription $prescription): static
    {
        if (!$this->prescriptions->contains($prescription)) {
            $this->prescriptions->add($prescription);
            $prescription->setTest($this);
        }

        return $this;
    }

    public function removePrescription(Prescription $prescription): static
    {
        if ($this->prescriptions->removeElement($prescription)) {
            // set the owning side to null (unless already changed)
            if ($prescription->getTest() === $this) {
                $prescription->setTest(null);
            }
        }

        return $this;
    }

    /**
     * Vérifie si le test est compatible avec l'âge donné
     */
    public function isCompatibleAvecAge(int $ageEnMois): bool
    {
        return $ageEnMois >= $this->ageMinMois && $ageEnMois <= $this->ageMaxMois;
    }

    /**
     * Retourne le nom complet du test avec sa version
     */
    public function getNomComplet(): string
    {
        return $this->nom . ' (' . $this->version . ')';
    }

    /**
     * Méthode abstraite pour obtenir le type de test
     */
    abstract public function getType(): string;

    /**
     * Méthode abstraite pour obtenir les domaines évalués
     */
    abstract public function getDomaines(): array;
}
