<?php

namespace App\Controller\frontOffice;

use App\Entity\Order;
use App\Entity\Transaction;
use App\Repository\OrderRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\CurrencyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/orders')]
class OrderController extends AbstractController
{
    #[Route('/', name: 'app_order_index', methods: ['GET'])]
    public function index(OrderRepository $orderRepository, UtilisateurRepository $userRepo): Response
    {
        $user = $userRepo->findOneBy([]); // Get the first user for demo
        $orders = $orderRepository->findBy(['utilisateur' => $user], ['orderDate' => 'DESC']);

        return $this->render('frontOffice/order/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/pay/{id}', name: 'app_order_pay', methods: ['POST'])]
    public function pay(Order $order, EntityManagerInterface $entityManager, CurrencyRepository $currencyRepo): Response
    {
        if ($order->getStatus() !== 'pending_payment') {
            $this->addFlash('error', 'Commande déjà payée ou invalide.');
            return $this->redirectToRoute('app_order_index');
        }

        // Logic for successful payment
        $order->setStatus('paid');

        // Generate Transaction (SCRUM-39)
        $transaction = new Transaction();
        $transaction->setMontant($order->getTotalAmount());
        $transaction->setType('PURCHASE');
        $transaction->setStatut('COMPLETED');
        $transaction->setDateTransaction(new \DateTime());
        
        // Fix for "id_wallet_destination cannot be null"
        $walletRepo = $entityManager->getRepository(\App\Entity\Wallet::class);
        $wallets = $walletRepo->findAll();
        if (count($wallets) >= 2) {
            $transaction->setWalletSource($wallets[0]);
            $transaction->setWalletDestination($wallets[1]);
        } elseif (count($wallets) >= 1) {
            $transaction->setWalletSource($wallets[0]);
            $transaction->setWalletDestination($wallets[0]);
        }
        
        // Try to find a default currency (e.g. BTC)
        $currency = $currencyRepo->findOneBy(['code' => 'BTC']) ?: $currencyRepo->findOneBy([]);
        if ($currency) {
            $transaction->setCurrency($currency);
        }

        $entityManager->persist($transaction);
        $entityManager->flush();

        $this->addFlash('success', 'Paiement crypto réussi ! Transaction ' . $transaction->getIdTransaction() . ' générée.');

        return $this->redirectToRoute('app_order_index');
    }

    #[Route('/cancel/{id}', name: 'app_order_cancel', methods: ['POST'])]
    public function cancel(Order $order, EntityManagerInterface $entityManager): Response
    {
        if ($order->getStatus() === 'pending_payment') {
            $order->setStatus('cancelled');
            $entityManager->flush();
            $this->addFlash('info', 'Commande annulée.');
        }

        return $this->redirectToRoute('app_order_index');
    }
}
