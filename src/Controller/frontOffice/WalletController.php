<?php

namespace App\Controller\frontOffice;

use App\Entity\Wallet;
use App\Entity\Utilisateur;
use App\Form\WalletType;
use App\Repository\WalletRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/wallet')]
class WalletController extends AbstractController
{
    /**
     * Affiche la liste et gère l'ajout d'un wallet
     */
    #[Route('/', name: 'app_wallet_index', methods: ['GET', 'POST'])]
    public function index(WalletRepository $walletRepository, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        
        // Initialisation pour le formulaire d'ajout
        $newWallet = new Wallet();
        $form = $this->createForm(WalletType::class, $newWallet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            // Sécurité : Forcer un utilisateur si non connecté pour le développement
            if (!$user) {
                $user = $entityManager->getRepository(Utilisateur::class)->find(1);
            }
            
            $newWallet->setUtilisateur($user);
            $newWallet->setSolde('0.00'); // Solde initial par défaut
            
            // Génération d'un RIB unique
            do {
                $rib = (string)random_int(10000000, 99999999);
                $exists = $walletRepository->findOneBy(['rib' => $rib]);
            } while ($exists);
            
            $newWallet->setRib($rib);
            $newWallet->setDateCreation(new \DateTime());

            $entityManager->persist($newWallet);
            $entityManager->flush();

            $this->addFlash('success', 'Nouveau wallet créé avec succès ! RIB : ' . $rib);
            return $this->redirectToRoute('app_wallet_index');
        }

        // Récupération de la liste (Filtrée par user ou globale pour test)
        $walletsList = $user ? $walletRepository->findBy(['utilisateur' => $user]) : $walletRepository->findAll();

        return $this->render('wallet/wallet.html.twig', [
            'wallets' => $walletsList,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Gère la modification d'un wallet existant
     */
    #[Route('/{idWallet}/edit', name: 'app_wallet_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Wallet $wallet, EntityManagerInterface $entityManager): Response
    {
        // On utilise le même WalletType pour la modification
        $form = $this->createForm(WalletType::class, $wallet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $wallet->setDateDerniereModification(new \DateTime());
            
            $entityManager->flush();

            $this->addFlash('success', 'Le wallet #' . $wallet->getIdWallet() . ' a été mis à jour.');
            return $this->redirectToRoute('app_wallet_index');
        }

        return $this->render('wallet/edit.html.twig', [
            'wallet' => $wallet,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Supprime un wallet si les conditions de solde sont respectées
     */
    #[Route('/{idWallet}/delete', name: 'app_wallet_delete', methods: ['POST'])]
    public function delete(Request $request, Wallet $wallet, EntityManagerInterface $entityManager): Response
    {
        // Vérification du token CSRF pour la sécurité
        if ($this->isCsrfTokenValid('delete'.$wallet->getIdWallet(), $request->request->get('_token'))) {
            
            $totalSolde = 0;
            // Vérification des devises associées (Logique métier de ton projet FinTech)
            if ($wallet->getWalletCurrencys()) {
                foreach ($wallet->getWalletCurrencys() as $wc) {
                    $totalSolde += (float)$wc->getSolde();
                }
            }

            if ($totalSolde > 0) {
                $this->addFlash('error', 'Action refusée : le solde de ce portefeuille n\'est pas vide.');
            } else {
                $entityManager->remove($wallet);
                $entityManager->flush();
                $this->addFlash('success', 'Le portefeuille a été supprimé définitivement.');
            }
        }

        return $this->redirectToRoute('app_wallet_index');
    }
}