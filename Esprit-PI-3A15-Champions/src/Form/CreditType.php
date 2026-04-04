<?php

namespace App\Form;

use App\Entity\Credit;
use App\Entity\Projet;
use App\Entity\Utilisateur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CreditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('projet', EntityType::class, [
                'class' => Projet::class,
                // CORRECTION ICI : 'title' au lieu de 'nom_projet'
                'choice_label' => 'title', 
                'label' => "Sélectionner le projet",
                'attr' => ['class' => 'form-control']
            ])
            
            ->add('montant', NumberType::class, [
                'label' => "Montant du prêt",
                'attr' => ['class' => 'form-control']
            ])
            ->add('devise', ChoiceType::class, [
                'choices'  => [
                    'TND' => 'TND',
                    'EUR' => 'EUR',
                    'USD' => 'USD',
                ],
                'label' => "Devise",
                'attr' => ['class' => 'form-control']
            ])
            ->add('taux', NumberType::class, [
                'label' => "Taux d'intérêt (%)",
                'attr' => ['class' => 'form-control']
            ])
            ->add('duree', IntegerType::class, [
                'label' => "Durée (en mois)",
                'attr' => ['class' => 'form-control']
            ])
            ->add('description', TextareaType::class, [
                'label' => "Description",
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Credit::class,
        ]);
    }
}