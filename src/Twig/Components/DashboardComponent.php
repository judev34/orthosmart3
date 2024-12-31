<?php

namespace App\Twig\Components;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('dashboard')]
class DashboardComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $activeTab = 'patients';

    #[LiveAction]
    public function setActiveTab(#[LiveArg] string $tab): void
    {
        if (array_key_exists($tab, $this->getAvailableTabs())) {
            $this->activeTab = $tab;
        }
    }

    public function getAvailableTabs(): array
    {
        return [
            'accueil' => [
                'name' => 'Accueil',
                'icon' => 'fa-home'
            ],
            'patients' => [
                'name' => 'Patients',
                'icon' => 'fa-users'
            ],
            'rendez-vous' => [
                'name' => 'Rendez-vous',
                'icon' => 'fa-calendar-alt'
            ],
            'statistiques' => [
                'name' => 'Statistiques',
                'icon' => 'fa-chart-line'
            ],
        ];
    }
}
