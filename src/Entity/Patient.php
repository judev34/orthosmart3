<?php

namespace App\Entity;

use App\Repository\PatientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PatientRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'Il existe déjà un compte avec cet email')]
class Patient implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire')]
    private ?string $prenom = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire')]
    #[Assert\Email(message: 'L\'email {{ value }} n\'est pas un email valide')]
    private ?string $email = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de naissance est obligatoire')]
    private ?\DateTimeInterface $dateNaissance = null;

    #[ORM\Column(nullable: true)]
    private ?string $password = null;


    #[ORM\ManyToOne(inversedBy: 'patients')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $praticien = null;

    #[ORM\Column]
    private array $roles = [];

    /**
     * @var Collection<int, Prescription>
     */
    #[ORM\OneToMany(mappedBy: 'patient', targetEntity: Prescription::class, orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $prescriptions;

    public function __construct()
    {
        $this->prescriptions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getDateNaissance(): ?\DateTimeInterface
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(\DateTimeInterface $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;

        return $this;
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

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }


    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        return ['ROLE_PATIENT'];
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // Cette méthode peut rester vide - plus de plainPassword à effacer
    }

    /**
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
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
            $prescription->setPatient($this);
        }

        return $this;
    }

    public function removePrescription(Prescription $prescription): static
    {
        if ($this->prescriptions->removeElement($prescription)) {
            // set the owning side to null (unless already changed)
            if ($prescription->getPatient() === $this) {
                $prescription->setPatient(null);
            }
        }

        return $this;
    }

    /**
     * Retourne les prescriptions actives (non annulées)
     */
    public function getPrescriptionsActives(): Collection
    {
        return $this->prescriptions->filter(function (Prescription $prescription) {
            return $prescription->getStatut() !== Prescription::STATUT_ANNULE;
        });
    }

    /**
     * Retourne les prescriptions en cours ou en attente
     */
    public function getPrescriptionsEnCours(): Collection
    {
        return $this->prescriptions->filter(function (Prescription $prescription) {
            return in_array($prescription->getStatut(), [
                Prescription::STATUT_EN_ATTENTE,
                Prescription::STATUT_EN_COURS
            ]);
        });
    }

    /**
     * Calcule l'âge en mois à une date donnée
     */
    public function getAgeEnMois(?\DateTimeInterface $dateReference = null): int
    {
        if ($this->dateNaissance === null) {
            return 0;
        }

        $dateReference = $dateReference ?? new \DateTime();
        $interval = $this->dateNaissance->diff($dateReference);
        
        return ($interval->y * 12) + $interval->m;
    }

    /**
     * Retourne l'âge formaté
     */
    public function getAgeFormate(?\DateTimeInterface $dateReference = null): string
    {
        if ($this->dateNaissance === null) {
            return 'Non renseigné';
        }

        $dateReference = $dateReference ?? new \DateTime();
        $interval = $this->dateNaissance->diff($dateReference);
        
        if ($interval->y > 0) {
            return sprintf('%d an%s %d mois', $interval->y, $interval->y > 1 ? 's' : '', $interval->m);
        }
        
        return sprintf('%d mois', $interval->m);
    }

    /**
     * Retourne le nom complet du patient
     */
    public function getNomComplet(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }
}
