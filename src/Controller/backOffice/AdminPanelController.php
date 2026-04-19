<?php

namespace App\Controller\backOffice;

use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminPanelController extends AbstractController
{
    #[Route('/admin/panel', name: 'app_admin_panel')]
    public function index(OrderRepository $orderRepo, ProductRepository $productRepo, UtilisateurRepository $userRepo): Response
    {
        $totalRevenue = $orderRepo->getTotalRevenue();
        $monthlyRevenueRaw = $orderRepo->getMonthlyRevenue();
        $categoryDist = $productRepo->getCategoryDistribution();
        $shippingStats = $orderRepo->getShippingAddressStats();
        
        // Prepare monthly revenue for Chart.js
        $months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        $monthlyData = array_fill(0, 12, 0);
        foreach ($monthlyRevenueRaw as $row) {
            $monthlyData[$row['month'] - 1] = (float)$row['total'];
        }

        return $this->render('admin_panel/index.html.twig', [
            'totalRevenue' => $totalRevenue,
            'monthlyData' => json_encode($monthlyData),
            'categoryDist' => json_encode($categoryDist),
            'shippingStats' => json_encode($shippingStats),
            'productCount' => $productRepo->count([]),
            'orderCount' => $orderRepo->count([]),
            'userCount' => $userRepo->count([]),
        ]);
    }
}