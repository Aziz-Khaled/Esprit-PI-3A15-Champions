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
    $pendingTrades = $this->tradeRepo->findBy(['status' => 'PENDING', 'orderMode' => 'LIMIT']);

    foreach ($pendingTrades as $trade) {
        // IMPORTANT : Récupérer le symbole via l'Asset si getSymbol() n'existe pas directement
        // Si tu as une relation, c'est $trade->getAsset()->getSymbol()
        $symbol = $trade->getAsset() ? $trade->getAsset()->getSymbol() : 'BTC';

        $marketPrice = $this->getBinancePrice($symbol);
        $targetPrice = (float) $trade->getPrice();
        $now = new \DateTime();

        // AFFICHAGE DEMANDÉ : Date | Prix Marché | Prix Client
        $output->writeln(sprintf(
            '[%s] %s | Marché: <info>$%s</info> | Client: <comment>$%s</comment>',
            $now->format('H:i:s'),
            $symbol,
            number_format($marketPrice, 2),
            number_format($targetPrice, 2)
        ));

        $shouldExecute = false;

        // Logique de déclenchement
        if ($trade->getTradeType() === 'BUY' && $marketPrice <= $targetPrice) {
            $shouldExecute = true;
        } elseif ($trade->getTradeType() === 'SELL' && $marketPrice >= $targetPrice) {
            $shouldExecute = true;
        }

        if ($shouldExecute) {
            $output->writeln("<fg=green;options=bold>🚀 EXECUTION DECLENCHÉE !</>");
            $this->executeTradeWithWallet($trade, $marketPrice, $output);
        }
    }
}
  private function executeTradeWithWallet(Trade $trade, float $marketPrice, OutputInterface $output): void
{
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
            $output->writeln("<error>❌ Fonds insuffisants.</error>");
            // On le laisse en PENDING pour qu'il réessaie plus tard quand l'user aura de l'argent
            return; 
        } else {
            $wallet->setSolde($solde - $total);
            $trade->setStatus('COMPLETED');
        }
    } else {
        // Logique de VENTE
        $wallet->setSolde($solde + $total);
        $trade->setStatus('COMPLETED');
    }

    // On n'enregistre que si le statut est passé à COMPLETED
    if ($trade->getStatus() === 'COMPLETED') {
        $trade->setPrice($marketPrice); 
        $trade->setExecutedAt(new \DateTime());
        
        $this->em->persist($wallet);
        $this->em->persist($trade);
        $this->em->flush(); 

        $output->writeln('<info>✅ Statut mis à jour : PENDING -> COMPLETED</info>');
    }

}
private function getBinancePrice(string $symbol): ?float
    {
        try {
            // On ajoute 'USDT' car Binance utilise des paires (ex: BTCUSDT)
            $response = $this->httpClient->request('GET', $this->binanceBase . '/ticker/price', [
                'query' => ['symbol' => strtoupper($symbol) . 'USDT']
            ]);
            $data = $response->toArray();
            return (float) ($data['price'] ?? null);
        } catch (\Exception $e) {
            return null;
        }
    }
}