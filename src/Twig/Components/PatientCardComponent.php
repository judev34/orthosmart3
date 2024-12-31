<?php

namespace App\Twig\Components;

use App\Entity\Patient;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('patient_card')]
class PatientCardComponent
{
    public Patient $patient;
}
