<?php

namespace App\Controller\frontOffice;

use App\Repository\AssetRepository;
use App\Repository\TradeRepository;
use App\Repository\WalletRepository;
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
    // Utilisation de l'API publique de Binance (pas besoin de map complexe)
    private string $binanceBase = 'https://api.binance.com/api/v3';
    private string $newsApiKey = '';
    private string $grokApiKey = '';

    public function __construct(
        private HttpClientInterface    $httpClient,
        private EntityManagerInterface $em,
        private TradeRepository        $tradeRepo,
    ) {}

    // ─────────────────────────────────────────
    // 1. DASHBOARD
    // ─────────────────────────────────────────
  #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(WalletRepository $walletRepo, AssetRepository $assetRepo): Response
    {
        $userId = 1; 
        $wallet = $walletRepo->findOneBy(['utilisateur' => $userId]);

        // Injection du solde statique pour tes tests
        if (!$wallet) {
            $solde = 10000000;
        } elseif ($wallet->getSolde() <= 0) {
            $wallet->setSolde(10000000);
            $this->em->flush();
            $solde = $wallet->getSolde();
        } else {
            $solde = $wallet->getSolde();
        }

        $trades = $this->tradeRepo->findBy(['userId' => $userId], ['createdAt' => 'DESC'], 10);
        $assets = $assetRepo->findBy(['status' => 'active'], ['symbol' => 'ASC']);
        $symbols = array_map(fn($a) => strtoupper($a->getSymbol()) . 'USDT', $assets);

        return $this->render('trading_dashboard/dashboard.html.twig', [
            'solde'   => $solde,
            'trades'  => $trades,
            'symbols' => $symbols,
            'assets'  => $assets,
        ]);
    }
    // ─────────────────────────────────────────
    // 2. PRIX EN TEMPS RÉEL — CoinGecko
    // ─────────────────────────────────────────
   #[Route('/api/prices', name: 'api_prices', methods: ['GET'])]
    public function getPrices(AssetRepository $assetRepo): JsonResponse
    {
        try {
            $response = $this->httpClient->request('GET', $this->binanceBase . '/ticker/24hr');
            $allData = $response->toArray();

            $assets = $assetRepo->findBy(['status' => 'active']);
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
    // 3. PRIX D'UN SYMBOLE — CoinGecko
    // ─────────────────────────────────────────
    #[Route('/api/price/{symbol}', name: 'api_price', methods: ['GET'])]
    public function getPrice(string $symbol): JsonResponse
    {
        $binanceSymbol = strtoupper(str_replace('USDT', '', $symbol)) . 'USDT';

        try {
            $response = $this->httpClient->request('GET', $this->binanceBase . '/ticker/24hr', [
                'query' => ['symbol' => $binanceSymbol],
            ]);
            $coin = $response->toArray();

            return new JsonResponse([
                'symbol'    => $binanceSymbol,
                'price'     => (float) $coin['lastPrice'],
                'change'    => (float) $coin['priceChangePercent'],
                'direction' => (float)$coin['priceChangePercent'] >= 0 ? 'up' : 'down',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Binance error: ' . $e->getMessage()], 500);
        }
    }
    // ─────────────────────────────────────────
    // 4. GRAPHIQUE — TradingView (pas d'API Binance ici,
    //    TradingView widget charge directement depuis le navigateur)
    // ─────────────────────────────────────────
    #[Route('/api/chart/{symbol}', name: 'api_chart', methods: ['GET'])]
    public function getChart(string $symbol, Request $request): JsonResponse
    {
        // Cette route n'est plus utilisée (TradingView widget côté client)
        // On garde pour compatibilité
        return new JsonResponse(['info' => 'Use TradingView widget directly in the browser.']);
    }

    // ─────────────────────────────────────────
    // 5. NEWS — NewsAPI
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
            $data     = $response->toArray();
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
    // 6. CHATBOT GROQ (xAI)
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
        // --- CHANGEMENT ICI : URL de GROQ ---
        $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->grokApiKey, // Ta clé gsk_ fonctionnera ici
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                // --- CHANGEMENT ICI : Modèle GROQ ---
                'model' => 'llama-3.3-70b-versatile', 
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a crypto expert for the Champions project. Be concise.',
                    ],
                    ['role' => 'user', 'content' => $message],
                ],
            ],
        ]);

        $result = $response->toArray();
        $reply = $result['choices'][0]['message']['content'] ?? 'No response.';

        return new JsonResponse(['reply' => $reply]);
    } catch (\Exception $e) {
        return new JsonResponse(['error' => 'Groq API Error: ' . $e->getMessage()], 400);
    }
}

    // ─────────────────────────────────────────
    // 7. RECOMMANDATION — CoinGecko
    // ─────────────────────────────────────────
   #[Route('/api/recommend/{symbol}', name: 'api_recommend', methods: ['GET'])]
public function recommend(string $symbol, WalletRepository $walletRepo): JsonResponse
{
    $userId = 1;
    $wallet = $walletRepo->findOneBy(['utilisateur' => $userId]);
    $solde = $wallet ? (float) $wallet->getSolde() : 10000000;

    $sym = strtoupper(str_replace('USDT', '', $symbol));
    $binanceSymbol = $sym . 'USDT';

    try {
        // On récupère les stats 24h sur Binance
        $response = $this->httpClient->request('GET', $this->binanceBase . '/ticker/24hr', [
            'query' => ['symbol' => $binanceSymbol],
        ]);
        $coin = $response->toArray();

        $price = (float) $coin['lastPrice'];
        $change24h = (float) $coin['priceChangePercent'];
        $volatility = abs($change24h);

        // Logique de risque adaptée aux pourcentages Binance
        $riskFactor = match(true) {
            $volatility > 10 => 0.05, // Risque élevé : on n'investit que 5% du solde
            $volatility > 5  => 0.10,
            default          => 0.20,
        };

        $maxInvestment = $solde * $riskFactor;
        $maxQuantity = $price > 0 ? round($maxInvestment / $price, 6) : 0;
        $riskLevel = ($volatility > 10) ? 'HIGH' : (($volatility > 5) ? 'MEDIUM' : 'LOW');

        return new JsonResponse([
            'symbol'        => $sym,
            'currentPrice'  => $price,
            'change24h'     => $change24h,
            'volatility'    => $volatility,
            'riskLevel'     => $riskLevel,
            'solde'         => $solde,
            'maxInvestment' => round($maxInvestment, 2),
            'maxQuantity'   => $maxQuantity,
            'advice'        => $this->getAdvice($riskLevel, $change24h),
        ]);
    } catch (\Exception $e) {
        return new JsonResponse(['error' => 'Binance error: ' . $e->getMessage()], 500);
    }
}

   
    // ─────────────────────────────────────────
    // 8. ACHAT/VENTE — Supporte Market et Limit (Bot)
    // ─────────────────────────────────────────
   #[Route('/api/execute-trade', name: 'api_execute_trade', methods: ['POST'])]
public function executeTrade(Request $request, WalletRepository $walletRepo, AssetRepository $assetRepo): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    $userId = 1; // À remplacer par $this->getUser()->getId() plus tard
    
    // Correction 1 : Gérer les deux noms de clés possibles pour le type de trade
    $tradeType = strtoupper($data['tradeType'] ?? $data['type'] ?? ''); 
    $symbol = strtoupper(str_replace('USDT', '', $data['symbol'] ?? ''));
    $quantity = (float) ($data['quantity'] ?? 0);
    $orderMode = strtoupper($data['orderMode'] ?? 'MARKET');
    $targetPrice = isset($data['targetPrice']) ? (float) $data['targetPrice'] : null;

    if (!$symbol || $quantity <= 0) {
        return new JsonResponse(['error' => 'Paramètres invalides.'], 400);
    }

    $wallet = $walletRepo->findOneBy(['utilisateur' => $userId]);
    $asset = $assetRepo->findOneBy(['symbol' => $symbol]);

    if (!$wallet || !$asset) {
        return new JsonResponse(['error' => 'Wallet ou Asset introuvable.'], 404);
    }

    $trade = new \App\Entity\Trade();
    $trade->setUserId($userId);
    
    // Correction 2 : Assigner l'assetId (obligatoire en base de données)
    $trade->setAssetId($asset->getId()); 
    
    // Correction 3 : Utiliser float pour correspondre à ton entité
    $trade->setQuantity($quantity); 
    $trade->setTradeType($tradeType);
    $trade->setOrderMode($orderMode);
    $trade->setCreatedAt(new \DateTime());

    // --- LOGIQUE ORDRE LIMIT (BOT) ---
    if ($orderMode === 'LIMIT') {
        if (!$targetPrice) {
            return new JsonResponse(['error' => 'Prix cible requis pour le mode LIMIT.'], 400);
        }
        
        $trade->setStatus('PENDING');
        $trade->setPrice($targetPrice); 
        
        $this->em->persist($trade);
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => "Ordre LIMIT enregistré pour $symbol.",
            'status'  => 'PENDING'
        ]);
    } 
    
    // --- LOGIQUE ORDRE MARKET (IMMÉDIAT) ---
    try {
        $res = $this->httpClient->request('GET', $this->binanceBase . '/ticker/price?symbol=' . $symbol . 'USDT');
        $currentPrice = (float) $res->toArray()['price'];
    } catch (\Exception $e) {
        return new JsonResponse(['error' => 'Erreur Binance: ' . $e->getMessage()], 500);
    }

    $total = $currentPrice * $quantity;
    $solde = (float) $wallet->getSolde();

    if ($tradeType === 'BUY') {
        if ($solde < $total) {
            return new JsonResponse(['error' => 'Solde insuffisant.'], 400);
        }
        $wallet->setSolde($solde - $total);
    } else {
        $wallet->setSolde($solde + $total);
    }

    // Correction 4 : Utiliser 'COMPLETED' (valeur autorisée par ton ENUM SQL)
    $trade->setStatus('COMPLETED'); 
    $trade->setPrice($currentPrice);
    $trade->setExecutedAt(new \DateTime()); // Optionnel : remplir la date d'exécution

    $this->em->persist($trade);
    $this->em->flush();

    return new JsonResponse([
        'success'    => true,
        'newBalance' => $wallet->getSolde(),
        'status'     => 'COMPLETED'
    ]);
}
    // ─────────────────────────────────────────
    // 9. HISTORIQUE COMPLET DES TRADES
    // ─────────────────────────────────────────
   #[Route('/api/trades/history', name: 'api_history', methods: ['GET'])]
public function getHistory(): JsonResponse
{
    $userId = 1; 
    $trades = $this->tradeRepo->findBy(['userId' => $userId], ['createdAt' => 'DESC']);

    $data = array_map(fn($t) => [
        'trade_type' => $t->getTradeType(), // 'BUY' ou 'SELL'
        'order_mode' => $t->getOrderMode(), // 'MARKET' ou 'LIMIT'
        'price'      => (float) $t->getPrice(),
        'quantity'   => (float) $t->getQuantity(),
        'status'     => $t->getStatus(),    // 'PENDING', 'ACTIVE', 'COMPLETED'
        'date'       => $t->getCreatedAt()->format('d M, H:i'),
    ], $trades);

    return new JsonResponse($data);
}


    #[Route('/api/bot/stats', name: 'api_bot_stats', methods: ['GET'])]
public function getBotStats(TradeRepository $tradeRepo): JsonResponse
{
    $userId = 1;
    // Compter les trades exécutés par le bot aujourd'hui
    $today = new \DateTime('today');
    
    $tradesToday = $tradeRepo->createQueryBuilder('t')
        ->select('count(t.id)')
        ->where('t.userId = :user')
        ->andWhere('t.status = :status')
        ->andWhere('t.executedAt >= :today')
        ->setParameter('user', $userId)
        ->setParameter('status', 'COMPLETED')
        ->setParameter('today', $today)
        ->getQuery()
        ->getSingleScalarResult();

    return new JsonResponse([
        'tradesToday' => $tradesToday,
        'botStatus' => true, // Tu peux stocker ça en DB ou en Cache
        'winRate' => 65,    // Logique à calculer selon tes profits/pertes
        'pnl' => 12.50      // Profit/Perte total du jour
    ]);
}


}