<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use App\Entity\Utilisateur;

class LoginType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr'  => ['placeholder' => 'Enter your email'],
                'constraints' => [
                    new NotBlank(['message' => 'Email is required']),
                    new Email(['message'   => 'Please enter a valid email address']),
                ],
            ])
            ->add('motDePasse', PasswordType::class, [
                'label' => 'Password',
                'attr'  => ['placeholder' => 'Enter your password'],
                'constraints' => [
                    new NotBlank(['message' => 'Password is required']),
                    new Length([
                        'min'        => 4,
                        'minMessage' => 'Password must be at least 4 characters',
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