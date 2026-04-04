<?php

namespace App\Controller\frontOffice;



use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\ProductRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\CurrencyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/cart')]
class CartController extends AbstractController
{
    #[Route('/', name: 'app_cart_index')]
    public function index(SessionInterface $session, ProductRepository $productRepository): Response
    {
        $panier = $session->get('panier', []);
        $items = [];
        $total = 0;

        foreach ($panier as $id => $quantity) {
            $product = $productRepository->find($id);
            if ($product) {
                $items[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'subtotal' => ($product->getDiscountPrice() ?: $product->getPrice()) * $quantity
                ];
                $total += ($product->getDiscountPrice() ?: $product->getPrice()) * $quantity;
            }
        }

        return $this->render('frontOffice/cart/index.html.twig', [
            'items' => $items,
            'total' => $total
        ]);
    }

    #[Route('/add/{id}', name: 'app_cart_add')]
    public function add($id, SessionInterface $session, ProductRepository $productRepository): Response
    {
        $product = $productRepository->find($id);
        if (!$product) {
            return $this->redirectToRoute('app_marketplace_index');
        }

        $panier = $session->get('panier', []);
        $currentQty = $panier[$id] ?? 0;

        // Check stock: do not allow more than available stock
        if ($currentQty >= $product->getStock()) {
            $this->addFlash('error', 'Quantité maximale atteinte ! Stock disponible : ' . $product->getStock());
            return $this->redirectToRoute('app_marketplace_index');
        }

        $panier[$id] = $currentQty + 1;
        $session->set('panier', $panier);

        return $this->redirectToRoute('app_marketplace_index');
    }

    #[Route('/ajax/update/{id}', name: 'app_cart_ajax_update', methods: ['POST'])]
    public function ajaxUpdate($id, Request $request, SessionInterface $session, ProductRepository $productRepository): Response
    {
        $panier = $session->get('panier', []);
        $action = $request->request->get('action');
        
        if (!empty($panier[$id])) {
            $product = $productRepository->find($id);
            if ($action === 'plus') {
                // Check stock: do not allow more than available stock
                if ($product && $panier[$id] >= $product->getStock()) {
                    return $this->json([
                        'success' => false,
                        'error' => 'Stock maximum atteint (' . $product->getStock() . ' unités)',
                        'quantity' => $panier[$id],
                        'subtotal' => ($product->getDiscountPrice() ?: $product->getPrice()) * $panier[$id],
                        'total' => $this->calculateTotal($panier, $productRepository),
                        'cartCount' => count($panier)
                    ]);
                }
                $panier[$id]++;
            } elseif ($action === 'minus') {
                if ($panier[$id] > 1) {
                    $panier[$id]--;
                } else {
                    unset($panier[$id]);
                    $session->set('panier', $panier);
                    return $this->json([
                        'success' => true,
                        'removed' => true,
                        'total' => $this->calculateTotal($panier, $productRepository),
                        'cartCount' => count($panier)
                    ]);
                }
            }
        }
        
        $session->set('panier', $panier);
        
        $total = 0;
        $itemSubtotal = 0;
        $itemQty = 0;
        
        foreach ($panier as $pid => $qty) {
            $product = $productRepository->find($pid);
            if ($product) {
                $price = ($product->getDiscountPrice() ?: $product->getPrice());
                $st = $price * $qty;
                $total += $st;
                if ($pid == $id) {
                    $itemSubtotal = $st;
                    $itemQty = $qty;
                }
            }
        }
        
        return $this->json([
            'success' => true,
            'quantity' => $itemQty,
            'subtotal' => $itemSubtotal,
            'total' => $total,
            'cartCount' => count($panier)
        ]);
    }

    private function calculateTotal($panier, $productRepository): float
    {
        $total = 0;
        foreach ($panier as $id => $qty) {
            $product = $productRepository->find($id);
            if ($product) {
                $total += ($product->getDiscountPrice() ?: $product->getPrice()) * $qty;
            }
        }
        return $total;
    }

    #[Route('/remove/{id}', name: 'app_cart_remove')]
    public function remove($id, SessionInterface $session): Response
    {
        $panier = $session->get('panier', []);

        if (!empty($panier[$id])) {
            unset($panier[$id]);
        }

        $session->set('panier', $panier);
        $this->addFlash('info', 'Product removed.');

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/checkout', name: 'app_cart_checkout', methods: ['GET', 'POST'])]
    public function checkout(Request $request, SessionInterface $session, ProductRepository $productRepository, UtilisateurRepository $userRepo, CurrencyRepository $currencyRepo, EntityManagerInterface $em): Response
    {
        $panier = $session->get('panier', []);
        if (empty($panier)) {
            $this->addFlash('warning', 'Your cart is empty.');
            return $this->redirectToRoute('app_marketplace_index');
        }

        if ($request->isMethod('POST')) {
            $user = $userRepo->findOneBy([]);
            if (!$user) {
                $this->addFlash('danger', 'User not found.');
                return $this->redirectToRoute('app_marketplace_index');
            }

            $order = new Order();
            $order->setUtilisateur($user);
            $order->setOrderDate(new \DateTime());
            $order->setStatus('pending_payment');
            
            // Get from form
            $order->setPaymentMethod($request->request->get('payment_method'));
            $order->setShippingAddress($request->request->get('address'));
            $order->setPhoneNumber($request->request->get('phone'));
            
            $totalAmount = 0;
            foreach ($panier as $id => $quantity) {
                $product = $productRepository->find($id);
                if ($product && $product->getStock() >= $quantity) {
                    $price = $product->getDiscountPrice() ?: $product->getPrice();
                    $subtotal = $price * $quantity;
                    $totalAmount += $subtotal;

                    $orderItem = new OrderItem();
                    $orderItem->setOrder($order);
                    $orderItem->setProduct($product);
                    $orderItem->setQuantity($quantity);
                    $orderItem->setUnitPrice($price);
                    $orderItem->setSubTotal($subtotal);
                    $orderItem->setDiscountApplied(0);
                    $orderItem->setUnit_price($price);
                    $orderItem->setSub_total($subtotal);
                    $orderItem->setDiscount_applied(0);

                    $product->setStock($product->getStock() - $quantity);
                    $em->persist($orderItem);
                }
            }

            $order->setTotalAmount($totalAmount);
            $em->persist($order);
            $em->flush();

            $session->remove('panier');
            $this->addFlash('success', 'Order placed successfully! Please finalize the ' . $order->getPaymentMethod() . ' payment.');

            return $this->redirectToRoute('app_order_index');
        }

        // GET: Show checkout form
        $total = $this->calculateTotal($panier, $productRepository);
        $currencies = $currencyRepo->findAll();

        return $this->render('frontOffice/cart/checkout.html.twig', [
            'total' => $total,
            'currencies' => $currencies
        ]);
    }
}