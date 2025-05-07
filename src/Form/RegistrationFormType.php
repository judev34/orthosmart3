<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-gray-700 mb-1'
                ],
                'attr' => [
                    'class' => 'w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 outline-none'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer un email',
                    ]),
                    new Email([
                        'message' => 'L\'email {{ value }} n\'est pas un email valide.',
                    ]),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les mots de passe doivent correspondre.',
                'required' => true,
                'first_options' => [
                    'label' => 'Mot de passe',
                    'label_attr' => [
                        'class' => 'block text-sm font-medium text-gray-700 mb-1'
                    ],
                    'attr' => [
                        'class' => 'w-full p-2 pr-10 border rounded focus:ring-2 focus:ring-blue-500 outline-none'
                    ]
                ],
                'second_options' => [
                    'label' => 'Répéter le mot de passe',
                    'label_attr' => [
                        'class' => 'block text-sm font-medium text-gray-700 mb-1'
                    ],
                    'attr' => [
                        'class' => 'w-full p-2 pr-10 border rounded focus:ring-2 focus:ring-blue-500 outline-none'
                    ]
                ],
                'mapped' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer un mot de passe',
                    ]),
                    new Length([
                        'min' => 12,
                        'minMessage' => 'Votre mot de passe doit contenir au moins {{ limit }} caractères',
                        'max' => 4096,
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d])[^\s]{12,}$/',
                        'message' => 'Votre mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial'
                    ])
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
