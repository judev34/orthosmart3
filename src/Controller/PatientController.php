<?php

namespace App\Controller;

use App\Entity\Patient;
use App\Form\PatientType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $patient = new Patient();
        $form = $this->createForm(PatientType::class, $patient);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $patient->setPraticien($this->getUser());
            
            // Générer un mot de passe aléatoire
            $plainPassword = bin2hex(random_bytes(4));
            $hashedPassword = $passwordHasher->hashPassword($patient, $plainPassword);
            $patient->setPassword($hashedPassword);
            $patient->setPlainPassword($plainPassword);
            $patient->setRoles(['ROLE_PATIENT']);

            $entityManager->persist($patient);
            $entityManager->flush();

            $this->addFlash('success', 'Patient créé avec succès ! Le mot de passe initial est : ' . $plainPassword);

            return $this->redirectToRoute('practitioner_patients_index');
        }

        return $this->render('dashboard/patients/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
