<?php

namespace App\Message;

class PatientActivationNotification
{
    public function __construct(
        private int $patientId,
        private string $patientEmail,
        private string $patientName,
        private string $activationToken
    ) {
    }

    public function getPatientId(): int
    {
        return $this->patientId;
    }

    public function getPatientEmail(): string
    {
        return $this->patientEmail;
    }

    public function getPatientName(): string
    {
        return $this->patientName;
    }

    public function getActivationToken(): string
    {
        return $this->activationToken;
    }
}
