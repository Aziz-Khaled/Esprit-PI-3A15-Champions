<?php

namespace App\Controller\frontOffice;

use App\Form\TransactionType;
use App\Service\TransactionManager;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TransactionController extends AbstractController
{
    
    #[Route('/transaction/execute', name: 'app_transaction_execute', methods: ['POST'])]
    public function execute(Request $request, TransactionManager $tm): Response
    {
        $form = $this->createForm(TransactionType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $currencyId = $data['currency']->getId(); 

            try {
                $tm->execute(
                    $data['ribSource'],
                    $data['ribDestination'],
                    $data['montant'],
                    $data['type'], 
                    $currencyId
                );

                $this->addFlash('success', 'Transfer completed successfully and secured on Blockchain!');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Transaction Failed: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Invalid transaction data.');
        }

        return $this->redirectToRoute('app_wallet_index');
    }


}