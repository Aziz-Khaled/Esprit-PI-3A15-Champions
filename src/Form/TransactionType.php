<?php

namespace App\Form;

use App\Entity\Currency;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class TransactionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ribSource', TextType::class, [
                'constraints' => [new NotBlank(['message' => 'Source RIB is required'])]
            ])
            ->add('ribDestination', TextType::class, [
                'constraints' => [new NotBlank(['message' => 'Destination RIB is required'])]
            ])
            ->add('montant', NumberType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Positive(['message' => 'The amount must be greater than 0'])
                ]
            ])
            ->add('currency', EntityType::class, [
                'class' => Currency::class,
                'choice_label' => 'nom', 
                'placeholder' => 'Select Currency',
                'attr' => ['class' => 'form-control-custom']
            ])
            ->add('type', HiddenType::class, [
                'data' => 'transfert'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
        ]);
    }
}