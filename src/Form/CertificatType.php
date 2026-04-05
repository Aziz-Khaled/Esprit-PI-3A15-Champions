<?php

namespace App\Form;

use App\Entity\Certificat;
use App\Entity\Participation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CertificatType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('participation', EntityType::class, [
                'class' => Participation::class,
                'choice_label' => function (Participation $participation) {
                    $formationTitle = $participation->getFormation()?->getTitre() ?? 'Sans formation';
                    return sprintf('#%s — %s', $participation->getIdParticipation(), $formationTitle);
                },
                'label' => 'Participation',
                'placeholder' => 'Sélectionnez une participation',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('dateEmission', DateType::class, [
                'label' => 'Date d\'émission',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La date d\'émission est requise.']),
                ],
            ])
            ->add('mention', TextType::class, [
                'label' => 'Mention',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('urlFichier', UrlType::class, [
                'label' => 'URL du fichier',
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\Url(['message' => 'L\'URL doit être valide.']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Certificat::class,
        ]);
    }
}
