<?php

namespace App\Controller\backOffice;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Service\CloudinaryService;
use App\Service\GeminiService;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/admin/products')]
class ProductController extends AbstractController
{
    private const ITEMS_PER_PAGE = 10;

    #[Route('/', name: 'app_back_product_index', methods: ['GET'])]
    public function index(Request $request, ProductRepository $productRepository, PaginatorInterface $paginator): Response
    {
        $keyword = $request->query->get('q', '');
        $sortBy = $request->query->get('sort', 'name');
        $sortDir = $request->query->get('dir', 'ASC');

        $query = $productRepository->searchAndSortQuery($keyword, $sortBy, $sortDir);
        $products = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE
        );

        $totalProducts = $productRepository->count([]);
        $lowStockCount = $productRepository->countLowStock(10);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'rows' => $this->renderView('backOffice/product/_table_rows.html.twig', [
                    'products' => $products,
                ]),
                'pagination' => $this->renderView('backOffice/product/_pagination.html.twig', [
                    'products' => $products,
                ]),
                'totalItemCount' => $products->getTotalItemCount(),
            ]);
        }

        return $this->render('backOffice/product/index.html.twig', [
            'products' => $products,
            'total_products' => $totalProducts,
            'low_stock_count' => $lowStockCount,
            'q' => $keyword,
            'sort' => $sortBy,
            'dir' => $sortDir,
        ]);
    }

    #[Route('/new', name: 'app_back_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, UtilisateurRepository $userRepo, CloudinaryService $cloudinaryService): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                // 1. Upload to Cloudinary FIRST (while temp file still exists)
                $cloudinaryUrl = $cloudinaryService->uploadImage($imageFile);

                // 2. Then save locally as backup
                try {
                    $imageFile->move(
                        $this->getParameter('products_images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                }

                // Use Cloudinary URL if available, otherwise use local filename
                if ($cloudinaryUrl) {
                    $product->setImageUrl($cloudinaryUrl);
                } else {
                    $product->setImageUrl($newFilename);
                }
            }

            // For now, assign to the first user since security is not fully implemented
            $user = $userRepo->findOneBy([]);
            if ($user) {
                $product->setUtilisateur($user);
            }
            
            $product->setCreatedAt(new \DateTime());
            $product->setUpdatedAt(new \DateTime());
            $product->setStatus('available');

            $entityManager->persist($product);
            $entityManager->flush();



            return $this->redirectToRoute('app_back_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('backOffice/product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    /**
     * AJAX endpoint to generate description using Gemini AI.
     * MUST be defined BEFORE /{id} to avoid route conflict.
     */
    #[Route('/ai/generate', name: 'app_back_product_ai_generate', methods: ['POST'])]
    public function aiGenerateDescription(Request $request, GeminiService $geminiService): JsonResponse
    {
        $name = $request->request->get('name', '');
        $category = $request->request->get('category', '');

        if (empty($name)) {
            return $this->json(['error' => 'Le nom du produit est requis pour la génération.'], 400);
        }

        $description = $geminiService->generateDescription($name, $category);

        return $this->json(['description' => $description]);
    }

    /**
     * AJAX endpoint to refine/correct description using Gemini AI.
     * MUST be defined BEFORE /{id} to avoid route conflict.
     */
    #[Route('/ai/refine', name: 'app_back_product_ai_refine', methods: ['POST'])]
    public function aiRefineDescription(Request $request, GeminiService $geminiService): JsonResponse
    {
        $description = $request->request->get('description', '');

        if (empty($description)) {
            return $this->json(['error' => 'La description est vide.'], 400);
        }

        $refined = $geminiService->refineDescription($description);

        return $this->json(['description' => $refined]);
    }

    #[Route('/{id}', name: 'app_back_product_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('backOffice/product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_back_product_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager, SluggerInterface $slugger, CloudinaryService $cloudinaryService): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                // 1. Upload to Cloudinary FIRST (while temp file still exists)
                $cloudinaryUrl = $cloudinaryService->uploadImage($imageFile);

                // 2. Then save locally as backup
                try {
                    $imageFile->move(
                        $this->getParameter('products_images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                }

                // Use Cloudinary URL if available, otherwise use local filename
                if ($cloudinaryUrl) {
                    $product->setImageUrl($cloudinaryUrl);
                } else {
                    $product->setImageUrl($newFilename);
                }
            }

            $product->setUpdatedAt(new \DateTime());

            $entityManager->flush();



            return $this->redirectToRoute('app_back_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('backOffice/product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_back_product_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($product);
            $entityManager->flush();

        }

        return $this->redirectToRoute('app_back_product_index', [], Response::HTTP_SEE_OTHER);
    }
}