<?php

namespace App\Twig\Components;

use App\Repository\PatientRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\Bundle\SecurityBundle\Security;

#[AsTwigComponent('accueil')]
class AccueilComponent
{
    public function __construct(
        private PatientRepository $patientRepository,
        private Security $security
    ) {}

    public function getPraticien(): ?object
    {
        return $this->security->getUser();
    }

    public function getNombrePatients(): int
    {
        return $this->patientRepository->count(['praticien' => $this->getPraticien()]);
    }
}
