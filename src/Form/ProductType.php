<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormError;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du produit',
                'attr' => ['placeholder' => 'Ex: Ledger Nano X', 'class' => 'form-control rounded-pill border-0 shadow-sm px-4'],
                'required' => false,
            ])
            ->add('brand', TextType::class, [
                'label' => 'Marque',
                'attr' => ['placeholder' => 'Ex: Ledger', 'class' => 'form-control rounded-pill border-0 shadow-sm px-4'],
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['placeholder' => 'Détails du produit...', 'rows' => 4, 'class' => 'form-control rounded-lg border-0 shadow-sm px-4'],
                'required' => false,
            ])
            ->add('price', NumberType::class, [
                'label' => 'Prix Initial (BTC)',
                'attr' => ['placeholder' => '0.00', 'class' => 'form-control rounded-pill border-0 shadow-sm px-4'],
                'required' => false,
            ])
            ->add('discountPrice', NumberType::class, [
                'label' => 'Prix Promotionnel (BTC)',
                'attr' => ['placeholder' => '0.00', 'class' => 'form-control rounded-pill border-0 shadow-sm px-4'],
                'required' => false,
            ])
            ->add('stock', NumberType::class, [
                'label' => 'Quantité en stock',
                'attr' => ['placeholder' => 'Ex: 100', 'class' => 'form-control rounded-pill border-0 shadow-sm px-4'],
                'required' => false,
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices'  => [
                    'Subscription' => 'subscription',
                    'Électronique' => 'electronic',
                    'Véhicule' => 'vehicule',
                ],
                'attr' => ['class' => 'form-select rounded-pill border-0 shadow-sm px-4'],
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut de vente',
                'choices'  => [
                    'Disponible' => 'available',
                    'Bientôt disponible' => 'coming_soon',
                    'Indisponible' => 'out_of_stock',
                    'Archivé' => 'archived',
                ],
                'attr' => ['class' => 'form-select rounded-pill border-0 shadow-sm px-4'],
                'required' => false,
            ])
            ->add('avgRating', NumberType::class, [
                'label' => 'Note moyenne (0-5)',
                'attr' => ['placeholder' => '4.5', 'step' => '0.1', 'class' => 'form-control rounded-pill border-0 shadow-sm px-4'],
                'required' => false,
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image du produit',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger une image valide (JPEG, PNG, WEBP)',
                    ])
                ],
                'attr' => ['class' => 'form-control rounded-pill border-0 shadow-sm px-4'],
            ])
        ;

        // Validation: discountPrice must not exceed price
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $product = $event->getData();
            $form = $event->getForm();

            if ($product->getDiscountPrice() !== null && $product->getPrice() !== null) {
                if ($product->getDiscountPrice() >= $product->getPrice()) {
                    $form->get('discountPrice')->addError(
                        new FormError('Le prix promotionnel doit être inférieur au prix initial.')
                    );
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
