<?php

namespace App\Controller\backOffice;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commercant/orders')]
class OrderController extends AbstractController
{
    #[Route('/', name: 'app_back_order_index', methods: ['GET'])]
    public function index(OrderRepository $orderRepository): Response
    {
        return $this->render('backOffice/order/index.html.twig', [
            'orders' => $orderRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'app_back_order_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        return $this->render('backOffice/order/show.html.twig', [
            'order' => $order,
        ]);
    }
}
