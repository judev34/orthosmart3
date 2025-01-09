<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/practitioner/dashboard')]
#[IsGranted('ROLE_PRACTITIONER')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'practitioner_dashboard')]
    public function index(): Response
    {
        return $this->render('dashboard/home.html.twig');
    }
}
