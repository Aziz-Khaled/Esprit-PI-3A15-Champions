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
    $solde  = $wallet ? (float) $wallet->getSolde() : 10_000_000;
 
    $sym           = strtoupper(str_replace('USDT', '', $symbol));
    $binanceSymbol = $sym . 'USDT';
 
    try {
        // ── 1. Données 24h ───────────────────────────────────────
        $response = $this->httpClient->request('GET', $this->binanceBase . '/ticker/24hr', [
            'query' => ['symbol' => $binanceSymbol],
        ]);
        $coin = $response->toArray();
 
        $price      = (float) $coin['lastPrice'];
        $change24h  = (float) $coin['priceChangePercent'];
        $high24h    = (float) $coin['highPrice'];
        $low24h     = (float) $coin['lowPrice'];
        $volume     = (float) $coin['volume'];
        $quoteVol   = (float) ($coin['quoteVolume'] ?? 0);
 
        // ── 2. Données klines 15 dernières périodes (1h) ─────────
        $klines = [];
        try {
            $klRes = $this->httpClient->request('GET', $this->binanceBase . '/klines', [
                'query' => ['symbol' => $binanceSymbol, 'interval' => '1h', 'limit' => 20],
                'timeout' => 5,
            ]);
            $klines = $klRes->toArray();
        } catch (\Exception) {}
 
        $closes = array_map(fn($k) => (float) $k[4], $klines);
 
        // ── 3. Indicateurs techniques ────────────────────────────
        $rsi        = count($closes) >= 15 ? $this->calcRSI(array_slice($closes, -15)) : 50;
        $ma5        = count($closes) >= 5  ? array_sum(array_slice($closes, -5))  / 5  : $price;
        $ma20       = count($closes) >= 20 ? array_sum(array_slice($closes, -20)) / 20 : $price;
        $volatility = abs($change24h);
 
        // ATR simplifié sur 14 periodes
        $atr = 0;
        if (count($klines) >= 14) {
            $trList = [];
            for ($i = 1; $i < count($klines); $i++) {
                $h = (float) $klines[$i][2];
                $l = (float) $klines[$i][3];
                $pc = (float) $klines[$i - 1][4];
                $trList[] = max($h - $l, abs($h - $pc), abs($l - $pc));
            }
            $atr = array_sum(array_slice($trList, -14)) / 14;
        }
 
        // ── 4. Signal de tendance ────────────────────────────────
        $trend = 'NEUTRAL';
        if ($ma5 > $ma20 * 1.001 && $change24h > 0) {
            $trend = 'BULLISH';
        } elseif ($ma5 < $ma20 * 0.999 && $change24h < 0) {
            $trend = 'BEARISH';
        }
 
        // ── 5. Niveau de risque ──────────────────────────────────
        $riskLevel = match(true) {
            $volatility > 15 || $rsi > 80 || $rsi < 20 => 'CRITICAL',
            $volatility > 8  || $rsi > 70 || $rsi < 30 => 'HIGH',
            $volatility > 4  || $rsi > 60 || $rsi < 40 => 'MEDIUM',
            default => 'LOW',
        };
 
        // ── 6. Sizing Kelly modifié (½ Kelly) ────────────────────
        // win_rate estimé depuis RSI + tendance
        $estWinRate = match(true) {
            $trend === 'BULLISH' && $rsi < 60 => 0.60,
            $trend === 'BEARISH' && $rsi > 40 => 0.55,
            default => 0.45,
        };
        $avgWin  = max($atr / max($price, 1) * 2, 0.02);  // R:R ratio 2:1 minimum
        $avgLoss = max($atr / max($price, 1),     0.01);
        $kellyFraction = ($estWinRate - (1 - $estWinRate) / ($avgWin / max($avgLoss, 0.001)));
        $halfKelly = max(0.01, min($kellyFraction * 0.5, 0.25));  // plafonné à 25%
 
        // ── 7. Calcul des quantités max BUY et SELL ──────────────
        // On adapte aussi au niveau de risque
        $riskMultiplier = match($riskLevel) {
            'CRITICAL' => 0.02,
            'HIGH'     => 0.05,
            'MEDIUM'   => 0.10,
            'LOW'      => 0.20,
        };
 
        $effectiveFraction = min($halfKelly, $riskMultiplier);
        $maxInvestment     = $solde * $effectiveFraction;
        $maxBuyQty         = $price > 0 ? round($maxInvestment / $price, 6) : 0;
 
        // Pour le SELL : on calcule en fonction du take-profit suggéré
        $suggestedTP   = $price + ($atr > 0 ? $atr * 2 : $price * 0.03);
        $suggestedSL   = $price - ($atr > 0 ? $atr * 1 : $price * 0.015);
        $maxSellQty    = $maxBuyQty; // même base
 
        // ── 8. Conseil texte ─────────────────────────────────────
        $adviceMap = [
            'CRITICAL' => [
                'BULLISH' => "⚠️ Risque critique. RSI=$rsi. Attendre un repli avant d'entrer.",
                'BEARISH' => "🔴 Marché en chute violente. Éviter toute position longue.",
                'NEUTRAL' => "⛔ Volatilité extrême. Rester en dehors du marché.",
            ],
            'HIGH' => [
                'BULLISH' => "📈 Tendance haussière mais volatile. Max " . round($maxBuyQty, 4) . " unités.",
                'BEARISH' => "📉 Momentum baissier. Réduire l'exposition. SL strict conseillé.",
                'NEUTRAL' => "⚡ Marché instable. Taille de position réduite recommandée.",
            ],
            'MEDIUM' => [
                'BULLISH' => "✅ Signal positif. Entrée prudente possible jusqu'à " . round($maxBuyQty, 4) . " unités.",
                'BEARISH' => "⚠️ Pression vendeuse. Attendez confirmation avant d'acheter.",
                'NEUTRAL' => "🔍 Signal mixte. RSI=$rsi. Surveiller cassure MA.",
            ],
            'LOW' => [
                'BULLISH' => "🚀 Conditions favorables. Vous pouvez acheter jusqu'à " . round($maxBuyQty, 4) . " unités.",
                'BEARISH' => "📊 Légère pression vendeuse. Zone d'accumulation potentielle.",
                'NEUTRAL' => "💼 Marché stable. Bonne période pour le DCA.",
            ],
        ];
 
        $advice = $adviceMap[$riskLevel][$trend] ?? "Analyse en cours…";
 
        // ── 9. Zones de support/résistance simples ───────────────
        $support1    = round($low24h, $price < 1 ? 6 : 2);
        $resistance1 = round($high24h, $price < 1 ? 6 : 2);
 
        return new JsonResponse([
            'symbol'         => $sym,
            'currentPrice'   => $price,
            'change24h'      => round($change24h, 2),
            'high24h'        => $high24h,
            'low24h'         => $low24h,
            'volume'         => round($volume, 2),
            'quoteVolume'    => round($quoteVol, 2),
 
            // Indicateurs
            'rsi'            => round($rsi, 1),
            'ma5'            => round($ma5, $price < 1 ? 6 : 4),
            'ma20'           => round($ma20, $price < 1 ? 6 : 4),
            'atr'            => round($atr, $price < 1 ? 6 : 4),
            'trend'          => $trend,
 
            // Risk & Sizing
            'riskLevel'      => $riskLevel,
            'volatility'     => round($volatility, 2),
            'halfKelly'      => round($halfKelly * 100, 1),    // en %
            'maxInvestment'  => round($maxInvestment, 2),
            'maxBuyQty'      => $maxBuyQty,
            'maxSellQty'     => $maxSellQty,
            'maxQuantity'    => $maxBuyQty,  // compat ancien code
 
            // Targets
            'suggestedTP'    => round($suggestedTP, $price < 1 ? 6 : 2),
            'suggestedSL'    => round($suggestedSL, $price < 1 ? 6 : 2),
            'support1'       => $support1,
            'resistance1'    => $resistance1,
 
            // Wallet
            'solde'          => round($solde, 2),
            'advice'         => $advice,
 
            // Securite
            'safeToTrade'    => in_array($riskLevel, ['LOW', 'MEDIUM']),
            'warningFlags'   => $this->getWarningFlags($rsi, $volatility, $change24h),
        ]);
 
    } catch (\Exception $e) {
        return new JsonResponse(['error' => 'Binance error: ' . $e->getMessage()], 500);
    }
}
 
// ─────────────────────────────────────────────────────────────────
// MÉTHODES PRIVÉES — à ajouter dans la classe
// ─────────────────────────────────────────────────────────────────
 
private function calcRSI(array $prices): float
{
    $gains = $losses = 0;
    for ($i = 1, $n = count($prices); $i < $n; $i++) {
        $diff = $prices[$i] - $prices[$i - 1];
        if ($diff > 0) { $gains += $diff; } else { $losses += abs($diff); }
    }
    $periods = count($prices) - 1;
    if ($periods === 0) return 50;
    $rs = ($gains / $periods) / (($losses / $periods) ?: 0.001);
    return 100 - (100 / (1 + $rs));
}
 
private function getWarningFlags(float $rsi, float $volatility, float $change24h): array
{
    $flags = [];
    if ($rsi > 75) $flags[] = ['level' => 'danger',  'msg' => 'RSI suracheté — correction probable'];
    if ($rsi < 25) $flags[] = ['level' => 'warning', 'msg' => 'RSI survendu — rebond potentiel'];
    if ($volatility > 10) $flags[] = ['level' => 'danger', 'msg' => 'Volatilité élevée (' . round($volatility, 1) . '%)'];
    if (abs($change24h) > 20) $flags[] = ['level' => 'danger', 'msg' => 'Mouvement extrême 24h: ' . round($change24h, 1) . '%'];
    return $flags;
}
 
private function getAdvice(string $riskLevel, float $change24h): string
{
    return match($riskLevel) {
        'HIGH'   => 'Risque élevé — réduire la taille de position',
        'MEDIUM' => 'Risque modéré — procéder avec prudence',
        default  => $change24h > 0 ? 'Conditions favorables' : 'Marché stable — bon pour DCA',
    };
}
   
    // ─────────────────────────────────────────
// 8. ACHAT/VENTE — Market immédiat + Limit (bot)
// ─────────────────────────────────────────
#[Route('/api/execute-trade', name: 'api_execute_trade', methods: ['POST'])]
public function executeTrade(Request $request, WalletRepository $walletRepo, AssetRepository $assetRepo): JsonResponse
{
    $data      = json_decode($request->getContent(), true);
    $userId    = 1;
    $tradeType = strtoupper($data['tradeType'] ?? $data['type'] ?? '');
    $symbol    = strtoupper(str_replace('USDT', '', $data['symbol'] ?? ''));
    $quantity  = (float) ($data['quantity'] ?? 0);
    $orderMode = strtoupper($data['orderMode'] ?? 'MARKET');
    $targetPrice = isset($data['targetPrice']) ? (float) $data['targetPrice'] : null;
 
    if (!$symbol || $quantity <= 0) {
        return new JsonResponse(['error' => 'Parametres invalides.'], 400);
    }
 
    $wallet = $walletRepo->findOneBy(['utilisateur' => $userId]);
    $asset  = $assetRepo->findOneBy(['symbol' => $symbol]);
 
    if (!$wallet) return new JsonResponse(['error' => 'Wallet introuvable.'], 404);
    if (!$asset)  return new JsonResponse(['error' => "Asset '$symbol' introuvable en DB."], 404);
 
    $trade = new \App\Entity\Trade();
    $trade->setUserId($userId);
    $trade->setAssetId($asset->getId());
    $trade->setQuantity($quantity);
    $trade->setTradeType($tradeType);
    $trade->setOrderMode($orderMode);
    $trade->setCreatedAt(new \DateTime());
 
    // ── ORDRE LIMIT (bot géré par BotTradingCommand) ──
    if ($orderMode === 'LIMIT') {
        if (!$targetPrice || $targetPrice <= 0) {
            return new JsonResponse(['error' => 'Prix cible requis pour le mode LIMIT.'], 400);
        }
 
        $trade->setStatus('PENDING');
        $trade->setPrice((string) $targetPrice);
 
        $this->em->persist($trade);
        $this->em->flush();
 
        return new JsonResponse([
            'success' => true,
            'message' => "Ordre LIMIT enregistre : $tradeType $quantity $symbol @ \$$targetPrice. Le bot l'executera automatiquement.",
            'status'  => 'PENDING',
            'tradeId' => $trade->getId(),
        ]);
    }
 
    // ── ORDRE MARKET (execution immediate via Binance côté navigateur → prix envoyé) ──
    // On utilise le prix fourni par le client (issu du WebSocket Binance navigateur)
    // OU on récupère le prix via CoinGecko côté serveur
    $currentPrice = null;
 
    // Essai 1 : prix envoyé par le client (depuis WebSocket Binance navigateur)
    if (isset($data['currentPrice']) && (float)$data['currentPrice'] > 0) {
        $currentPrice = (float) $data['currentPrice'];
    }
 
    // Essai 2 : CoinGecko côté serveur
    if (!$currentPrice) {
        $geckoIds = [
            'BTC'=>'bitcoin','ETH'=>'ethereum','XRP'=>'ripple','BNB'=>'binancecoin',
            'SOL'=>'solana','ADA'=>'cardano','DOGE'=>'dogecoin','DOT'=>'polkadot',
            'AVAX'=>'avalanche-2','LINK'=>'chainlink','ATOM'=>'cosmos','LTC'=>'litecoin',
            'ESP'=>'espers','ZAMA'=>'zama','SENT'=>'sentinel','RLUSD'=>'ripple-usd',
        ];
        $geckoId = $geckoIds[$symbol] ?? null;
        if ($geckoId) {
            try {
                $res = $this->httpClient->request('GET', 'https://api.coingecko.com/api/v3/simple/price', [
                    'query' => ['ids' => $geckoId, 'vs_currencies' => 'usd'],
                    'timeout' => 8,
                ]);
                $priceData = $res->toArray();
                $currentPrice = (float) ($priceData[$geckoId]['usd'] ?? 0);
            } catch (\Exception) {}
        }
    }
 
    if (!$currentPrice || $currentPrice <= 0) {
        return new JsonResponse(['error' => 'Impossible de recuperer le prix actuel.'], 500);
    }
 
    $total = $currentPrice * $quantity;
    $solde = (float) $wallet->getSolde();
 
    if ($tradeType === 'BUY') {
        if ($solde < $total) {
            return new JsonResponse([
                'error' => "Solde insuffisant. Besoin \$" . number_format($total, 2) . ", disponible \$" . number_format($solde, 2),
            ], 400);
        }
        $wallet->setSolde($solde - $total);
    } elseif ($tradeType === 'SELL') {
        $wallet->setSolde($solde + $total);
    } else {
        return new JsonResponse(['error' => 'Type de trade invalide.'], 400);
    }
 
    $trade->setStatus('COMPLETED');
    $trade->setPrice((string) $currentPrice);
    if (method_exists($trade, 'setExecutedAt')) {
        $trade->setExecutedAt(new \DateTime());
    }
 
    $this->em->persist($trade);
    $this->em->flush();
 
    return new JsonResponse([
        'success'    => true,
        'tradeType'  => $tradeType,
        'symbol'     => $symbol,
        'quantity'   => $quantity,
        'price'      => $currentPrice,
        'total'      => $total,
        'newBalance' => (float) $wallet->getSolde(),
        'status'     => 'COMPLETED',
    ]);
}
 
// ─────────────────────────────────────────
// 9. HISTORIQUE DES TRADES
// ─────────────────────────────────────────
#[Route('/api/trades/history', name: 'api_history', methods: ['GET'])]
public function getHistory(AssetRepository $assetRepo): JsonResponse
{
    $userId = 1;
    $trades = $this->tradeRepo->findBy(['userId' => $userId], ['createdAt' => 'DESC']);
 
    // Cache des symboles d'assets pour éviter les N+1 requêtes
    $assetCache = [];
 
    $data = array_map(function($t) use ($assetRepo, &$assetCache) {
        // Récupérer le symbole via l'asset
        $symbol = 'N/A';
        $assetId = method_exists($t, 'getAssetId') ? $t->getAssetId() : null;
        if ($assetId) {
            if (!isset($assetCache[$assetId])) {
                $asset = $assetRepo->find($assetId);
                $assetCache[$assetId] = $asset ? strtoupper($asset->getSymbol()) : 'N/A';
            }
            $symbol = $assetCache[$assetId];
        }
 
        return [
            'id'         => $t->getId(),
            'symbol'     => $symbol,
            'trade_type' => $t->getTradeType(),
            'order_mode' => $t->getOrderMode(),
            'price'      => (float) $t->getPrice(),
            'quantity'   => (float) $t->getQuantity(),
            'status'     => $t->getStatus(),
            'date'       => $t->getCreatedAt() ? $t->getCreatedAt()->format('M d, H:i') : '-',
        ];
    }, $trades);
 
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