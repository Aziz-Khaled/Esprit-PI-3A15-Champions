<?php

namespace App\Controller\frontOffice;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/marketplace')]
class MarketplaceController extends AbstractController
{
    #[Route('/', name: 'app_marketplace_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        return $this->render('frontOffice/marketplace/index.html.twig', [
            'products' => $productRepository->findAll(),
        ]);
    }

    /**
     * AJAX endpoint for client-side product search + sort (tri).
     * Returns HTML partial of the product cards.
     */
    #[Route('/ajax-search', name: 'app_marketplace_ajax_search', methods: ['GET'])]
    public function ajaxSearch(Request $request, ProductRepository $productRepository): Response
    {
        $keyword = $request->query->get('q', '');
        $sortBy  = $request->query->get('sort', 'name');
        $sortDir = $request->query->get('dir', 'ASC');

        $products = $productRepository->searchAndSort($keyword, $sortBy, $sortDir);

        return $this->render('frontOffice/marketplace/_product_cards.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/product/{id}', name: 'app_marketplace_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('frontOffice/marketplace/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/buy/{id}', name: 'app_marketplace_buy', methods: ['POST'])]
    public function buy(Product $product, EntityManagerInterface $entityManager, UtilisateurRepository $userRepo): Response
    {
        // For now, assign to the first user
        $user = $userRepo->findOneBy([]);
        
        if (!$user) {
            $this->addFlash('error', 'Utilisateur non trouvé.');
            return $this->redirectToRoute('app_marketplace_index');
        }

        if ($product->getStock() <= 0) {
            $this->addFlash('error', 'Produit en rupture de stock.');
            return $this->redirectToRoute('app_marketplace_index');
        }

        // Create Order
        $order = new Order();
        $order->setUtilisateur($user);
        $order->setOrderDate(new \DateTime());
        $order->setStatus('pending_payment');
        $order->setTotalAmount($product->getPrice());
        $order->setPaymentMethod('crypto_wallet');
        $order->setShippingAddress('Default Address'); // Example
        $order->setPhoneNumber('00000000'); // Example

        // Create Order Item
        $orderItem = new OrderItem();
        $orderItem->setOrder($order);
        $orderItem->setProduct($product);
        $orderItem->setQuantity(1);
        $orderItem->setUnit_price($product->getPrice());
        $orderItem->setSub_total($product->getPrice());
        $orderItem->setDiscount_applied(0);
        $orderItem->setUnitPrice($product->getPrice()); // Sync both camelCase and snake_case for entity compatibility
        $orderItem->setSubTotal($product->getPrice());
        $orderItem->setDiscountApplied(0);

        // Update Stock
        $product->setStock($product->getStock() - 1);

        $entityManager->persist($order);
        $entityManager->persist($orderItem);
        $entityManager->flush();

        $this->addFlash('success', 'Commande passée avec succès ! Veuillez finaliser le paiement.');
        
        return $this->redirectToRoute('app_order_index');
    }
}
