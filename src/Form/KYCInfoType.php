<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\File;


class KYCInfoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('role', ChoiceType::class, [
                'label'   => 'Your Role',
                'choices' => [
                    'Client'       => 'CLIENT',
                    'Investisseur' => 'INVESTISSEUR',
                    'Commerçant'   => 'COMMERCANT',
                    'Admin'        => 'ADMIN',
                ],
                'expanded'    => true,   
                'multiple'    => false,
                'constraints' => [
                    new NotBlank(['message' => 'Please select a role']),
                ],
            ])
            
            ->add('selfie', FileType::class, [
                'label'       => 'Profile Photo',
                'mapped'      => false,
                'required'    => false,
                'constraints' => [
                    new File([
                        'maxSize'          => '5M',
                        'mimeTypes'        => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Please upload a valid image (JPG, PNG, WEBP)',
                    ]),
                ],
            ])
            ->add('pieceIdentiteFile', FileType::class, [
                'label'       => 'ID Document',
                'mapped'      => false,
                'required'    => false,
                'constraints' => [
                    new File([
                        'maxSize'          => '5M',
                        'mimeTypes'        => ['image/jpeg', 'image/png', 'application/pdf'],
                        'mimeTypesMessage' => 'Please upload a valid file (JPG, PNG, PDF)',
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