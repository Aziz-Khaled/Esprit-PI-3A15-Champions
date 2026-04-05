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
    /**
     * Action pour exécuter une transaction (Côté Client)
     */
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

    /**
     * Action pour afficher et filtrer les transactions (Côté Admin)
     */
    #[Route('/admin_panel/transactions', name: 'app_admin_transactions', methods: ['GET'])]
    public function adminTransactions(Request $request, TransactionRepository $repo): Response
    {
        // Récupération des paramètres de filtrage depuis la requête GET
        $userName = $request->query->get('user_name');
        $type = $request->query->get('type');

        // Si des filtres sont présents, on utilise la méthode personnalisée
        if ($userName || $type) {
            $transactions = $repo->findByFilters($userName, $type);
        } else {
            // Sinon, on affiche tout par ordre décroissant (comportement par défaut)
            $transactions = $repo->findBy([], ['dateTransaction' => 'DESC']);
        }

        // Vérifie que le chemin du template correspond bien à ton dossier (admin/ ou admin_panel/)
        return $this->render('admin_panel/transactions.html.twig', [
            'transactions' => $transactions,
            'current_user_name' => $userName, // Pour garder la valeur dans l'input après validation
            'current_type' => $type           // Pour garder la sélection dans le dropdown
        ]);
    }
}