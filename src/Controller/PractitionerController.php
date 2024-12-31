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

#[Route('/practitioner', name: 'practitioner_')]
class PractitionerController extends AbstractController
{
    #[Route('/login', name: 'login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {

        if ($this->getUser()) {
            return $this->redirectToRoute('practitioner_dashboard');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('practitioner/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/register', name: 'register')]
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
                $form->get('email')->addError(new \Symfony\Component\Form\FormError('Cet email est déjà utilisé.'));
                
                return $this->render('practitioner/register.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }

            // Encode le mot de passe
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('password')->getData()
                )
            );

            // Définir le rôle praticien
            $user->setRoles(['ROLE_PRACTITIONER']);

            $entityManager->persist($user);
            $entityManager->flush();

            // Ajouter un message flash
            $this->addFlash(
                'success',
                'Votre compte a été créé avec succès ! Vous pouvez maintenant vous connecter.'
            );

            return $this->redirectToRoute('practitioner_login');
        }

        return $this->render('practitioner/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): void
    {
        // Cette méthode peut rester vide,
        // elle sera interceptée par la configuration de sécurité
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
