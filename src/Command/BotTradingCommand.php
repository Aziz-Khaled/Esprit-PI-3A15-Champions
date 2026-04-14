<?php

namespace App\Command;

use App\Entity\Trade;
use App\Repository\TradeRepository;
use App\Repository\WalletRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:bot:trading',
    description: 'Bot qui surveille les prix et exécute les trades automatiquement.',
)]
class BotTradingCommand extends Command
{
    private string $binanceBase = 'https://api.binance.com/api/v3';

    public function __construct(
        private HttpClientInterface    $httpClient,
        private EntityManagerInterface $em,
        private TradeRepository        $tradeRepo,
        private WalletRepository       $walletRepo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>🤖 Bot Trading démarré — surveillance des prix...</info>');

        while (true) {
            // On nettoie l'EntityManager à chaque cycle pour avoir des données fraîches de la DB
            $this->em->clear(); 
            
            $this->processPendingTrades($output);
            
            // On attend 10 secondes (plus rapide pour une démo Esprit)
            sleep(10); 
        }

        return Command::SUCCESS;
    }

    private function processPendingTrades(OutputInterface $output): void
    {
        // 1. Récupérer les trades en attente (LIMIT)
        $pendingTrades = $this->tradeRepo->findBy([
            'status'    => 'PENDING',
            'orderMode' => 'LIMIT',
        ]);

        if (empty($pendingTrades)) {
            $output->writeln('<comment>[' . date('H:i:s') . '] Aucun trade en attente...</comment>');
            return;
        }

        foreach ($pendingTrades as $trade) {
            try {
                $symbol = $trade->getSymbol();
                $marketPrice = $this->getBinancePrice($symbol);

                if ($marketPrice === null) {
                    $output->writeln("<error>Impossible de récupérer le prix pour $symbol</error>");
                    continue;
                }

                $targetPrice = (float) $trade->getPrice(); // Le prix entré par le client dans le formulaire
                $tradeType   = $trade->getTradeType();

                $output->writeln(sprintf(
                    '🔍 %s | Marché: <fg=cyan>$%.4f</> | Cible: <fg=yellow>$%.4f</> | Type: %s',
                    $symbol, $marketPrice, $targetPrice, $tradeType
                ));

                $shouldExecute = false;

                // --- LOGIQUE DU BOT ---
                
                // ACHAT : On achète si le prix du marché baisse et devient inférieur ou égal au prix client
                if ($tradeType === 'BUY' && $marketPrice <= $targetPrice) {
                    $shouldExecute = true;
                    $output->writeln("<info>🚀 ACHAT DECLENCHÉ : Le prix a chuté sous la cible !</info>");
                }

                // VENTE : On vend si le prix du marché monte et devient supérieur ou égal au prix client
                if ($tradeType === 'SELL' && $marketPrice >= $targetPrice) {
                    $shouldExecute = true;
                    $output->writeln("<info>💰 VENTE DECLENCHÉE : Le prix a atteint le profit cible !</info>");
                }

                if ($shouldExecute) {
                    $this->executeTradeWithWallet($trade, $marketPrice, $output);
                }

            } catch (\Exception $e) {
                $output->writeln('<error>Erreur sur le trade #' . $trade->getId() . ': ' . $e->getMessage() . '</error>');
            }
        }
    }

    private function getBinancePrice(string $symbol): ?float
    {
        try {
            // Conversion forcée en USDT pour Binance (ex: BTC -> BTCUSDT)
            $pair = strtoupper($symbol);
            if (!str_ends_with($pair, 'USDT')) {
                $pair .= 'USDT';
            }

            $response = $this->httpClient->request('GET', $this->binanceBase . '/ticker/price', [
                'query' => ['symbol' => $pair],
            ]);
            
            $data = $response->toArray();
            return (float) $data['price'];
        } catch (\Exception) {
            return null;
        }
    }

    private function executeTradeWithWallet(Trade $trade, float $marketPrice, OutputInterface $output): void
    {
        // On récupère le wallet via l'utilisateur lié au trade
        $wallet = $this->walletRepo->findOneBy(['utilisateur' => $trade->getUserId()]);
        
        if (!$wallet) {
            $output->writeln('<error>Wallet introuvable.</error>');
            return;
        }

        $quantity = (float) $trade->getQuantity();
        $total    = $marketPrice * $quantity;
        $solde    = (float) $wallet->getSolde();

        if ($trade->getTradeType() === 'BUY') {
            if ($solde < $total) {
                $output->writeln("<error>❌ Fonds insuffisants ($solde $) pour acheter ($total $)</error>");
                $trade->setStatus('CANCELLED');
            } else {
                $wallet->setSolde($solde - $total);
                $trade->setStatus('EXECUTED');
            }
        } else {
            // Pour la vente, on ajoute simplement au solde
            $wallet->setSolde($solde + $total);
            $trade->setStatus('EXECUTED');
        }

        if ($trade->getStatus() === 'EXECUTED') {
            $trade->setExecutedAt(new \DateTime());
            $trade->setPrice((string) $marketPrice); // On enregistre le prix réel d'exécution
            
            $output->writeln(sprintf(
                '<info>✅ TRADE RÉUSSI ! #%d | Total: %.2f$ | Nouveau solde: %.2f$</info>',
                $trade->getId(), $total, $wallet->getSolde()
            ));
        }

        $this->em->flush();
    }
}