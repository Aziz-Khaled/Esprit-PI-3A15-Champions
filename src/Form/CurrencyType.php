<?php

namespace App\Form;

use App\Entity\Currency;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class CurrencyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Currency Code (ex: BTC, USD)',
                'attr' => ['class' => 'form-control']
            ])
            ->add('nom', TextType::class, [
                'label' => 'Full Name',
                'attr' => ['class' => 'form-control']
            ])
            ->add('type_currency', ChoiceType::class, [
                'label' => 'Category',
                'choices'  => [
                    'Fiat' => 'fiat',
                    'Crypto' => 'crypto',
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('is_trading', CheckboxType::class, [
                'label' => 'Enable Trading?',
                'required' => false,
                'attr' => ['class' => 'custom-control-input'],
                'label_attr' => ['class' => 'custom-control-label']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Currency::class,
        ]);
    }
}