<?php

namespace App\Controller\frontOffice;

use App\Entity\CreditCard;
use App\Entity\Utilisateur;
use App\Form\CreditCardType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/credit-card')]
class CreditCardController extends AbstractController
{
    #[Route('/new', name: 'app_card_new', methods: ['POST'])]
public function new(Request $request, EntityManagerInterface $em): Response
{
    $card = new CreditCard();
    $user = $this->getUser() ?: $em->getRepository(Utilisateur::class)->find(1);
    
    $form = $this->createForm(CreditCardType::class, $card, ['is_edit' => false]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $card->setUtilisateur($user);
        $card->setStatut('ACTIVE');
        $em->persist($card);
        $em->flush();

        $this->addFlash('success', 'Card added successfully.');
    } else {
        // Si erreurs (ex: pas 16 chiffres), on les passe en flash
        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('danger', $error->getMessage());
        }
    }

    // On redirige TOUJOURS vers le wallet
    return $this->redirectToRoute('app_wallet_index');
}

    #[Route('/{id}/edit', name: 'app_card_edit', methods: ['POST'])]
    public function edit(CreditCard $card, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CreditCardType::class, $card, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Card updated successfully.');
        } else {
            // Extraction précise des erreurs de validation (ex: date d'expiration)
            $errors = $form->getErrors(true);
            
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    // Utilisation du tag 'danger' pour correspondre à alert-danger (rouge)
                    $this->addFlash('danger', $error->getMessage());
                }
            } else {
                $this->addFlash('danger', 'An error occurred while updating the card.');
            }
        }

        return $this->redirectToRoute('app_wallet_index'); 
    }

    #[Route('/{id}/delete', name: 'app_card_delete', methods: ['POST', 'GET'])]
    public function delete(CreditCard $card, EntityManagerInterface $em): Response
    {
        // Désactivation de la carte (Soft delete) pour le projet Champions
        $card->setStatut('DELETED');
        $em->flush();

        $this->addFlash('info', 'The card has been removed.');
        return $this->redirectToRoute('app_wallet_index');
    }
}