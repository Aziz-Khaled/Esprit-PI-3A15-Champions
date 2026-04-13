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
            $this->processPendingTrades($output);
            sleep(30); // vérification toutes les 30 secondes
        }

        return Command::SUCCESS;
    }

    private function processPendingTrades(OutputInterface $output): void
    {
        // Récupérer tous les trades PENDING avec ordre LIMIT
        $pendingTrades = $this->tradeRepo->findBy([
            'status'    => 'PENDING',
            'orderMode' => 'LIMIT',
        ]);

        if (empty($pendingTrades)) {
            $output->writeln('<comment>Aucun trade PENDING trouvé.</comment>');
            return;
        }

        foreach ($pendingTrades as $trade) {
            try {
                // Récupérer le prix actuel de l'asset
                $assetRow = $this->em->getConnection()->fetchAssociative(
                    "SELECT symbol, current_price FROM asset WHERE id = :id",
                    ['id' => $trade->getAssetId()]
                );

                if (!$assetRow) continue;

                $symbol      = $assetRow['symbol'];
                $marketPrice = $this->getBinancePrice($symbol);

                if ($marketPrice === null) continue;

                $targetPrice = (float) $trade->getPrice();
                $tradeType   = $trade->getTradeType();

                $output->writeln(sprintf(
                    '📊 %s | Market: $%.4f | Target: $%.4f | Type: %s',
                    $symbol, $marketPrice, $targetPrice, $tradeType
                ));

                $shouldExecute = false;

                // Règle BUY : prix marché <= prix cible client → acheter
                if ($tradeType === 'BUY' && $marketPrice <= $targetPrice) {
                    $shouldExecute = true;
                    $output->writeln("<info>✅ BUY triggered: market $marketPrice <= target $targetPrice</info>");
                }

                // Règle SELL : prix marché >= prix cible client → vendre
                if ($tradeType === 'SELL' && $marketPrice >= $targetPrice) {
                    $shouldExecute = true;
                    $output->writeln("<info>✅ SELL triggered: market $marketPrice >= target $targetPrice</info>");
                }

                if ($shouldExecute) {
                    $this->executeTradeWithWallet($trade, $marketPrice, $output);
                }

            } catch (\Exception $e) {
                $output->writeln('<error>Erreur: ' . $e->getMessage() . '</error>');
            }
        }
    }

    private function getBinancePrice(string $symbol): ?float
    {
        try {
            $response = $this->httpClient->request('GET', $this->binanceBase . '/ticker/price', [
                'query' => ['symbol' => strtoupper($symbol) . 'USDT'],
            ]);
            return (float) $response->toArray()['price'];
        } catch (\Exception) {
            return null;
        }
    }

    private function executeTradeWithWallet(Trade $trade, float $marketPrice, OutputInterface $output): void
    {
        $wallet   = $this->walletRepo->findOneBy(['utilisateur' => $trade->getUtilisateur()]);
        $quantity = (float) $trade->getQuantity();
        $total    = $marketPrice * $quantity;

        if (!$wallet) {
            $output->writeln('<error>Wallet introuvable pour ce trade.</error>');
            return;
        }

        $solde = (float) $wallet->getSolde();

        if ($trade->getTradeType() === 'BUY') {
            if ($solde < $total) {
                $output->writeln("<error>❌ Solde insuffisant: besoin $total, disponible $solde</error>");
                $trade->setStatus('CANCELLED');
                $this->em->flush();
                return;
            }
            $wallet->setSolde($solde - $total);
        } else {
            $wallet->setSolde($solde + $total);
        }

        // Marquer le trade comme EXECUTED
        $trade->setStatus('EXECUTED');
        $trade->setExecutedAt(new \DateTime());
        $trade->setPrice((string) $marketPrice);

        $this->em->flush();

        $output->writeln(sprintf(
            '<info>🎯 Trade #%d EXECUTED | %s | Qty: %s | Price: $%.4f | Total: $%.2f</info>',
            $trade->getId(),
            $trade->getTradeType(),
            $quantity,
            $marketPrice,
            $total
        ));
    }
}