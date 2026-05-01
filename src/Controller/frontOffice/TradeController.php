<?php

namespace App\Controller\frontOffice;

use App\Entity\Trade;
use App\Form\TradeType;
use App\Repository\TradeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/trade', name: 'app_frontoffice_trade_')]
class TradeController extends AbstractController
{

/**
 * @return array<string, int>
 */
    private function getAssetChoices(EntityManagerInterface $em): array
    {
        $rows = $em->getConnection()->fetchAllAssociative(
            "SELECT id, symbol, name FROM asset WHERE status = 'ACTIVE' ORDER BY symbol ASC"
        );
        $choices = [];
        foreach ($rows as $row) {
            $choices[$row['symbol'] . ' — ' . $row['name']] = $row['id'];
        }
        return $choices;
    }

    /**
 * @return array<int, array{id: int, symbol: string, name: string}>
 */
    private function getAssetMap(EntityManagerInterface $em): array
    {
        $rows = $em->getConnection()->fetchAllAssociative(
            "SELECT id, symbol, name FROM asset"
        );
        $map = [];
        foreach ($rows as $row) {
            $map[$row['id']] = $row;
        }
        return $map;
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(TradeRepository $tradeRepo, EntityManagerInterface $em): Response
    {
        $trades = $tradeRepo->findBy([], ['createdAt' => 'DESC']);

        // Calcul des stats directement en PHP
        $stats = [
            'total'     => count($trades),
            'pending'   => count(array_filter($trades, fn($t) => $t->getStatus() === 'PENDING')),
            'executed'  => count(array_filter($trades, fn($t) => $t->getStatus() === 'EXECUTED')),
            'cancelled' => count(array_filter($trades, fn($t) => $t->getStatus() === 'CANCELLED')),
        ];

        return $this->render('trade/index.html.twig', [
            'trades' => $trades,
            'assets' => $this->getAssetMap($em),
            'stats'  => $stats,
            'mode'   => 'index',
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $trade = new Trade();
        $trade->setCreatedAt(new \DateTime());
        $trade->setStatus('PENDING');

        $form = $this->createForm(TradeType::class, $trade, [
            'asset_choices' => $this->getAssetChoices($em),
            'is_edit'       => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($trade->getOrderMode() === 'MARKET') {
                $trade->setPrice(null);
            }
            $em->persist($trade);
            $em->flush();

            $this->addFlash('success', 'Trade created successfully.');
            return $this->redirectToRoute('app_frontoffice_trade_index');
        }

        return $this->render('trade/index.html.twig', [
            'form'  => $form->createView(),
            'trade' => $trade,
            'mode'  => 'form',
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Trade $trade, EntityManagerInterface $em): Response
    {
        $assetMap = $this->getAssetMap($em);
        $asset    = $assetMap[$trade->getAssetId()] ?? null;

        return $this->render('trade/index.html.twig', [
            'trade' => $trade,
            'asset' => $asset,
            'mode'  => 'show',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Trade $trade, EntityManagerInterface $em): Response
    {
        if ($trade->getStatus() === 'EXECUTED') {
            $this->addFlash('warning', 'Executed trades cannot be modified.');
            return $this->redirectToRoute('app_frontoffice_trade_show', ['id' => $trade->getId()]);
        }

        $form = $this->createForm(TradeType::class, $trade, [
            'asset_choices' => $this->getAssetChoices($em),
            'is_edit'       => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($trade->getOrderMode() === 'MARKET') {
                $trade->setPrice(null);
            }
            if ($trade->getStatus() === 'EXECUTED' && $trade->getExecutedAt() === null) {
                $trade->setExecutedAt(new \DateTime());
            }
            $em->flush();

            $this->addFlash('success', 'Trade updated successfully.');
            return $this->redirectToRoute('app_frontoffice_trade_index');
        }

        return $this->render('trade/index.html.twig', [
            'form'  => $form->createView(),
            'trade' => $trade,
            'mode'  => 'form',
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Trade $trade, EntityManagerInterface $em): Response
    {
        if ($trade->getStatus() === 'EXECUTED') {
            $this->addFlash('danger', 'Executed trades cannot be deleted.');
            return $this->redirectToRoute('app_frontoffice_trade_index');
        }

        if ($this->isCsrfTokenValid('delete' . $trade->getId(), $request->request->get('_token'))) {
            $em->remove($trade);
            $em->flush();
            $this->addFlash('success', 'Trade deleted successfully.');
        } else {
            $this->addFlash('danger', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_frontoffice_trade_index');
    }
}