<?php

namespace App\Controller\frontOffice;

use App\Entity\Wallet;
use App\Entity\WalletCurrency;
use App\Entity\Utilisateur;
use App\Entity\Currency;
use App\Entity\CreditCard;
use App\Form\WalletType;
use App\Form\TransactionType;
use App\Form\CreditCardType; 
use App\Repository\WalletRepository;
use App\Repository\CurrencyRepository;
use App\Repository\TransactionRepository;
use App\Repository\WalletCurrencyRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/wallet')]
class WalletController extends AbstractController
{
    #[Route('/', name: 'app_wallet_index', methods: ['GET', 'POST'])]
    public function index(
        WalletRepository $walletRepository,
        CurrencyRepository $currencyRepo,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // 1. User Management
        $user = $this->getUser() ?: $entityManager->getRepository(Utilisateur::class)->find(1);

        if (!$user) {
            throw $this->createNotFoundException("Test user not found.");
        }

        // 2. Wallet Creation Form
        $newWallet = new Wallet();
        $walletForm = $this->createForm(WalletType::class, $newWallet);
        $walletForm->handleRequest($request);

        if ($walletForm->isSubmitted() && $walletForm->isValid()) {
            $newWallet->setUtilisateur($user);
            $newWallet->setSolde(0.0);
            
            do {
                $rib = (string)random_int(10000000, 99999999);
                $exists = $walletRepository->findOneBy(['rib' => $rib]);
            } while ($exists);
            
            $newWallet->setRib($rib);
            $newWallet->setDateCreation(new \DateTime());
            $newWallet->setStatut('actif');

            $entityManager->persist($newWallet);
            $entityManager->flush();

            $this->addFlash('success', 'New wallet created successfully!');
            return $this->redirectToRoute('app_wallet_index');
        }

        // 3. Data Retrieval for View
        $walletsList = $walletRepository->findBy(['utilisateur' => $user]);
        $allCurrencies = $currencyRepo->findAll();
        $transactionForm = $this->createForm(TransactionType::class);

        // 4. Active Credit Card Management
        $creditCard = $entityManager->getRepository(CreditCard::class)->findOneBy([
            'utilisateur' => $user,
            'statut' => 'ACTIVE'
        ]);

        // Form for EDITING an existing card
        $cardFormView = null;
        if ($creditCard) {
            $cardFormView = $this->createForm(CreditCardType::class, $creditCard, ['is_edit' => true])->createView();
        }

        // ADDITION: Form for CREATING a new card (used by addCardModal)
        $newCardForm = $this->createForm(CreditCardType::class, new CreditCard(), [
            'is_edit' => false,
            'action' => $this->generateUrl('app_card_new') 
        ]);

        return $this->render('wallet/wallet.html.twig', [
            'wallets' => $walletsList,
            'walletForm' => $walletForm->createView(),
            'cardForm' => $cardFormView,           
            'newCardForm' => $newCardForm->createView(), 
            'all_available_currencies' => $allCurrencies,
            'transactionForm' => $transactionForm->createView(),
            'creditCard' => $creditCard,
        ]);
    }

    #[Route('/{id}/add-currency', name: 'app_wallet_add_currency', methods: ['POST'])]
    public function addCurrency(Request $request, Wallet $wallet, CurrencyRepository $currencyRepo, EntityManagerInterface $entityManager): Response
    {
        $idCurrency = $request->request->get('id_currency');
        $nomCurrency = $request->request->get('nom_currency');

        foreach ($wallet->getWalletCurrencys() as $existingWc) {
            if ($existingWc->getCurrency() && $existingWc->getCurrency()->getId() == $idCurrency) {
                $this->addFlash('error', 'This currency already exists in this wallet.');
                return $this->redirectToRoute('app_wallet_index');
            }
        }

        $currency = $currencyRepo->find($idCurrency);
        if (!$currency) {
            $this->addFlash('error', 'Currency not found.');
            return $this->redirectToRoute('app_wallet_index');
        }

        $wc = new WalletCurrency();
        $wc->setWallet($wallet);
        $wc->setCurrency($currency);
        $wc->setNomCurrency($nomCurrency ?: $currency->getNom());
        $wc->setSolde(0.0);
        $wallet->setDateDerniereModification(new \DateTime());

        $entityManager->persist($wc);
        $entityManager->flush();

        $this->addFlash('success', "Currency " . $wc->getNomCurrency() . " added successfully.");
        return $this->redirectToRoute('app_wallet_index');
    }

    #[Route('/{id}/edit', name: 'app_wallet_edit', methods: ['POST'])]
    public function edit(Request $request, Wallet $wallet, EntityManagerInterface $entityManager): Response
    {
        $statut = $request->request->get('statut');
        if ($statut) {
            $wallet->setStatut($statut);
            $wallet->setDateDerniereModification(new \DateTime());
            $entityManager->flush();
            $this->addFlash('success', 'Status updated successfully.');
        }
        return $this->redirectToRoute('app_wallet_index');
    }

    #[Route('/{id}/delete', name: 'app_wallet_delete', methods: ['POST'])]
    public function delete(Request $request, Wallet $wallet, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$wallet->getIdWallet(), $request->request->get('_token'))) {
            if (!$wallet->getWalletCurrencys()->isEmpty()) {
                $this->addFlash('error', 'Cannot delete: the wallet contains currencies.');
            } else {
                $entityManager->remove($wallet);
                $entityManager->flush();
                $this->addFlash('success', 'Wallet deleted successfully.');
            }
        }
        return $this->redirectToRoute('app_wallet_index');
    }

    #[Route('/currency/{id}/delete', name: 'app_wallet_currency_delete', methods: ['GET', 'POST'])]
    public function deleteCurrency(WalletCurrency $wc, EntityManagerInterface $entityManager): Response
    {
        if ($wc->getSolde() == 0) {
            $entityManager->remove($wc);
            $entityManager->flush();
            $this->addFlash('success', 'Currency removed from wallet.');
        } else {
            $this->addFlash('error', 'Cannot remove a currency with a positive balance.');
        }
        return $this->redirectToRoute('app_wallet_index');
    }

    #[Route('/stats/chart-data', name: 'app_wallet_chart_data', methods: ['GET'])]
    public function getChartData(WalletCurrencyRepository $wcRepo, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser() ?: $entityManager->getRepository(Utilisateur::class)->find(1);
        $data = $wcRepo->sumBalancesByUser($user);
        return new JsonResponse($data);
    }

    #[Route('/export/statement', name: 'app_wallet_export_pdf')]
    public function exportStatement(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser() ?: $entityManager->getRepository(Utilisateur::class)->find(1);
        $wallets = $entityManager->getRepository(Wallet::class)->findBy(['utilisateur' => $user]);

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($pdfOptions);

        $html = $this->renderView('wallet/statement_pdf.html.twig', [
            'user' => $user,
            'wallets' => $wallets,
            'date' => new \DateTime()
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="champions_statement.pdf"'
        ]);
    }

    #[Route('/{id}/history-json', name: 'app_wallet_history_json', methods: ['GET'])]
    public function getHistoryJson(int $id, WalletRepository $walletRepository, TransactionRepository $transactionRepository): JsonResponse
    {
        $wallet = $walletRepository->find($id);
        if (!$wallet) return new JsonResponse(['error' => 'Wallet not found'], 404);

        $transactions = $transactionRepository->createQueryBuilder('t')
            ->where('t.walletSource = :wallet OR t.walletDestination = :wallet')
            ->setParameter('wallet', $wallet)
            ->orderBy('t.dateTransaction', 'DESC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($transactions as $t) {
            $isDebit = ($t->getWalletSource() && $t->getWalletSource()->getIdWallet() === $wallet->getIdWallet());
            $counterparty = $isDebit ? 
                ($t->getWalletDestination() ? $t->getWalletDestination()->getRib() : 'N/A') :
                ($t->getWalletSource() ? $t->getWalletSource()->getRib() : 'N/A');

            $data[] = [
                'date' => $t->getDateTransaction() ? $t->getDateTransaction()->format('d/m/Y H:i') : 'N/A',
                'type' => $t->getType(),
                'amount' => number_format($t->getMontant(), 2),
                'currency' => $t->getCurrency() ? $t->getCurrency()->getNom() : '',
                'counterparty' => $counterparty,
                'direction' => $isDebit ? 'out' : 'in'
            ];
        }
        return new JsonResponse($data);
    }
}