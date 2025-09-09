<?php

namespace App\Message;

class PractitionerNotification
{
    public function __construct(
        private int $practitionerId,
        private string $practitionerEmail,
        private string $subject,
        private string $message,
        private string $type = 'info'
    ) {
    }

    public function getPractitionerId(): int
    {
        return $this->practitionerId;
    }

    public function getPractitionerEmail(): string
    {
        return $this->practitionerEmail;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
