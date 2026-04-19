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

class AdminEditUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label'       => 'First Name',
                'constraints' => [new NotBlank()],
            ])
            ->add('nom', TextType::class, [
                'label'       => 'Last Name',
                'constraints' => [new NotBlank()],
            ])
            ->add('email', EmailType::class, [
                'label'       => 'Email',
                'constraints' => [new NotBlank(), new Email()],
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Phone',
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