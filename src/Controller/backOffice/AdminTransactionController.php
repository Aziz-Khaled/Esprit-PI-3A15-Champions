<?php

namespace App\Controller\backOffice;

use App\Service\BlockchainService;
use App\Service\FraudDetectionService;
use App\Repository\NotificationRepository;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminTransactionController extends AbstractController
{
    #[Route('/admin/transactions', name: 'app_admin_transactions', methods: ['GET'])]
    public function adminTransactions(
        Request $request, 
        TransactionRepository $repo, 
        BlockchainService $blockchainService,
        FraudDetectionService $fraudService, 
        NotificationRepository $notifRepo
    ): Response
    {
        $blockchainService->verifyIntegrity();

        $userName = $request->query->get('user_name');
        $type = $request->query->get('type');

        if ($userName || $type) {
            $transactions = $repo->findByFilters($userName, $type);
        } else {
            $transactions = $repo->findBy([], ['dateTransaction' => 'DESC']);
        }

      /* foreach ($transactions as $t) {
            $aiResult = $fraudService->verifyTransaction($t->getIdTransaction());
            
            $t->aiPrediction = [
                'is_fraud' => $aiResult['fraud_alert'] ?? false,
                'score'    => $aiResult['percentage'] ?? 0,
                'error'    => $aiResult['error'] ?? null
            ];
        }*/

            $aiPredictions = [];
foreach ($transactions as $t) {
    $aiResult = $fraudService->verifyTransaction($t->getIdTransaction());
    $aiPredictions[$t->getIdTransaction()] = [
        'is_fraud' => $aiResult['fraud_alert'] ?? false,
        'score'    => $aiResult['percentage'] ?? 0,
        'error'    => $aiResult['error'] ?? null
    ];
}

        $notifications = $notifRepo->findBy(['is_read' => [false, null]], ['created_at' => 'DESC']);
        $count = count($notifications);

        return $this->render('admin_panel/transactions.html.twig', [
            'transactions' => $transactions,
            'current_user_name' => $userName,
            'current_type' => $type,
            'notifications' => $notifications, 
            'unreadNotificationsCount' => $count 
        ]);
    }

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

    #[Route('/admin/fraud/analyze', name: 'admin_fraud_analyze')]
    public function analyze(TransactionRepository $repo, FraudDetectionService $fraudService): Response
    {
        $transactions = $repo->findAll(); 
        
        $report = $fraudService->getGlobalFraudReport($transactions);

        if (empty($report)) {
            $this->addFlash('info', 'Aucune activité suspecte détectée par l\'IA.');
        } else {
            $this->addFlash('fraud_report', json_encode(array_values($report)));
        }

        return $this->redirectToRoute('app_admin_transactions');
    }
}