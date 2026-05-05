<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

class GeneralInfoType extends AbstractType
{
   public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label' => 'First Name',
                'attr'  => ['placeholder' => 'Alex'],
                'constraints' => [
                    new NotBlank(['message' => 'First name is required']),
                    new Length([
                        'min'        => 2,
                        'minMessage' => 'First name must be at least 2 characters',
                        'max'        => 50,
                        'maxMessage' => 'First name cannot exceed 50 characters',
                    ]),
                ],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Last Name',
                'attr'  => ['placeholder' => 'Morgan'],
                'constraints' => [
                    new NotBlank(['message' => 'Last name is required']),
                    new Length([
                        'min'        => 2,
                        'minMessage' => 'Last name must be at least 2 characters',
                        'max'        => 50,
                        'maxMessage' => 'Last name cannot exceed 50 characters',
                    ]),
                ],
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Phone Number',
                'attr'  => ['placeholder' => '+216 XX XXX XXX'],
                'constraints' => [
                    new NotBlank(['message' => 'Phone number is required']),
                    new Regex([
                        'pattern' => '/^[+]?[0-9]{8,15}$/',
                        'message' => 'Please enter a valid phone number',
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr'  => ['placeholder' => 'you@example.com'],
                'constraints' => [
                    new NotBlank(['message' => 'Email is required']),
                    new Email(['message'   => 'Please enter a valid email address']),
                ],
            ])
            ->add('motDePasse', PasswordType::class, [
                'label' => 'Password',
                'attr'  => ['placeholder' => 'Min. 8 characters'],
                'constraints' => [
                    new NotBlank(['message' => 'Password is required']),
                    new Length([
                        'min'        => 8,
                        'minMessage' => 'Password must be at least 8 characters',
                    ]),
                    new Regex([
                        'pattern' => '/[A-Z]/',
                        'message' => 'Password must contain at least one uppercase letter',
                    ]),
                    new Regex([
                        'pattern' => '/[0-9]/',
                        'message' => 'Password must contain at least one number',
                    ]),
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
