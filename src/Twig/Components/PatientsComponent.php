<?php

namespace App\Twig\Components;

use App\Entity\Patient;
use App\Repository\PatientRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Doctrine\ORM\EntityManagerInterface;

#[AsLiveComponent('patients')]
class PatientsComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public bool $showForm = false;

    public function __construct(
        private PatientRepository $patientRepository,
        private EntityManagerInterface $entityManager
    ) {}

    public function getPatients(): array
    {
        return $this->patientRepository->findBy([], ['nom' => 'ASC']);
    }

    public function toggleForm(): void
    {
        $this->showForm = !$this->showForm;
    }
}
