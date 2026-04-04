<?php
namespace App\Form;



use App\Entity\Projet;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ProjetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Nom du Projet',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Plateforme Fintech Innovante'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description détaillée',
                'attr' => [
                    'class' => 'form-control', 
                    'rows' => 5,
                    'placeholder' => 'Décrivez les objectifs de votre projet...'
                ]
            ])
            ->add('targetAmount', NumberType::class, [
                'label' => 'Objectif de Financement (DT)',
                'attr' => ['class' => 'form-control']
            ])
            ->add('secteur', ChoiceType::class, [
                'label' => 'Secteur d\'activité',
                'choices'  => [
                    'Agriculture' => 'Agriculture',
                    'Technologie' => 'Technologie',
                    'Santé' => 'Sante',
                    'Éducation' => 'Education',
                    'Finance / Fintech' => 'Fintech',
                    'Autre' => 'Autre',
                ],
                'attr' => ['class' => 'form-control select2']
            ])
            // Ajout du champ Date de Début
            ->add('startDate', DateType::class, [
                'label' => 'Date de lancement prévue',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'required' => false,
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date d\'échéance du financement',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            // Ajout du champ Image (pour l'URL de l'image ou un upload)
            ->add('imageUrl', TextType::class, [
                'label' => 'Lien vers l\'image du projet (URL)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'https://exemple.com/image.jpg'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Projet::class,
        ]);
    }
}