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
    // ── CoinGecko (gratuit, pas de clé requise) ──
    private string $geckoBase = 'https://api.coingecko.com/api/v3';

    // ── Map symbol → ID CoinGecko ──
    // Ajoute ici tout asset que tu crées dans l'admin
    private array $geckoIds = [
        'BTC'   => 'bitcoin',
        'ETH'   => 'ethereum',
        'XRP'   => 'ripple',
        'BNB'   => 'binancecoin',
        'SOL'   => 'solana',
        'ADA'   => 'cardano',
        'DOGE'  => 'dogecoin',
        'DOT'   => 'polkadot',
        'AVAX'  => 'avalanche-2',
        'LINK'  => 'chainlink',
        'UNI'   => 'uniswap',
        'ATOM'  => 'cosmos',
        'LTC'   => 'litecoin',
        'MATIC' => 'matic-network',
        'ESP'   => 'espers',
        'ZAMA'  => 'zama',
        'SENT'  => 'sentinel',
        'RLUSD' => 'ripple-usd',
    ];

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
        $solde  = $wallet ? $wallet->getSolde() : 0;

        $trades = $this->tradeRepo->findBy(
            ['userId' => $userId],
            ['createdAt' => 'DESC'],
            10
        );

        // Assets actifs depuis la DB
        $assets  = $assetRepo->findBy(['status' => 'active'], ['symbol' => 'ASC']);

        // Symbols pour le ticker (format "BTCUSDT" pour compatibilité TradingView)
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
        // Récupérer uniquement les assets actifs en DB
        $assets  = $assetRepo->findBy(['status' => 'active']);
        $symbols = array_map(fn($a) => strtoupper($a->getSymbol()), $assets);

        if (empty($symbols)) {
            return new JsonResponse([]);
        }

        // Construire la liste des IDs CoinGecko
        $ids = [];
        $idToSymbol = []; // geckoId → symbol
        foreach ($symbols as $sym) {
            if (isset($this->geckoIds[$sym])) {
                $geckoId = $this->geckoIds[$sym];
                $ids[]   = $geckoId;
                $idToSymbol[$geckoId] = $sym;
            }
        }

        if (empty($ids)) {
            return new JsonResponse(['error' => 'No CoinGecko IDs found for your assets. Update the $geckoIds map.'], 500);
        }

        try {
            $response = $this->httpClient->request('GET', $this->geckoBase . '/coins/markets', [
                'query' => [
                    'vs_currency'             => 'usd',
                    'ids'                     => implode(',', $ids),
                    'order'                   => 'market_cap_desc',
                    'per_page'                => 50,
                    'page'                    => 1,
                    'sparkline'               => 'false',
                    'price_change_percentage' => '24h',
                ],
            ]);
            $data = $response->toArray();

            $prices = array_map(fn($coin) => [
                // Format "BTCUSDT" pour matcher le JS du template
                'symbol' => strtoupper($idToSymbol[$coin['id']] ?? $coin['symbol']) . 'USDT',
                'price'  => (float) ($coin['current_price'] ?? 0),
                'change' => (float) ($coin['price_change_percentage_24h'] ?? 0),
                'high'   => (float) ($coin['high_24h'] ?? 0),
                'low'    => (float) ($coin['low_24h'] ?? 0),
                'volume' => (float) ($coin['total_volume'] ?? 0),
            ], $data);

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
        $sym = strtoupper(str_replace('USDT', '', $symbol));
        $id  = $this->geckoIds[$sym] ?? null;

        if (!$id) {
            return new JsonResponse(['error' => "No CoinGecko ID for symbol $sym. Add it to \$geckoIds."], 404);
        }

        try {
            $response = $this->httpClient->request('GET', $this->geckoBase . '/coins/markets', [
                'query' => [
                    'vs_currency'             => 'usd',
                    'ids'                     => $id,
                    'sparkline'               => 'false',
                    'price_change_percentage' => '24h',
                ],
            ]);
            $data = $response->toArray();

            if (empty($data)) {
                return new JsonResponse(['error' => 'Symbol not found on CoinGecko'], 404);
            }

            $coin = $data[0];
            return new JsonResponse([
                'symbol'    => $sym . 'USDT',
                'price'     => (float) $coin['current_price'],
                'change'    => (float) $coin['price_change_percentage_24h'],
                'high'      => (float) $coin['high_24h'],
                'low'       => (float) $coin['low_24h'],
                'volume'    => (float) $coin['total_volume'],
                'direction' => $coin['price_change_percentage_24h'] >= 0 ? 'up' : 'down',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
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
    // 6. CHATBOT GROK (xAI)
    // ─────────────────────────────────────────
    #[Route('/api/chatbot', name: 'api_chatbot', methods: ['POST'])]
    public function chatbot(Request $request): JsonResponse
    {
        $data    = json_decode($request->getContent(), true);
        $message = trim($data['message'] ?? '');

        if (empty($message)) {
            return new JsonResponse(['error' => 'Message is required.'], 400);
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.x.ai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->grokApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'    => 'grok-beta',
                    'messages' => [
                        [
                            'role'    => 'system',
                            'content' => 'You are a cryptocurrency expert assistant. Only answer questions about crypto, blockchain, trading, DeFi, NFTs and financial markets. Be concise.',
                        ],
                        ['role' => 'user', 'content' => $message],
                    ],
                    'max_tokens'  => 500,
                    'temperature' => 0.7,
                ],
            ]);

            $result = $response->toArray();
            $reply  = $result['choices'][0]['message']['content'] ?? 'No response.';

            return new JsonResponse(['reply' => $reply]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
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
        $solde  = $wallet ? (float) $wallet->getSolde() : 0;

        $sym = strtoupper(str_replace('USDT', '', $symbol));
        $id  = $this->geckoIds[$sym] ?? null;

        if (!$id) {
            return new JsonResponse(['error' => "No CoinGecko ID for $sym"], 404);
        }

        try {
            $response = $this->httpClient->request('GET', $this->geckoBase . '/coins/markets', [
                'query' => [
                    'vs_currency'             => 'usd',
                    'ids'                     => $id,
                    'sparkline'               => 'false',
                    'price_change_percentage' => '24h',
                ],
            ]);
            $data = $response->toArray();
            if (empty($data)) return new JsonResponse(['error' => 'Not found'], 404);

            $coin       = $data[0];
            $price      = (float) $coin['current_price'];
            $change24h  = (float) $coin['price_change_percentage_24h'];
            $volatility = abs($change24h);

            $riskFactor = match(true) {
                $volatility > 10 => 0.05,
                $volatility > 5  => 0.10,
                $volatility > 2  => 0.20,
                default          => 0.30,
            };

            $maxInvestment = $solde * $riskFactor;
            $maxQuantity   = $price > 0 ? round($maxInvestment / $price, 6) : 0;
            $riskLevel     = match(true) {
                $volatility > 10 => 'HIGH',
                $volatility > 5  => 'MEDIUM',
                default          => 'LOW',
            };

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
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    private function getAdvice(string $riskLevel, float $change): string
    {
        return match($riskLevel) {
            'HIGH'   => '⚠️ High volatility detected. Limit your investment to avoid major losses.',
            'MEDIUM' => '⚡ Moderate volatility. Invest cautiously and diversify.',
            'LOW'    => '✅ Market is stable. Safe to invest within recommended limits.',
            default  => 'No data available.',
        };
    }

    // ─────────────────────────────────────────
    // 8. ACHAT/VENTE — prix via CoinGecko simple/price
    // ─────────────────────────────────────────
    #[Route('/api/execute-trade', name: 'api_execute_trade', methods: ['POST'])]
    public function executeTrade(Request $request, WalletRepository $walletRepo): JsonResponse
    {
        $data      = json_decode($request->getContent(), true);
        $userId    = 1;
        $symbol    = strtoupper(str_replace('USDT', '', $data['symbol'] ?? ''));
        $tradeType = strtoupper($data['tradeType'] ?? '');
        $quantity  = (float) ($data['quantity'] ?? 0);

        if (!$symbol || !$tradeType || $quantity <= 0) {
            return new JsonResponse(['error' => 'Invalid parameters.'], 400);
        }

        $id = $this->geckoIds[$symbol] ?? null;
        if (!$id) {
            return new JsonResponse(['error' => "No CoinGecko ID for $symbol. Add it to \$geckoIds."], 400);
        }

        try {
            $response = $this->httpClient->request('GET', $this->geckoBase . '/simple/price', [
                'query' => ['ids' => $id, 'vs_currencies' => 'usd'],
            ]);
            $priceData = $response->toArray();
            $price     = (float) ($priceData[$id]['usd'] ?? 0);

            if ($price <= 0) {
                return new JsonResponse(['error' => 'Cannot fetch price from CoinGecko.'], 500);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Cannot fetch price: ' . $e->getMessage()], 500);
        }

        $total  = $price * $quantity;
        $wallet = $walletRepo->findOneBy(['utilisateur' => $userId]);

        if (!$wallet) {
            return new JsonResponse(['error' => 'Wallet not found.'], 404);
        }

        $soldeCourant = (float) $wallet->getSolde();

        if ($tradeType === 'BUY') {
            if ($soldeCourant < $total) {
                return new JsonResponse([
                    'error' => 'Insufficient balance. Need $' . number_format($total, 2) . ', have $' . number_format($soldeCourant, 2),
                ], 400);
            }
            $wallet->setSolde($soldeCourant - $total);
        } elseif ($tradeType === 'SELL') {
            $wallet->setSolde($soldeCourant + $total);
        } else {
            return new JsonResponse(['error' => 'Invalid trade type.'], 400);
        }

        $this->em->flush();

        return new JsonResponse([
            'success'    => true,
            'tradeType'  => $tradeType,
            'symbol'     => $symbol,
            'quantity'   => $quantity,
            'price'      => $price,
            'total'      => $total,
            'newBalance' => (float) $wallet->getSolde(),
        ]);
    }
}