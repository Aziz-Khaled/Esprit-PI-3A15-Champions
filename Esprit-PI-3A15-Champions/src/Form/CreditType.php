<?php
namespace App\Form;

use App\Entity\Credit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CreditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('montant', null, [
                'label' => "Montant du prêt",
                'attr' => ['placeholder' => 'Ex: 5000.00']
            ])
            ->add('devise', ChoiceType::class, [
                'choices'  => [
                    'TND' => 'TND',
                    'EUR' => 'EUR',
                    'USD' => 'USD',
                ],
                'label' => "Devise"
            ])
            ->add('taux', null, [
                'label' => "Taux d'intérêt (%)",
                'attr' => ['placeholder' => 'Ex: 5.5']
            ])
            ->add('duree', null, [
                'label' => "Durée (en mois)",
                'attr' => ['placeholder' => 'Ex: 12']
            ])
            ->add('description', TextareaType::class, [
                'label' => "Description de votre projet",
                'required' => false
            ])
            ->add('save', SubmitType::class, [ // [cite: 31]
                'label' => 'Soumettre la demande',
                'attr' => ['class' => 'nav-btn active'] // On réutilise ton style CSS
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Credit::class,
        ]);
    }
}