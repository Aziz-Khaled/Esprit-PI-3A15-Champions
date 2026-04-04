<?php

namespace App\Controller\frontOffice;

use App\Form\TransactionType;
use App\Service\TransactionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TransactionController extends AbstractController
{
    #[Route('/transaction/execute', name: 'app_transaction_execute', methods: ['POST'])]
    public function execute(Request $request, TransactionManager $tm): Response
    {
        // Création du formulaire pour intercepter les données envoyées par la modale
        $form = $this->createForm(TransactionType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            
            // On récupère l'ID via l'entité Currency sélectionnée par l'utilisateur
            $currencyId = $data['currency']->getId(); 

            try {
                // Appel du service métier pour la logique FinTech/Blockchain
                $tm->execute(
                    $data['ribSource'],
                    $data['ribDestination'],
                    $data['montant'],
                    $data['type'], // Sera "transfert" car c'est un HiddenType avec valeur par défaut
                    $currencyId
                );

                $this->addFlash('success', 'Transfer completed successfully and secured on Blockchain!');
            } catch (\Exception $e) {
                // Gestion des erreurs métier (Solde insuffisant, etc.)
                $this->addFlash('error', 'Transaction Failed: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Invalid transaction data. Please check your inputs.');
        }

        // Redirection systématique vers la page des wallets
        return $this->redirectToRoute('app_wallet_index');
    }
}