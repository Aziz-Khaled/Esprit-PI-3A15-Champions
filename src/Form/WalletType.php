<?php

namespace App\Form;

use App\Entity\Wallet;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WalletType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeWallet', ChoiceType::class, [
                'choices'  => [
                    'Fiat' => 'fiat',
                    'Crypto' => 'crypto',
                    'Trading' => 'trading',
                ],
            ])
            ->add('statut', ChoiceType::class, [
                'choices'  => [
                    'Actif' => 'actif',
                    'Bloqué' => 'bloque',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Wallet::class,
        ]);
    }
}