<?php

namespace App\Controller\frontOffice;

use App\Entity\Trade;
use App\Entity\Wallet;
use App\Entity\Asset;
use App\Entity\WalletCurrency;
use App\Entity\Currency;
use App\Repository\AssetRepository;
use App\Repository\TradeRepository;
use App\Repository\WalletRepository;
use App\Repository\WalletCurrencyRepository;
use App\Repository\CurrencyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/trading', name: 'app_trading_')]
class TradingDashboardController extends AbstractController
{
    private string $binanceBase = 'https://api.binance.com/api/v3';
    private string $newsApiKey = '';
    private string $grokApiKey = '';

    public function __construct(
        private HttpClientInterface      $httpClient,
        private EntityManagerInterface   $em,
        private TradeRepository          $tradeRepo,
        private WalletRepository         $walletRepo,
        private WalletCurrencyRepository $walletCurrencyRepo,
        private CurrencyRepository       $currencyRepo,
        private AssetRepository          $assetRepo,
    ) {}

    // ─────────────────────────────────────────
    // 1. DASHBOARD
    // ─────────────────────────────────────────
    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $userId = 1;
        
        // Récupérer uniquement les wallets de type 'trading' ou 'crypto'
        $wallets = $this->walletRepo->findBy([
            'utilisateur' => $userId,
            'typeWallet' => ['trading']
        ]);
        
    if (empty($wallets)) {
        $defaultWallet = new Wallet();
        $defaultWallet->setUtilisateur($userId);
        $defaultWallet->setSolde('0');
        $defaultWallet->setRib('TRADING-' . uniqid());
        $defaultWallet->setTypeWallet('trading');
        $defaultWallet->setStatut('actif');
        $defaultWallet->setDateCreation(new \DateTime());
        $defaultWallet->setDateDerniereModification(new \DateTime());
        $this->em->persist($defaultWallet);
        $this->em->flush();
        $wallets = [$defaultWallet];
    }
        // S'assurer que la devise USDT existe (crypto uniquement)
        $usdtCurrency = $this->currencyRepo->findOneBy([
            'code' => 'USDT',
            'type_currency' => 'crypto'
        ]);
        
        if (!$usdtCurrency) {
            $usdtCurrency = new Currency();
            $usdtCurrency->setCode('USDT');
            $usdtCurrency->setNom('Tether USD');
            $usdtCurrency->setType_currency('crypto');
            $usdtCurrency->setIs_trading(true);
            $this->em->persist($usdtCurrency);
            $this->em->flush();
        }
        
        // Créer WalletCurrency USDT pour chaque wallet crypto si nécessaire
        foreach ($wallets as $wallet) {
            $walletUsdt = $this->walletCurrencyRepo->findOneBy([
                'wallet' => $wallet,
                'currency' => $usdtCurrency
            ]);
            
            if (!$walletUsdt) {
                $walletUsdt = new WalletCurrency();
                $walletUsdt->setWallet($wallet);
                $walletUsdt->setCurrency($usdtCurrency);
                $walletUsdt->setNomCurrency('USDT');
                $walletUsdt->setSolde(10000);
                $this->em->persist($walletUsdt);
            }
        }
        $this->em->flush();
        
        // Récupérer les soldes en USDT pour chaque wallet
        $walletBalances = [];
        $totalSolde = 0;
        
        foreach ($wallets as $wallet) {
            $usdtBalance = 0;
            $walletCurrency = $this->walletCurrencyRepo->findOneBy([
                'wallet' => $wallet,
                'currency' => $usdtCurrency
            ]);
            if ($walletCurrency) {
                $usdtBalance = $walletCurrency->getSolde() ?? 0;
            }
            $walletBalances[] = [
                'id' => $wallet->getIdWallet(),
                'rib' => $wallet->getRib(),
                'solde' => $usdtBalance,
                'type' => $wallet->getTypeWallet(),
                'statut' => $wallet->getStatut(),
            ];
            $totalSolde += $usdtBalance;
        }
        
        $trades = $this->tradeRepo->findBy(['userId' => $userId], ['createdAt' => 'DESC'], 10);
        $pendingOrders = $this->tradeRepo->findBy([
            'userId' => $userId,
            'orderMode' => 'LIMIT',
            'status' => 'PENDING'
        ]);
        $assets = $this->assetRepo->findBy(['status' => 'active'], ['symbol' => 'ASC']);
        $symbols = array_map(fn($a) => strtoupper($a->getSymbol()) . 'USDT', $assets);

        return $this->render('trading_dashboard/dashboard.html.twig', [
            'totalSolde'          => $totalSolde,
            'wallets'             => $walletBalances,
            'trades'              => $trades,
            'symbols'             => $symbols,
            'assets'              => $assets,
            'pendingLimitOrders'  => $pendingOrders,
        ]);
    }

    // ─────────────────────────────────────────
    // 2. RÉCUPÉRER TOUS LES WALLETS CRYPTO (RIBs)
    // ─────────────────────────────────────────
    #[Route('/api/wallets', name: 'api_wallets', methods: ['GET'])]
    public function getWallets(): JsonResponse
    {
        $userId = 1;
        $wallets = $this->walletRepo->findBy([
            'utilisateur' => $userId,
            'typeWallet' => ['trading']
        ]);
        
        $usdtCurrency = $this->currencyRepo->findOneBy([
            'code' => 'USDT',
            'type_currency' => 'crypto'
        ]);
        
        $data = [];
        foreach ($wallets as $wallet) {
            $usdtBalance = 0;
            if ($usdtCurrency) {
                $walletCurrency = $this->walletCurrencyRepo->findOneBy([
                    'wallet' => $wallet,
                    'currency' => $usdtCurrency
                ]);
                if ($walletCurrency) {
                    $usdtBalance = $walletCurrency->getSolde() ?? 0;
                }
            }
            
            $data[] = [
                'id'        => $wallet->getIdWallet(),
                'rib'       => $wallet->getRib(),
                'solde'     => $usdtBalance,
                'type'      => $wallet->getTypeWallet(),
                'statut'    => $wallet->getStatut(),
            ];
        }
        return new JsonResponse($data);
    }

    // ─────────────────────────────────────────
    // 3. RÉCUPÉRER LE SOLDE D'UN WALLET SPÉCIFIQUE
    // ─────────────────────────────────────────
    #[Route('/api/wallet/{id}/balance', name: 'api_wallet_balance', methods: ['GET'])]
    public function getWalletBalance(int $id): JsonResponse
    {
        $userId = 1;
        $wallet = $this->walletRepo->findOneBy([
            'idWallet' => $id, 
            'utilisateur' => $userId,
            'typeWallet' => ['trading', 'crypto']
        ]);
        
        if (!$wallet) {
            return new JsonResponse(['error' => 'Wallet crypto non trouvé'], 404);
        }
        
        $usdtCurrency = $this->currencyRepo->findOneBy([
            'code' => 'USDT',
            'type_currency' => 'crypto'
        ]);
        $usdtBalance = 0;
        
        if ($usdtCurrency) {
            $walletCurrency = $this->walletCurrencyRepo->findOneBy([
                'wallet' => $wallet,
                'currency' => $usdtCurrency
            ]);
            if ($walletCurrency) {
                $usdtBalance = $walletCurrency->getSolde() ?? 0;
            }
        }
        
        return new JsonResponse([
            'id'        => $wallet->getIdWallet(),
            'rib'       => $wallet->getRib(),
            'solde'     => $usdtBalance,
        ]);
    }

    // ─────────────────────────────────────────
    // 4. RÉCUPÉRER TOUS LES WALLETS AVEC LEURS SOLDES
    // ─────────────────────────────────────────
    #[Route('/api/wallets-with-balance', name: 'api_wallets_balance', methods: ['GET'])]
    public function getWalletsWithBalance(): JsonResponse
    {
        $userId = 1;
        $wallets = $this->walletRepo->findBy([
            'utilisateur' => $userId,
            'typeWallet' => ['trading']
        ]);
        
        $usdtCurrency = $this->currencyRepo->findOneBy([
            'code' => 'USDT',
            'type_currency' => 'crypto'
        ]);
        
        $data = [];
        foreach ($wallets as $wallet) {
            $usdtBalance = 0;
            if ($usdtCurrency) {
                $walletCurrency = $this->walletCurrencyRepo->findOneBy([
                    'wallet' => $wallet,
                    'currency' => $usdtCurrency
                ]);
                if ($walletCurrency) {
                    $usdtBalance = $walletCurrency->getSolde() ?? 0;
                }
            }
            
            $data[] = [
                'id'         => $wallet->getIdWallet(),
                'rib'        => $wallet->getRib(),
                'type'       => $wallet->getTypeWallet(),
                'statut'     => $wallet->getStatut(),
                'solde_usdt' => $usdtBalance,
            ];
        }
        
        return new JsonResponse($data);
    }

    // ─────────────────────────────────────────
    // 5. RÉCUPÉRER LE SOLDE USDT D'UN WALLET SPÉCIFIQUE
    // ─────────────────────────────────────────
    #[Route('/api/wallet/{id}/usdt-balance', name: 'api_wallet_usdt_balance', methods: ['GET'])]
    public function getWalletUsdtBalanceById(int $id): JsonResponse
    {
        $userId = 1;
        $wallet = $this->walletRepo->findOneBy([
            'idWallet' => $id, 
            'utilisateur' => $userId,
            'typeWallet' => ['trading', ]
        ]);
        
        if (!$wallet) {
            return new JsonResponse(['error' => 'Wallet crypto non trouvé'], 404);
        }
        
        $usdtCurrency = $this->currencyRepo->findOneBy([
            'code' => 'USDT',
            'type_currency' => 'crypto'
        ]);
        $usdtBalance = 0;
        
        if ($usdtCurrency) {
            $walletCurrency = $this->walletCurrencyRepo->findOneBy([
                'wallet' => $wallet,
                'currency' => $usdtCurrency
            ]);
            if ($walletCurrency) {
                $usdtBalance = $walletCurrency->getSolde() ?? 0;
            }
        }
        
        return new JsonResponse([
            'wallet_id' => $wallet->getIdWallet(),
            'rib' => $wallet->getRib(),
            'type' => $wallet->getTypeWallet(),
            'usdt_balance' => $usdtBalance,
        ]);
    }

    // ─────────────────────────────────────────
    // 6. PRIX EN TEMPS RÉEL
    // ─────────────────────────────────────────
    #[Route('/api/prices', name: 'api_prices', methods: ['GET'])]
    public function getPrices(): JsonResponse
    {
        try {
            $response = $this->httpClient->request('GET', $this->binanceBase . '/ticker/24hr');
            $allData = $response->toArray();
            $assets = $this->assetRepo->findBy(['status' => 'active']);
            $dbSymbols = array_map(fn($a) => strtoupper($a->getSymbol()) . 'USDT', $assets);
            $prices = [];
            foreach ($allData as $ticker) {
                if (in_array($ticker['symbol'], $dbSymbols)) {
                    $prices[] = [
                        'symbol' => $ticker['symbol'],
                        'price'  => (float) $ticker['lastPrice'],
                        'change' => (float) $ticker['priceChangePercent'],
                        'high'   => (float) $ticker['highPrice'],
                        'low'    => (float) $ticker['lowPrice'],
                        'volume' => (float) $ticker['volume'],
                    ];
                }
            }
            return new JsonResponse($prices);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────
    // 7. NEWS
    // ─────────────────────────────────────────
    #[Route('/api/news', name: 'api_news', methods: ['GET'])]
    public function getNews(): JsonResponse
    {
        try {
            $response = $this->httpClient->request('GET', 'https://newsapi.org/v2/everything', [
                'query' => [
                    'q'        => 'cryptocurrency OR bitcoin OR ethereum OR blockchain',
                    'sortBy'   => 'publishedAt',
                    'language' => 'en',
                    'pageSize' => 10,
                    'apiKey'   => $this->newsApiKey,
                ],
            ]);
            $data = $response->toArray();
            $articles = array_map(fn($a) => [
                'title'       => $a['title'],
                'description' => $a['description'],
                'url'         => $a['url'],
                'image'       => $a['urlToImage'],
                'publishedAt' => $a['publishedAt'],
                'source'      => $a['source']['name'],
            ], $data['articles'] ?? []);
            return new JsonResponse($articles);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────
    // 8. CHATBOT
    // ─────────────────────────────────────────
    #[Route('/api/chatbot', name: 'api_chatbot', methods: ['POST'])]
    public function chatbot(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $message = trim($data['message'] ?? '');
        if (empty($message)) {
            return new JsonResponse(['error' => 'Message is required.'], 400);
        }
        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->grokApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model' => 'llama-3.3-70b-versatile',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a crypto expert. Be concise.'],
                        ['role' => 'user', 'content' => $message],
                    ],
                ],
            ]);
            $result = $response->toArray();
            $reply = $result['choices'][0]['message']['content'] ?? 'No response.';
            return new JsonResponse(['reply' => $reply]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'API Error: ' . $e->getMessage()], 400);
        }
    }

    // ─────────────────────────────────────────
    // 9. RECOMMANDATION
    // ─────────────────────────────────────────
    #[Route('/api/recommend/{symbol}', name: 'api_recommend', methods: ['GET'])]
    public function recommend(string $symbol): JsonResponse
    {
        $userId = 1;
        $totalSolde = $this->getTotalUsdtBalance($userId);
        $solde = $totalSolde ?: 10000;
        $sym = strtoupper(str_replace('USDT', '', $symbol));
        $binanceSymbol = $sym . 'USDT';
        try {
            $response = $this->httpClient->request('GET', $this->binanceBase . '/ticker/24hr', [
                'query' => ['symbol' => $binanceSymbol],
            ]);
            $coin = $response->toArray();
            $price = (float) $coin['lastPrice'];
            $change24h = (float) $coin['priceChangePercent'];
            $volatility = abs($change24h);
            $riskLevel = match(true) {
                $volatility > 15 => 'CRITICAL',
                $volatility > 8  => 'HIGH',
                $volatility > 4  => 'MEDIUM',
                default => 'LOW',
            };
            $maxInvestment = $solde * 0.1;
            $maxBuyQty = $price > 0 ? round($maxInvestment / $price, 6) : 0;
            return new JsonResponse([
                'symbol'        => $sym,
                'currentPrice'  => $price,
                'change24h'     => round($change24h, 2),
                'riskLevel'     => $riskLevel,
                'maxBuyQty'     => $maxBuyQty,
                'maxSellQty'    => $maxBuyQty,
                'solde'         => round($solde, 2),
                'advice'        => $riskLevel === 'LOW' ? 'Conditions favorables' : 'Prudence recommandée',
                'safeToTrade'   => $riskLevel !== 'CRITICAL',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Binance error: ' . $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────
    // 10. EXÉCUTION D'ORDRE (MARKET OU LIMIT)
    // ─────────────────────────────────────────
    #[Route('/api/execute-trade', name: 'api_execute_trade', methods: ['POST'])]
    public function executeTrade(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = 1;
        $tradeType = strtoupper($data['tradeType'] ?? $data['type'] ?? '');
        $symbol = strtoupper(str_replace('USDT', '', $data['symbol'] ?? ''));
        $quantity = (float) ($data['quantity'] ?? 0);
        $orderMode = strtoupper($data['orderMode'] ?? 'MARKET');
        $targetPrice = isset($data['targetPrice']) ? (float) $data['targetPrice'] : null;
        $walletId = isset($data['walletId']) ? (int) $data['walletId'] : null;
        
        if (!$symbol || $quantity <= 0) {
            return new JsonResponse(['error' => 'Paramètres invalides'], 400);
        }
        
        $wallet = $this->getUserWallet($userId, $walletId);
        if (!$wallet) {
            return new JsonResponse(['error' => 'Wallet crypto non trouvé'], 404);
        }
        
        $asset = $this->assetRepo->findOneBy(['symbol' => $symbol]);
        if (!$asset) {
            return new JsonResponse(['error' => "Asset '$symbol' introuvable"], 404);
        }
        
        $currentBalance = $this->getWalletUsdtBalance($wallet);
        $currentPrice = $this->getCurrentPrice($symbol);
        
        if ($orderMode === 'LIMIT') {
            if (!$targetPrice || $targetPrice <= 0) {
                return new JsonResponse(['error' => 'Prix cible requis'], 400);
            }
            
            $trade = new Trade();
            $trade->setUserId($userId);
            $trade->setAssetId($asset->getId());
            $trade->setQuantity($quantity);
            $trade->setTradeType($tradeType);
            $trade->setOrderMode('LIMIT');
            $trade->setStatus('PENDING');
            $trade->setPrice((string) $targetPrice);
            $trade->setCreatedAt(new \DateTime());
            $this->em->persist($trade);
            $this->em->flush();
            
            return new JsonResponse([
                'success'    => true,
                'message'    => "Ordre LIMIT enregistré : $tradeType $quantity $symbol @ \$$targetPrice",
                'status'     => 'PENDING',
                'tradeId'    => $trade->getId(),
                'newBalance' => $currentBalance,
                'symbol'     => $symbol,
                'tradeType'  => $tradeType,
                'quantity'   => $quantity,
                'price'      => $targetPrice,
                'walletId'   => $wallet->getIdWallet(),
                'walletRib'  => $wallet->getRib(),
            ]);
        }
        
        if (!$currentPrice) {
            return new JsonResponse(['error' => 'Prix actuel introuvable'], 500);
        }
        
        $total = $currentPrice * $quantity;
        $usdtCurrency = $this->getUsdtCurrency();
        $walletUsdt = $this->walletCurrencyRepo->findOneBy([
            'wallet' => $wallet,
            'currency' => $usdtCurrency
        ]);
        
        if (!$walletUsdt) {
            return new JsonResponse(['error' => 'Wallet USDT non configuré'], 500);
        }
        
        if ($tradeType === 'BUY') {
            if ($walletUsdt->getSolde() < $total) {
                return new JsonResponse(['error' => 'Solde USDT insuffisant'], 400);
            }
            $walletUsdt->setSolde($walletUsdt->getSolde() - $total);
        } else {
            $walletUsdt->setSolde($walletUsdt->getSolde() + $total);
        }
        
        $trade = new Trade();
        $trade->setUserId($userId);
        $trade->setAssetId($asset->getId());
        $trade->setQuantity($quantity);
        $trade->setTradeType($tradeType);
        $trade->setOrderMode('MARKET');
        $trade->setStatus('COMPLETED');
        $trade->setPrice((string) $currentPrice);
        $trade->setCreatedAt(new \DateTime());
        $this->em->persist($trade);
        $this->em->flush();
        
        return new JsonResponse([
            'success'    => true,
            'tradeType'  => $tradeType,
            'symbol'     => $symbol,
            'quantity'   => $quantity,
            'price'      => $currentPrice,
            'total'      => $total,
            'newBalance' => $walletUsdt->getSolde(),
            'status'     => 'COMPLETED',
            'tradeId'    => $trade->getId(),
            'walletId'   => $wallet->getIdWallet(),
            'walletRib'  => $wallet->getRib(),
        ]);
    }

    // ─────────────────────────────────────────
    // 11. BOT : EXÉCUTION D'UN ORDRE LIMIT SPÉCIFIQUE
    // ─────────────────────────────────────────
    #[Route('/api/bot/check-execute', name: 'api_bot_check_execute', methods: ['POST'])]
    public function checkAndExecuteSpecificOrder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $orderId = $data['orderId'] ?? null;
        $currentPrice = (float) ($data['currentPrice'] ?? 0);
        
        if (!$orderId || $currentPrice <= 0) {
            return new JsonResponse(['error' => 'Paramètres invalides'], 400);
        }
        
        $userId = 1;
        $order = $this->tradeRepo->findOneBy([
            'id' => $orderId,
            'userId' => $userId,
            'orderMode' => 'LIMIT',
            'status' => 'PENDING'
        ]);
        
        if (!$order) {
            return new JsonResponse(['error' => 'Ordre non trouvé ou déjà exécuté'], 404);
        }
        
        $asset = $this->assetRepo->find($order->getAssetId());
        if (!$asset) {
            return new JsonResponse(['error' => 'Asset non trouvé'], 404);
        }
        
        $wallet = $this->walletRepo->find($order->getWalletId());
        if (!$wallet) {
            return new JsonResponse(['error' => 'Wallet non trouvé'], 404);
        }
        
        $targetPrice = (float) $order->getPrice();
        $tradeType = $order->getTradeType();
        $quantity = $order->getQuantity();
        $total = $currentPrice * $quantity;
        
        $usdtCurrency = $this->getUsdtCurrency();
        $walletUsdt = $this->walletCurrencyRepo->findOneBy([
            'wallet' => $wallet,
            'currency' => $usdtCurrency
        ]);
        
        if (!$walletUsdt) {
            return new JsonResponse(['error' => 'Wallet USDT non configuré'], 500);
        }
        
        $shouldExecute = ($tradeType === 'BUY' && $currentPrice <= $targetPrice) ||
                        ($tradeType === 'SELL' && $currentPrice >= $targetPrice);
        
        if (!$shouldExecute) {
            return new JsonResponse(['error' => 'Condition non remplie'], 400);
        }
        
        if ($tradeType === 'BUY') {
            if ($walletUsdt->getSolde() < $total) {
                return new JsonResponse(['error' => 'Solde USDT insuffisant'], 400);
            }
            $walletUsdt->setSolde($walletUsdt->getSolde() - $total);
        } else {
            $walletUsdt->setSolde($walletUsdt->getSolde() + $total);
        }
        
        $order->setStatus('COMPLETED');
        $this->em->flush();
        
        return new JsonResponse([
            'success'    => true,
            'orderId'    => $orderId,
            'tradeType'  => $tradeType,
            'quantity'   => $quantity,
            'price'      => $currentPrice,
            'newBalance' => $walletUsdt->getSolde(),
            'walletId'   => $wallet->getIdWallet(),
            'walletRib'  => $wallet->getRib(),
        ]);
    }

    // ─────────────────────────────────────────
    // 12. BOT : RÉCUPÉRER LES ORDRES PENDING
    // ─────────────────────────────────────────
    #[Route('/api/bot/pending-orders', name: 'api_bot_pending_orders', methods: ['GET'])]
    public function getBotPendingOrders(): JsonResponse
    {
        $userId = 1;
        $pendingOrders = $this->tradeRepo->findBy([
            'userId' => $userId,
            'orderMode' => 'LIMIT',
            'status' => 'PENDING'
        ]);
        
        $result = [];
        foreach ($pendingOrders as $order) {
            $asset = $this->assetRepo->find($order->getAssetId());
            if ($asset) {
                $result[] = [
                    'id'           => $order->getId(),
                    'symbol'       => $asset->getSymbol(),
                    'trade_type'   => $order->getTradeType(),
                    'quantity'     => (float) $order->getQuantity(),
                    'target_price' => (float) $order->getPrice(),
                    'created_at'   => $order->getCreatedAt()->format('Y-m-d H:i:s'),
                    'wallet_id'    => $order->getWalletId(),
                ];
            }
        }
        return new JsonResponse($result);
    }

    // ─────────────────────────────────────────
    // 13. BOT : STATISTIQUES RÉELLES
    // ─────────────────────────────────────────
    #[Route('/api/bot/stats/real', name: 'api_bot_stats_real', methods: ['GET'])]
    public function getRealBotStats(): JsonResponse
    {
        $userId = 1;
        $today = new \DateTime('today');
        
        $tradesCompleted = $this->tradeRepo->createQueryBuilder('t')
            ->select('count(t.id)')
            ->where('t.userId = :user')
            ->andWhere('t.status = :status')
            ->andWhere('t.createdAt >= :today')
            ->setParameter('user', $userId)
            ->setParameter('status', 'COMPLETED')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();
        
        $tradesPending = $this->tradeRepo->createQueryBuilder('t')
            ->select('count(t.id)')
            ->where('t.userId = :user')
            ->andWhere('t.orderMode = :mode')
            ->andWhere('t.status = :status')
            ->setParameter('user', $userId)
            ->setParameter('mode', 'LIMIT')
            ->setParameter('status', 'PENDING')
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse([
            'tradesToday'   => (int) $tradesCompleted,
            'pendingOrders' => (int) $tradesPending,
            'botStatus'     => true,
            'winRate'       => 65,
            'pnl'           => 12.50
        ]);
    }

    // ─────────────────────────────────────────
    // 14. BOT : VÉRIFICATION DE TOUS LES ORDRES LIMIT
    // ─────────────────────────────────────────
    #[Route('/api/bot/check-limit-orders', name: 'api_bot_check_limit', methods: ['POST'])]
    public function checkAndExecuteLimitOrders(): JsonResponse
    {
        $userId = 1;
        $pendingOrders = $this->tradeRepo->findBy([
            'userId' => $userId,
            'orderMode' => 'LIMIT',
            'status' => 'PENDING'
        ]);
        $executedOrders = [];
        $errors = [];
        foreach ($pendingOrders as $order) {
            $asset = $this->assetRepo->find($order->getAssetId());
            if (!$asset) continue;
            $symbol = $asset->getSymbol();
            $currentPrice = $this->getCurrentPrice($symbol);
            $targetPrice = (float) $order->getPrice();
            $tradeType = $order->getTradeType();
            if ($currentPrice <= 0) {
                $errors[] = "Prix invalide pour {$symbol}";
                continue;
            }
            $shouldExecute = ($tradeType === 'BUY' && $currentPrice <= $targetPrice) ||
                            ($tradeType === 'SELL' && $currentPrice >= $targetPrice);
            if ($shouldExecute) {
                $wallet = $this->walletRepo->find($order->getWalletId());
                if (!$wallet) {
                    $errors[] = "Wallet non trouvé";
                    continue;
                }
                $quantity = $order->getQuantity();
                $total = $currentPrice * $quantity;
                $usdtCurrency = $this->getUsdtCurrency();
                $walletUsdt = $this->walletCurrencyRepo->findOneBy([
                    'wallet' => $wallet,
                    'currency' => $usdtCurrency
                ]);
                if (!$walletUsdt) {
                    $errors[] = "Wallet USDT non trouvé";
                    continue;
                }
                if ($tradeType === 'BUY') {
                    if ($walletUsdt->getSolde() < $total) {
                        $errors[] = "Solde insuffisant pour exécuter l'ordre #{$order->getId()}";
                        continue;
                    }
                    $walletUsdt->setSolde($walletUsdt->getSolde() - $total);
                } else {
                    $walletUsdt->setSolde($walletUsdt->getSolde() + $total);
                }
                $order->setStatus('COMPLETED');
                $this->em->flush();
                $executedOrders[] = [
                    'orderId'    => $order->getId(),
                    'symbol'     => $symbol,
                    'tradeType'  => $tradeType,
                    'quantity'   => $quantity,
                    'price'      => $currentPrice,
                    'targetPrice'=> $targetPrice,
                    'newBalance' => $walletUsdt->getSolde(),
                    'walletId'   => $wallet->getIdWallet(),
                    'walletRib'  => $wallet->getRib(),
                ];
            }
        }
        return new JsonResponse([
            'executed'  => $executedOrders,
            'errors'    => $errors,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    // ─────────────────────────────────────────
    // 15. ANNULER UN ORDRE LIMIT
    // ─────────────────────────────────────────
    #[Route('/api/limit-order/{id}/cancel', name: 'api_cancel_limit_order', methods: ['DELETE'])]
    public function cancelLimitOrder(int $id): JsonResponse
    {
        $userId = 1;
        $order = $this->tradeRepo->findOneBy([
            'id' => $id,
            'userId' => $userId,
            'orderMode' => 'LIMIT',
            'status' => 'PENDING'
        ]);
        if (!$order) {
            return new JsonResponse(['error' => 'Ordre non trouvé'], 404);
        }
        $order->setStatus('CANCELLED');
        $this->em->flush();
        return new JsonResponse([
            'success' => true,
            'message' => 'Ordre annulé',
            'orderId' => $id,
        ]);
    }

    // ─────────────────────────────────────────
    // 16. HISTORIQUE DES TRADES
    // ─────────────────────────────────────────
   #[Route('/api/trades/history', name: 'api_history', methods: ['GET'])]
public function getHistory(): JsonResponse
{
    $userId = 1;
    $trades = $this->tradeRepo->findBy(['userId' => $userId], ['createdAt' => 'DESC']);
    
    $assetCache = [];
    
    $data = array_map(function($trade) use (&$assetCache) {
        // Récupérer le symbole de l'asset
        $symbol = 'N/A';
        $assetId = $trade->getAssetId();
        if ($assetId) {
            if (!isset($assetCache[$assetId])) {
                $asset = $this->assetRepo->find($assetId);
                $assetCache[$assetId] = $asset ? strtoupper($asset->getSymbol()) : 'N/A';
            }
            $symbol = $assetCache[$assetId];
        }
        
        return [
            'id'         => $trade->getId(),
            'symbol'     => $symbol,
            'trade_type' => $trade->getTradeType(),
            'order_mode' => $trade->getOrderMode(),
            'price'      => (float) $trade->getPrice(),
            'quantity'   => (float) $trade->getQuantity(),
            'status'     => $trade->getStatus(),
            'date'       => $trade->getCreatedAt() ? $trade->getCreatedAt()->format('Y-m-d H:i:s') : '-',
        ];
    }, $trades);
    
    return new JsonResponse($data);
}
    // ─────────────────────────────────────────
    // 17. STATISTIQUES BOT (SIMPLE)
    // ─────────────────────────────────────────
    #[Route('/api/bot/stats', name: 'api_bot_stats', methods: ['GET'])]
    public function getBotStats(): JsonResponse
    {
        $userId = 1;
        $pendingCount = $this->tradeRepo->count([
            'userId' => $userId,
            'orderMode' => 'LIMIT',
            'status' => 'PENDING'
        ]);
        $completedCount = $this->tradeRepo->count([
            'userId' => $userId,
            'status' => 'COMPLETED'
        ]);
        return new JsonResponse([
            'tradesToday'   => $completedCount,
            'pendingOrders' => $pendingCount,
            'botStatus'     => true,
            'winRate'       => 65,
            'pnl'           => 0,
        ]);
    }

    // ─────────────────────────────────────────
    // 18. INITIALISATION DES CRYPTOS (OPTIONNEL)
    // ─────────────────────────────────────────
    #[Route('/api/init-crypto-wallets', name: 'api_init_crypto', methods: ['GET'])]
    public function initCryptoWallets(): JsonResponse
    {
        $userId = 1;
        
        $cryptoWallets = $this->walletRepo->findBy([
            'utilisateur' => $userId,
            'typeWallet' => ['trading']
        ]);
        
        if (empty($cryptoWallets)) {
            $wallet = new Wallet();
            $wallet->setUtilisateur($userId);
            $wallet->setSolde('0');
            $wallet->setRib('CRYPTO-' . date('Ymd') . '-' . uniqid());
            $wallet->setTypeWallet('trading');
            $wallet->setStatut('actif');
            $wallet->setDateCreation(new \DateTime());
            $wallet->setDateDerniereModification(new \DateTime());
            $this->em->persist($wallet);
            $this->em->flush();
            
            $usdtCurrency = $this->getUsdtCurrency();
            $walletUsdt = new WalletCurrency();
            $walletUsdt->setWallet($wallet);
            $walletUsdt->setCurrency($usdtCurrency);
            $walletUsdt->setNomCurrency('USDT');
            $walletUsdt->setSolde(10000);
            $this->em->persist($walletUsdt);
            $this->em->flush();
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Wallet crypto créé avec succès',
                'wallet' => [
                    'id' => $wallet->getIdWallet(),
                    'rib' => $wallet->getRib(),
                    'type' => $wallet->getTypeWallet()
                ]
            ]);
        }
        
        return new JsonResponse([
            'success' => true,
            'message' => 'Wallet crypto déjà existant',
            'wallets' => array_map(fn($w) => [
                'id' => $w->getIdWallet(),
                'rib' => $w->getRib(),
                'type' => $w->getTypeWallet()
            ], $cryptoWallets)
        ]);
    }

    // ─────────────────────────────────────────
    // 19. LISTE DES CRYPTOS DISPONIBLES
    // ─────────────────────────────────────────
    #[Route('/api/crypto-currencies', name: 'api_crypto_currencies', methods: ['GET'])]
    public function getCryptoCurrencies(): JsonResponse
    {
        $cryptos = $this->currencyRepo->findBy(['type_currency' => 'crypto']);
        
        $data = [];
        foreach ($cryptos as $crypto) {
            $data[] = [
                'id' => $crypto->getId_currency(),
                'code' => $crypto->getCode(),
                'nom' => $crypto->getNom(),
                'is_trading' => $crypto->is_trading()
            ];
        }
        
        return new JsonResponse($data);
    }

    // ─────────────────────────────────────────
    // MÉTHODES PRIVÉES
    // ─────────────────────────────────────────
    
    private function getUserWallet(int $userId, ?int $walletId = null): ?Wallet
    {
        if ($walletId) {
            return $this->walletRepo->findOneBy([
                'idWallet' => $walletId, 
                'utilisateur' => $userId,
                'typeWallet' => ['trading']
            ]);
        }
        
        $wallets = $this->walletRepo->findBy([
            'utilisateur' => $userId,
            'typeWallet' => ['trading']
        ]);
        
        return !empty($wallets) ? $wallets[0] : null;
    }

    private function getWalletUsdtBalance(Wallet $wallet): float
    {
        $usdtCurrency = $this->getUsdtCurrency();
        $walletUsdt = $this->walletCurrencyRepo->findOneBy([
            'wallet' => $wallet,
            'currency' => $usdtCurrency
        ]);
        return $walletUsdt ? ($walletUsdt->getSolde() ?? 0) : 0;
    }

    private function getTotalUsdtBalance(int $userId): float
    {
        $wallets = $this->walletRepo->findBy([
            'utilisateur' => $userId,
            'typeWallet' => ['trading']
        ]);
        $total = 0;
        foreach ($wallets as $wallet) {
            $total += $this->getWalletUsdtBalance($wallet);
        }
        return $total;
    }

    private function getUsdtCurrency(): ?Currency
    {
        $usdt = $this->currencyRepo->findOneBy([
            'code' => 'USDT',
            'type_currency' => 'crypto'
        ]);
        
        if (!$usdt) {
            $usdt = new Currency();
            $usdt->setCode('USDT');
            $usdt->setNom('Tether USD');
            $usdt->setType_currency('crypto');
            $usdt->setIs_trading(true);
            $this->em->persist($usdt);
            $this->em->flush();
        }
        return $usdt;
    }

    private function getCurrentPrice(string $symbol): float
    {
        try {
            $binanceSymbol = $symbol . 'USDT';
            $response = $this->httpClient->request('GET', $this->binanceBase . '/ticker/price', [
                'query' => ['symbol' => $binanceSymbol],
                'timeout' => 5,
            ]);
            $data = $response->toArray();
            return (float) ($data['price'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }
}