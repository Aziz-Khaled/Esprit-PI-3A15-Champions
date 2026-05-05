<?php

namespace App\Controller\backOffice;

use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CommercantDashboardController extends AbstractController
{
    #[Route('/commercant/dashboard', name: 'app_commercant_dashboard')]
    public function index(OrderRepository $orderRepo, ProductRepository $productRepo, UtilisateurRepository $userRepo): Response
    {
        $totalRevenue      = $orderRepo->getTotalRevenue();
        $monthlyRevenueRaw = $orderRepo->getMonthlyRevenue();
        $categoryDist      = $productRepo->getCategoryDistribution();
        $shippingStats     = $orderRepo->getShippingAddressStats();

        // Monthly revenue for Chart.js
        $monthlyData = array_fill(0, 12, 0);
        foreach ($monthlyRevenueRaw as $row) {
            $monthlyData[$row['month'] - 1] = (float)$row['total'];
        }

        // Advanced revenue stats
        $revenueThisMonth = $orderRepo->getRevenueThisMonth();
        $revenueLastMonth = $orderRepo->getRevenueLastMonth();
        $revenueGrowth    = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : ($revenueThisMonth > 0 ? 100 : 0);

        // Order analytics
        $orderStatusDist   = $orderRepo->getOrderStatusDistribution();
        $paymentMethodDist = $orderRepo->getPaymentMethodDistribution();
        $avgOrderValue     = $orderRepo->getAverageOrderValue();
        $weeklyTrend       = $orderRepo->getWeeklyOrderTrend();
        $topProducts       = $orderRepo->getTopOrderedProducts(5);

        // Product analytics
        $topRatedProducts  = $productRepo->getTopRatedProducts(5);
        $lowStockProducts  = $productRepo->getLowStockProducts(10);
        $stockBreakdown    = $productRepo->getStockStatusBreakdown();
        $totalStockValue   = $productRepo->getTotalStockValue();
        $newProductsMonth  = $productRepo->getProductsAddedThisMonth();

        return $this->render('commercant_dashboard/index.html.twig', [
            'totalRevenue'      => $totalRevenue,
            'monthlyData'       => json_encode($monthlyData),
            'categoryDist'      => json_encode($categoryDist),
            'shippingStats'     => json_encode($shippingStats),
            'productCount'      => $productRepo->count([]),
            'orderCount'        => $orderRepo->count([]),
            'userCount'         => $userRepo->count([]),
            // Advanced stats
            'revenueThisMonth'  => $revenueThisMonth,
            'revenueLastMonth'  => $revenueLastMonth,
            'revenueGrowth'     => $revenueGrowth,
            'orderStatusDist'   => json_encode($orderStatusDist),
            'paymentMethodDist' => json_encode($paymentMethodDist),
            'avgOrderValue'     => $avgOrderValue,
            'weeklyTrend'       => json_encode($weeklyTrend),
            'topProducts'       => $topProducts,
            'topRatedProducts'  => $topRatedProducts,
            'lowStockProducts'  => $lowStockProducts,
            'stockBreakdown'    => $stockBreakdown,
            'totalStockValue'   => $totalStockValue,
            'newProductsMonth'  => $newProductsMonth,
        ]);
    }
}
