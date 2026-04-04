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
    #[Route('/', name: 'app_wallet_index', methods: ['GET', 'POST'])]
    public function index(WalletRepository $walletRepository, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $newWallet = new Wallet();
        $form = $this->createForm(WalletType::class, $newWallet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$user) {
                $user = $entityManager->getRepository(Utilisateur::class)->find(1);
            }
            
            $newWallet->setUtilisateur($user);
            $newWallet->setSolde('0.00'); 
            
            do {
                $rib = (string)random_int(10000000, 99999999);
                $exists = $walletRepository->findOneBy(['rib' => $rib]);
            } while ($exists);
            
            $newWallet->setRib($rib);
            $newWallet->setDateCreation(new \DateTime());

            $entityManager->persist($newWallet);
            $entityManager->flush();

            $this->addFlash('success', 'New wallet created successfully! RIB: ' . $rib);
            return $this->redirectToRoute('app_wallet_index');
        }

        $walletsList = $user ? $walletRepository->findBy(['utilisateur' => $user]) : $walletRepository->findAll();

        return $this->render('wallet/wallet.html.twig', [
            'wallets' => $walletsList,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{idWallet}/edit', name: 'app_wallet_edit', methods: ['POST'])]
    public function edit(Request $request, Wallet $wallet, EntityManagerInterface $entityManager): Response
    {
        $statut = $request->request->get('statut');

        if ($statut) {
            // On ne change QUE le statut
            $wallet->setStatut($statut);
            $wallet->setDateDerniereModification(new \DateTime());
            
            $entityManager->flush();
            $this->addFlash('success', 'Status of Wallet #' . $wallet->getRib() . ' updated.');
        } else {
            $this->addFlash('error', 'Update failed: Status is missing.');
        }

        return $this->redirectToRoute('app_wallet_index');
    }
    #[Route('/{idWallet}/delete', name: 'app_wallet_delete', methods: ['POST'])]
    public function delete(Request $request, Wallet $wallet, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$wallet->getIdWallet(), $request->request->get('_token'))) {
            $totalSolde = 0;
            if ($wallet->getWalletCurrencys()) {
                foreach ($wallet->getWalletCurrencys() as $wc) {
                    $totalSolde += (float)$wc->getSolde();
                }
            }

            if ($totalSolde > 0) {
                $this->addFlash('error', 'Action denied: This wallet balance is not empty.');
            } else {
                $entityManager->remove($wallet);
                $entityManager->flush();
                $this->addFlash('success', 'Wallet has been permanently deleted.');
            }
        }
        return $this->redirectToRoute('app_wallet_index');
    }
}