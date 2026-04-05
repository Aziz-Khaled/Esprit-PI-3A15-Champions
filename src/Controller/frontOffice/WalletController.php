<?php

namespace App\Controller\frontOffice;

use App\Entity\Wallet;
use App\Entity\WalletCurrency;
use App\Entity\Utilisateur;
use App\Entity\Currency;
use App\Form\WalletType;
use App\Form\TransactionType; // Import important pour la modale
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
    public function index(WalletRepository $walletRepository, CurrencyRepository $currencyRepo, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            // Simulation d'utilisateur pour le projet académique
            $user = $entityManager->getRepository(Utilisateur::class)->find(1);
        }

        // --- Gestion du formulaire de création de Wallet ---
        $newWallet = new Wallet();
        $form = $this->createForm(WalletType::class, $newWallet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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

            $this->addFlash('success', 'New wallet created successfully! RIB: ' . $rib);
            return $this->redirectToRoute('app_wallet_index');
        }

        // --- Préparation du formulaire de Transaction pour la Modale ---
        $transactionForm = $this->createForm(TransactionType::class);

        $walletsList = $walletRepository->findBy(['utilisateur' => $user]);
        $allCurrencies = $currencyRepo->findAll();

        return $this->render('wallet/wallet.html.twig', [
            'wallets' => $walletsList,
            'form' => $form->createView(),
            'all_available_currencies' => $allCurrencies,
            'transactionForm' => $transactionForm->createView(), // Envoie la variable à Twig
        ]);
    }

    #[Route('/{id}/add-currency', name: 'app_wallet_add_currency', methods: ['POST'])]
    public function addCurrency(Request $request, Wallet $wallet, CurrencyRepository $currencyRepo, EntityManagerInterface $entityManager): Response
    {
        $idCurrency = $request->request->get('id_currency');
        $nomCurrency = $request->request->get('nom_currency');

        foreach ($wallet->getWalletCurrencys() as $existingWc) {
            if ($existingWc->getCurrency() && $existingWc->getCurrency()->getId() == $idCurrency) {
                $this->addFlash('error', 'This currency already exists in this wallet!');
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

        $this->addFlash('success', "Currency " . $wc->getNomCurrency() . " added.");
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
            $this->addFlash('success', 'Status updated.');
        }
        return $this->redirectToRoute('app_wallet_index');
    }

    #[Route('/{id}/delete', name: 'app_wallet_delete', methods: ['POST'])]
    public function delete(Request $request, Wallet $wallet, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$wallet->getIdWallet(), $request->request->get('_token'))) {
            if (!$wallet->getWalletCurrencys()->isEmpty()) {
                $this->addFlash('error', 'Cannot delete: wallet contains currencies.');
            } else {
                $entityManager->remove($wallet);
                $entityManager->flush();
                $this->addFlash('success', 'Wallet deleted.');
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
            $this->addFlash('error', 'Cannot remove currency with a positive balance.');
        }
        return $this->redirectToRoute('app_wallet_index');
    }

#[Route('/stats/chart-data', name: 'app_wallet_chart_data', methods: ['GET'])]
    public function getChartData(WalletCurrencyRepository $wcRepo, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            $user = $entityManager->getRepository(Utilisateur::class)->find(1);
        }

        // Appel de la méthode que nous avons créée dans le Repository
        $data = $wcRepo->sumBalancesByUser($user);

        return new JsonResponse($data);
    }
#[Route('/export/statement', name: 'app_wallet_export_pdf')]
public function exportStatement(EntityManagerInterface $entityManager): Response
{
    $user = $this->getUser() ?: $entityManager->getRepository(Utilisateur::class)->find(1);

    // Récupérer tous les wallets de l'utilisateur avec leurs transactions
    $wallets = $entityManager->getRepository(Wallet::class)->findBy(['utilisateur' => $user]);

    // Configurer Dompdf
    $pdfOptions = new Options();
    $pdfOptions->set('defaultFont', 'Arial');
    $dompdf = new Dompdf($pdfOptions);

    // Générer le HTML à partir d'un nouveau template twig
    $html = $this->renderView('wallet/statement_pdf.html.twig', [
        'user' => $user,
        'wallets' => $wallets,
        'date' => new \DateTime()
    ]);

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Envoyer le PDF au navigateur
    return new Response($dompdf->output(), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="statement_champions.pdf"'
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
        // Déterminer si c'est un débit (sortie) ou un crédit (entrée)
        $isDebit = ($t->getWalletSource() && $t->getWalletSource()->getIdWallet() === $wallet->getIdWallet());
        
        // Déterminer la contrepartie
        $counterparty = 'External';
        if ($isDebit) {
            $counterparty = $t->getWalletDestination() ? $t->getWalletDestination()->getRib() : 'N/A';
        } else {
            $counterparty = $t->getWalletSource() ? $t->getWalletSource()->getRib() : 'N/A';
        }

        $data[] = [
            'date' => $t->getDateTransaction() ? $t->getDateTransaction()->format('d/m/Y H:i') : 'N/A',
            'type' => $t->getType(),
            'amount' => number_format($t->getMontant(), 2),
            'currency' => $t->getCurrency() ? $t->getCurrency()->getNom() : '',
            'counterparty' => $counterparty,
            'direction' => $isDebit ? 'out' : 'in' // <--- On ajoute cette info
        ];
    }
    return new JsonResponse($data);
}
}