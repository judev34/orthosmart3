<?php

namespace App\Message;

class BilanReadyNotification
{
    public function __construct(
        private int $bilanId,
        private int $patientId,
        private string $patientEmail,
        private string $patientName,
        private int $practitionerId,
        private string $practitionerEmail
    ) {
    }

    public function getBilanId(): int
    {
        return $this->bilanId;
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

    public function getPractitionerId(): int
    {
        return $this->practitionerId;
    }

    public function getPractitionerEmail(): string
    {
        return $this->practitionerEmail;
    }
}
