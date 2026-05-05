<?php

namespace App\Controller\frontOffice;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\UtilisateurRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Knp\Component\Pager\PaginatorInterface;
use App\Service\GrokService;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Route('/marketplace')]
class MarketplaceController extends AbstractController
{
    #[Route('/', name: 'app_marketplace_index', methods: ['GET'])]
    public function index(Request $request, ProductRepository $productRepository, PaginatorInterface $paginator): Response
    {
        $query = $productRepository->searchAndSortQuery();
       
        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            3 // items per page
        );

        return $this->render('frontOffice/marketplace/index.html.twig', [
            'products' => $pagination,
        ]);
    }

    /**
     * AJAX endpoint for client-side product search + sort (tri).
     * Returns HTML partial of the product cards.
     */
    #[Route('/ajax-search', name: 'app_marketplace_ajax_search', methods: ['GET'])]
    public function ajaxSearch(Request $request, ProductRepository $productRepository, PaginatorInterface $paginator): Response
    {
        $keyword = $request->query->get('q', '');
        $sortBy  = $request->query->get('sortBy', 'name');
        $sortDir = $request->query->get('sortDir', 'ASC');

        $query = $productRepository->searchAndSortQuery($keyword, $sortBy, $sortDir);
       
        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            3
        );

        return $this->render('frontOffice/marketplace/_product_cards.html.twig', [
            'products' => $pagination,
        ]);
    }

    #[Route('/product/{id}', name: 'app_marketplace_product_show', methods: ['GET'])]
    public function show(Product $product, ProductRepository $productRepository, GrokService $grokService): Response
    {
        // Fetch candidate products from the SAME category
        $candidates = $productRepository->findBy(
            ['category' => $product->getCategory()],
            ['createdAt' => 'DESC'],
            30
        );
       
        $aiData = [];
        if (count($candidates) > 1) {
            $aiData = $grokService->recommendSimilarProducts($product, $candidates, 4);
        }
       
        // Fetch the recommended products from DB and attach reasons
        $recommendations = [];
        if (!empty($aiData)) {
            foreach ($aiData as $item) {
                if (!isset($item['id'])) continue;
                $p = $productRepository->find($item['id']);
                if ($p) {
                    $recommendations[] = [
                        'product' => $p,
                        'reason'  => $item['reason'] ?? 'Recommandé par notre IA'
                    ];
                }
            }
        }
       
        // Final fallback if AI returns nothing or fails
        if (empty($recommendations)) {
            $fallbacks = $productRepository->findBy(
                ['category' => $product->getCategory()],
                ['avgRating' => 'DESC'],
                4
            );
           
            foreach ($fallbacks as $f) {
                if ($f->getId() === $product->getId()) continue;
                $recommendations[] = [
                    'product' => $f,
                    'reason'  => 'Produit populaire dans cette catégorie'
                ];
            }
        }

        return $this->render('frontOffice/marketplace/show.html.twig', [
            'product' => $product,
            'recommendations' => $recommendations
        ]);
    }

    #[Route('/buy/{id}', name: 'app_marketplace_buy', methods: ['POST'])]
    public function buy(Product $product, EntityManagerInterface $entityManager, UtilisateurRepository $userRepo, EmailService $emailService): Response
    {
        // For now, assign to the first user
        $user = $userRepo->findOneBy([]);
       
        if (!$user) {
            return $this->redirectToRoute('app_marketplace_index');
        }

        if ($product->getStock() <= 0) {
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
        $orderItem->setDiscount_applied('0');
        $orderItem->setUnitPrice($product->getPrice()); // Sync both camelCase and snake_case for entity compatibility
        $orderItem->setSubTotal($product->getPrice());
        $orderItem->setDiscountApplied('0');

        // Update Stock
        $product->setStock($product->getStock() - 1);

        $entityManager->persist($order);
        $entityManager->persist($orderItem);
        $entityManager->flush();

        // Send confirmation email
        try {
            // Send to the fixed recipient as requested
            $emailService->sendOrderConfirmation($order, 'thassanjebri99@gmail.com');
           
            // Also send to the user's email if it exists
            if ($user->getEmail()) {
                $emailService->sendOrderConfirmation($order, $user->getEmail());
            }
        } catch (\Exception $e) {
            // Silently fail email or log it so it doesn't break the purchase flow
        }


       
        return $this->redirectToRoute('app_order_index');
    }

    #[Route('/wishlist/toggle/{id}', name: 'app_wishlist_toggle', methods: ['POST'])]
    public function toggleWishlist(Product $product, SessionInterface $session): Response
    {
        $wishlist = $session->get('wishlist', []);
       
        if (in_array($product->getId(), $wishlist)) {
            $wishlist = array_diff($wishlist, [$product->getId()]);
            $isWishlisted = false;
        } else {
            $wishlist[] = $product->getId();
            $isWishlisted = true;
        }
       
        $session->set('wishlist', $wishlist);
       
        return $this->json([
            'success' => true,
            'isWishlisted' => $isWishlisted,
            'count' => count($wishlist)
        ]);
    }
}