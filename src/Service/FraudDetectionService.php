<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Repository\TransactionRepository;
use App\Entity\Transaction;

class FraudDetectionService
{
    private $client;
    private $repository;
    private $apiUrl;

    public function __construct(HttpClientInterface $client, TransactionRepository $repository, string $fraudApiUrl)
    {
        $this->client = $client;
        $this->repository = $repository;
        $this->apiUrl = $fraudApiUrl;
    }

    /**
     * PARTIE STATIQUE : Conservée pour l'affichage prédictible
     */
    public function verifyTransaction(int $transactionId): array
    {
        $data = [
            'is_same_card_repeat'        => 1, 
            'montant_category'           => 2,
            'consecutive_card_recharges' => 2,
            'trading_flow_signal'        => 0,
            'daily_internal_transfers'   => 2,
            'daily_conversion_count'     => 6 
        ];

        try {
            $response = $this->client->request('POST', $this->apiUrl, ['json' => $data, 'timeout' => 2]);
            return $response->toArray();
        } catch (\Exception $e) {
            return ['fraud_alert' => false, 'percentage' => 0, 'error' => 'IA Offline'];
        }
    }

    /**
     * PARTIE DYNAMIQUE : Avec explication des motifs (Reasons)
     */
public function getGlobalFraudReport(array $transactions): array
{
    $fraudMap = [];

    foreach ($transactions as $t) {
        $user = null;
        if ($t->getWalletSource()) {
            $user = $t->getWalletSource()->getUtilisateur();
        } elseif ($t->getCreditCard()) {
            $user = $t->getCreditCard()->getUtilisateur();
        }

        if (!$user) continue;

        $date  = $t->getDateTransaction() ?? new \DateTime();
        $start = (clone $date)->setTime(0, 0, 0);
        $end   = (clone $date)->setTime(23, 59, 59);

        // Récupération des transactions du jour via QueryBuilder
        $allUserTransactionsToday = $this->repository->createQueryBuilder('tr')
            ->leftJoin('tr.walletSource', 'ws')
            ->leftJoin('tr.walletDestination', 'wd')
            ->leftJoin('tr.creditCard', 'c')
            ->where('ws.utilisateur = :u OR c.utilisateur = :u')
            ->andWhere('tr.dateTransaction BETWEEN :start AND :end')
            ->setParameter('u', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        $dailyConversions         = 0;
        $dailyInternalTransfers   = 0;
        $consecutiveCardRecharges = 0;
        $tradingFlowSignal        = 1;

        foreach ($allUserTransactionsToday as $todayT) {
            if ($todayT->getCreditCard() !== null) {
                $consecutiveCardRecharges++;
            }
            if ($todayT->getWalletSource() && $todayT->getWalletDestination()) {
                if ($todayT->getWalletSource()->getUtilisateur() === $todayT->getWalletDestination()->getUtilisateur()) {
                    $dailyInternalTransfers++;
                }
            }
            if ($todayT->getConversion() !== null || strtoupper($todayT->getType()) === 'CONVERSION') {
                $dailyConversions++;
            }
        }

        if ($t->getWalletDestination() && $t->getWalletDestination()->getTypeWallet() === 'trading') {
            if ($dailyConversions === 0) $tradingFlowSignal = 0;
        }

        $montant         = $t->getMontant();
        $montantCategory = ($montant > 5000) ? 2 : (($montant >= 1000) ? 1 : 0);

        // Préparation des données pour l'IA
        $dataForAI = [
            'is_same_card_repeat'        => ($consecutiveCardRecharges > 1) ? 1 : 0,
            'montant_category'           => $montantCategory,
            'consecutive_card_recharges' => $consecutiveCardRecharges,
            'trading_flow_signal'        => $tradingFlowSignal,
            'daily_internal_transfers'   => $dailyInternalTransfers,
            'daily_conversion_count'     => $dailyConversions
        ];

        try {
            $response = $this->client->request('POST', $this->apiUrl, ['json' => $dataForAI]);
            $result   = $response->toArray();

            if (isset($result['fraud_alert']) && $result['fraud_alert'] === true) {
                $userName = strtoupper($user->getNom() ?? 'Unknown') . ' ' . ($user->getPrenom() ?? '');
                $prob     = $result['percentage'] ?? 0;

                // --- GÉNÉRATION DE L'EXPLICATION (REASON) ---
                $reasons = [];
                if ($montantCategory == 2) {
                    $reasons[] = "High amount (>5000)";
                }
                if ($consecutiveCardRecharges > 2) {
                    $reasons[] = "Too many card recharges ($consecutiveCardRecharges)";
                }
                if ($dailyConversions > 5) {
                    $reasons[] = "Many conversions today ($dailyConversions)";
                }
                if ($tradingFlowSignal == 0) {
                    $reasons[] = "Bad trading flow";
                }
                if ($dailyInternalTransfers > 3) {
                    $reasons[] = "Too many internal transfers ($dailyInternalTransfers)";
                }

                $reasonString = !empty($reasons) ? implode(", ", $reasons) : "Unusual activity detected";

                if (!isset($fraudMap[$userName]) || $prob > $fraudMap[$userName]['highestProbability']) {
                    $fraudMap[$userName] = [
                        'userName'           => $userName,
                        'highestProbability' => $prob,
                        'reason'             => $reasonString
                    ];
                }
            }
        } catch (\Exception $e) {
            continue;
        }
    }

    return $fraudMap;
}
}