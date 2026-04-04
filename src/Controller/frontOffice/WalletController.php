<?php

namespace App\Controller\frontOffice;

use App\Entity\Wallet;
use App\Entity\WalletCurrency;
use App\Entity\Utilisateur;
use App\Entity\Currency;
use App\Form\WalletType;
use App\Repository\WalletRepository;
use App\Repository\CurrencyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/wallet')]
class WalletController extends AbstractController
{
    #[Route('/', name: 'app_wallet_index', methods: ['GET', 'POST'])]
    public function index(WalletRepository $walletRepository, CurrencyRepository $currencyRepo, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $user = $entityManager->getRepository(Utilisateur::class)->find(1);
        }

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

            $this->addFlash('success', 'Nouveau wallet créé avec succès ! RIB: ' . $rib);
            return $this->redirectToRoute('app_wallet_index');
        }

        $walletsList = $walletRepository->findBy(['utilisateur' => $user]);
        $allCurrencies = $currencyRepo->findAll();

        return $this->render('wallet/wallet.html.twig', [
            'wallets' => $walletsList,
            'form' => $form->createView(),
            'all_available_currencies' => $allCurrencies,
        ]);
    }

    // CORRECTION : {id} au lieu de {id_wallet} pour l'autowiring
    #[Route('/{id}/add-currency', name: 'app_wallet_add_currency', methods: ['POST'])]
    public function addCurrency(Request $request, Wallet $wallet, CurrencyRepository $currencyRepo, EntityManagerInterface $entityManager): Response
    {
        $idCurrency = $request->request->get('id_currency');
        $nomCurrency = $request->request->get('nom_currency');

        foreach ($wallet->getWalletCurrencys() as $existingWc) {
            if ($existingWc->getCurrency() && $existingWc->getCurrency()->getId() == $idCurrency) {
                $this->addFlash('error', 'Cette devise existe déjà dans ce wallet !');
                return $this->redirectToRoute('app_wallet_index');
            }
        }

        $currency = $currencyRepo->find($idCurrency);
        if (!$currency) {
            $this->addFlash('error', 'Devise introuvable.');
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

        $this->addFlash('success', "Devise " . $wc->getNomCurrency() . " ajoutée.");
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
            $this->addFlash('success', 'Statut mis à jour.');
        }
        return $this->redirectToRoute('app_wallet_index');
    }

    #[Route('/{id}/delete', name: 'app_wallet_delete', methods: ['POST'])]
    public function delete(Request $request, Wallet $wallet, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$wallet->getIdWallet(), $request->request->get('_token'))) {
            if (!$wallet->getWalletCurrencys()->isEmpty()) {
                $this->addFlash('error', 'Suppression impossible : le wallet contient des devises.');
            } else {
                $entityManager->remove($wallet);
                $entityManager->flush();
                $this->addFlash('success', 'Le wallet a été supprimé.');
            }
        }
        return $this->redirectToRoute('app_wallet_index');
    }
    /**
 * Supprime une devise spécifique d'un wallet si son solde est à 0
 */
#[Route('/currency/{id}/delete', name: 'app_wallet_currency_delete', methods: ['GET', 'POST'])]
public function deleteCurrency(WalletCurrency $wc, EntityManagerInterface $entityManager): Response
{
    // Sécurité supplémentaire : on vérifie que le solde est bien à 0
    if ($wc->getSolde() == 0) {
        $entityManager->remove($wc);
        $entityManager->flush();
        $this->addFlash('success', 'Devise retirée du wallet.');
    } else {
        $this->addFlash('error', 'Impossible de retirer une devise avec un solde positif.');
    }

    return $this->redirectToRoute('app_wallet_index');
}
}