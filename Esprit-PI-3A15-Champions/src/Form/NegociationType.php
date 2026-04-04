<?php

namespace App\Form;

use App\Entity\Negociation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NegociationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('montant', NumberType::class, [
                'label' => 'MONTANT PROPOSÉ (DT)',
                'attr' => ['class' => 'form-control p-3 bg-light', 'placeholder' => 'Ex: 5000']
            ])
            ->add('taux_propose', NumberType::class, [
                'label' => 'TAUX D’INTÉRÊT PROPOSÉ (%)',
                'attr' => ['class' => 'form-control p-3 bg-light', 'placeholder' => 'Ex: 2.5']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Negociation::class,
        ]);
    }
}