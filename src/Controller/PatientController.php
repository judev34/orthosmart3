<?php

namespace App\Controller;

use App\Entity\Patient;
use App\Form\PatientType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\PatientActivationService;
use App\Service\NotificationService;

#[Route('/practitioner/patients')]
class PatientController extends AbstractController
{
    #[Route('/', name: 'practitioner_patients_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $patients = $entityManager
            ->getRepository(Patient::class)
            ->findBy(['praticien' => $this->getUser()]);

        return $this->render('dashboard/patients/index.html.twig', [
            'patients' => $patients,
        ]);
    }

    #[Route('/new', name: 'practitioner_patient_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, PatientActivationService $activationService, NotificationService $notificationService): Response
    {
        $patient = new Patient();
        $form = $this->createForm(PatientType::class, $patient);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Le patient sera activé via email, pas de mot de passe initial
            $patient->setPraticien($this->getUser());
            $patient->setRoles(['ROLE_PATIENT']);

            $entityManager->persist($patient);
            $entityManager->flush();

            // Générer et envoyer le token d'activation
            $activationService->generateAndSendActivationToken($patient, $request);
            
            // Notifier le praticien
            $notificationService->notifyPatientRegistered($patient);

            $this->addFlash('success', 'Patient créé avec succès ! Un email d\'activation lui a été envoyé.');

            return $this->redirectToRoute('practitioner_patients_index');
        }

        return $this->render('dashboard/patients/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
