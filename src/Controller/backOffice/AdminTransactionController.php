<?php

namespace App\Controller\backOffice;

use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminTransactionController extends AbstractController
{
    /**
     * Affiche la liste des transactions avec filtrage AJAX
     * Route corrigée pour correspondre à ton test : /admin/transactions
     */
    #[Route('/admin/transactions', name: 'app_admin_transactions', methods: ['GET'])]
    public function adminTransactions(Request $request, TransactionRepository $repo): Response
    {
        // Récupération des paramètres de filtrage depuis la requête GET
        $userName = $request->query->get('user_name');
        $type = $request->query->get('type');

        // Si des filtres sont présents, on utilise la méthode personnalisée dans le Repository
        if ($userName || $type) {
            $transactions = $repo->findByFilters($userName, $type);
        } else {
            // Sinon, on affiche tout par ordre décroissant
            $transactions = $repo->findBy([], ['dateTransaction' => 'DESC']);
        }

        // Rendu du template
        return $this->render('admin_panel/transactions.html.twig', [
            'transactions' => $transactions,
            'current_user_name' => $userName,
            'current_type' => $type
        ]);
    }

    /**
     * Voir les détails d'une transaction spécifique
     */
    #[Route('/admin/transactions/{id}', name: 'app_admin_transaction_show', methods: ['GET'])]
    public function show(int $id, TransactionRepository $repo): Response
    {
        $transaction = $repo->find($id);

        if (!$transaction) {
            throw $this->createNotFoundException('Transaction not found.');
        }

        return $this->render('admin_panel/transaction_show.html.twig', [
            'transaction' => $transaction,
        ]);
    }
}