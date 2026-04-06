<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

class AdminEditUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
           ->add('prenom', TextType::class, [
    'label'       => 'First Name',
    'constraints' => [
        new NotBlank(['message' => 'First name is required.']),
        new Length(['min' => 2, 'max' => 50,
            'minMessage' => 'First name must be at least 2 characters.',
            'maxMessage' => 'First name cannot exceed 50 characters.',
        ]),
        new Regex([
            'pattern' => '/^[a-zA-ZÀ-ÿ\s\-]+$/',
            'message' => 'First name can only contain letters, spaces, and hyphens.',
        ]),
    ],
])
->add('nom', TextType::class, [
    'label'       => 'Last Name',
    'constraints' => [
        new NotBlank(['message' => 'Last name is required.']),
        new Length(['min' => 2, 'max' => 50,
            'minMessage' => 'Last name must be at least 2 characters.',
            'maxMessage' => 'Last name cannot exceed 50 characters.',
        ]),
        new Regex([
            'pattern' => '/^[a-zA-ZÀ-ÿ\s\-]+$/',
            'message' => 'Last name can only contain letters, spaces, and hyphens.',
        ]),
    ],
])
->add('email', EmailType::class, [
    'label'       => 'Email',
    'constraints' => [
        new NotBlank(['message' => 'Email is required.']),
        new Email(['message' => 'Please enter a valid email address.']),
    ],
])
->add('telephone', TextType::class, [
    'label'       => 'Phone',
    'constraints' => [
        new Regex([
            'pattern' => '/^\+?[0-9]{8,15}$/',
            'message' => 'Please enter a valid phone number (8–15 digits, optional + prefix).',
        ]),
    ],
])
            ->add('role', ChoiceType::class, [
                'label'   => 'Role',
                'choices' => [
                    'Client'       => 'CLIENT',
                    'Investisseur' => 'INVESTISSEUR',
                    'Commerçant'   => 'COMMERCANT',
                    'Admin'        => 'ADMIN',
                ],
            ])
            ->add('statut', ChoiceType::class, [
                'label'   => 'Status',
                'choices' => [
                    'Pending' => 'pending',
                    'Active'  => 'active',
                    'Banned'  => 'banned',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
        ]);
    }
}