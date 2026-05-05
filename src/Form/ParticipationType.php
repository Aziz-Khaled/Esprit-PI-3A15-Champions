<?php

namespace App\Form;

use App\Entity\Participation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParticipationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut de la participation',
                'choices' => [
                    'Inscrit' => 'INSCRIT',
                    'En cours' => 'EN_COURS',
                    'Terminée' => 'TERMINEE',
                    'Annulée' => 'ANNULEE',
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('presence', CheckboxType::class, [
                'label' => 'Participant présent',
                'required' => false,
            ])
            ->add('note', NumberType::class, [
                'label' => 'Note (sur 20)',
                'required' => false,
                'scale' => 2,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 20,
                    'step' => 0.5
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Participation::class,
        ]);
    }
}
