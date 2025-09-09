<?php

namespace App\Entity;

use App\Repository\PatientActivationTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PatientActivationTokenRepository::class)]
#[ORM\Table(name: 'patient_activation_tokens')]
#[ORM\Index(columns: ['token_hash'], name: 'idx_token_hash')]
#[ORM\Index(columns: ['expires_at'], name: 'idx_expires_at')]
class PatientActivationToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Patient::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Patient $patient = null;

    #[ORM\Column(length: 64, unique: true)]
    private ?string $tokenHash = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipCreated = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgentCreated = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTokenHash(): ?string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): static
    {
        $this->tokenHash = $tokenHash;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getUsedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function setUsedAt(?\DateTimeImmutable $usedAt): static
    {
        $this->usedAt = $usedAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getIpCreated(): ?string
    {
        return $this->ipCreated;
    }

    public function setIpCreated(?string $ipCreated): static
    {
        $this->ipCreated = $ipCreated;
        return $this;
    }

    public function getUserAgentCreated(): ?string
    {
        return $this->userAgentCreated;
    }

    public function setUserAgentCreated(?string $userAgentCreated): static
    {
        $this->userAgentCreated = $userAgentCreated;
        return $this;
    }

    /**
     * Vérifie si le token est encore valide
     */
    public function isValid(): bool
    {
        return $this->usedAt === null && 
               $this->expiresAt > new \DateTimeImmutable();
    }

    /**
     * Vérifie si le token a expiré
     */
    public function isExpired(): bool
    {
        return $this->expiresAt <= new \DateTimeImmutable();
    }

    /**
     * Vérifie si le token a été utilisé
     */
    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }

    /**
     * Marque le token comme utilisé
     */
    public function markAsUsed(): static
    {
        $this->usedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Génère un token sécurisé (256 bits)
     */
    public static function generateSecureToken(): string
    {
        return bin2hex(random_bytes(32)); // 256 bits = 32 bytes
    }

    /**
     * Hash un token pour le stockage sécurisé
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
