<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class PractitionerController extends AbstractController
{
    public function __construct()
    {
        // Constructeur vide pour éviter l'erreur de service
    }

    #[Route('/practitioner/login', name: 'practitioner_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request, RateLimiterFactory $loginAttemptsLimiter): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('practitioner_dashboard');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // Rate limiting seulement en cas d'erreur de connexion (POST)
        if ($request->isMethod('POST') && $error) {
            $limiter = $loginAttemptsLimiter->create($request->getClientIp());
            
            if (!$limiter->consume(1)->isAccepted()) {
                $this->addFlash('error', 'Trop de tentatives de connexion échouées. Veuillez réessayer dans 15 minutes.');
                return $this->render('practitioner/login.html.twig', [
                    'last_username' => '',
                    'error' => null,
                    'rate_limited' => true,
                ]);
            }
        }

        return $this->render('practitioner/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'rate_limited' => false,
        ]);
    }

    #[Route('/practitioner/register', name: 'practitioner_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier si l'email existe déjà
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $form->get('email')->getData()]);
            
            if ($existingUser) {
                $this->addFlash('error', 'Un compte existe déjà avec cet email.');
                return $this->redirectToRoute('practitioner_register');
            }

            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $user->setRoles(['ROLE_PRACTITIONER']);

            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('practitioner_login');
        }

        return $this->render('practitioner/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/practitioner/logout', name: 'practitioner_logout')]
    public function logout(): void
    {
        // Cette méthode peut rester vide,
        // elle sera interceptée par la configuration de sécurité
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
