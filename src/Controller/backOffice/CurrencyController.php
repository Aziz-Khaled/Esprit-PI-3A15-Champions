<?php

namespace App\Controller\backOffice;

use App\Entity\Currency;
use App\Form\CurrencyType;
use App\Repository\CurrencyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/currency')]
class CurrencyController extends AbstractController
{
    #[Route('/', name: 'app_admin_currency_index', methods: ['GET'])]
    public function index(CurrencyRepository $currencyRepository): Response
    {
        // Form for the "Add New" Modal
        $form = $this->createForm(CurrencyType::class, new Currency());

        return $this->render('admin_panel/currency_index.html.twig', [
            'currencies' => $currencyRepository->findAll(),
            'form_new' => $form->createView(),
        ]);
    }

    #[Route('/new', name: 'app_admin_currency_new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $currency = new Currency();
        $form = $this->createForm(CurrencyType::class, $currency);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($currency);
            $entityManager->flush();

            $this->addFlash('success', 'Currency has been successfully added.');
        } else {
            $this->addFlash('error', 'Failed to add currency. Please check your input.');
        }

        return $this->redirectToRoute('app_admin_currency_index');
    }

    #[Route('/{id}/edit', name: 'app_admin_currency_edit', methods: ['POST'])]
    public function edit(Request $request, Currency $currency, EntityManagerInterface $entityManager): Response
    {
        // Simple toggle for is_trading from the modal
        $isTrading = $request->request->get('is_trading') === 'on';

        if ($currency->getTypeCurrency() === 'crypto') {
            $currency->setIsTrading($isTrading);
            $entityManager->flush();
            $this->addFlash('success', 'Trading status updated successfully.');
        } else {
            $this->addFlash('error', 'Fiat currencies cannot be enabled for trading.');
        }

        return $this->redirectToRoute('app_admin_currency_index');
    }

    #[Route('/{id}/delete', name: 'app_admin_currency_delete', methods: ['POST'])]
    public function delete(Request $request, Currency $currency, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$currency->getId(), $request->request->get('_token'))) {
            
            // CHECK: Are there any users holding this currency?
            if (!$currency->getWalletCurrencys()->isEmpty()) {
                $this->addFlash('error', 'Cannot delete: Users currently hold this currency in their wallets.');
                return $this->redirectToRoute('app_admin_currency_index');
            }

            try {
                $entityManager->remove($currency);
                $entityManager->flush();
                $this->addFlash('success', 'Currency deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'An error occurred while deleting the currency.');
            }
        }

        return $this->redirectToRoute('app_admin_currency_index');
    }
}