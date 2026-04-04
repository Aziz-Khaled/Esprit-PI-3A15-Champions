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

#[Route('/admin/products')]
class ProductController extends AbstractController
{
    #[Route('/', name: 'app_back_product_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        return $this->render('backOffice/product/index.html.twig', [
            'products' => $productRepository->findAll(),
        ]);
    }

    /**
     * AJAX endpoint for admin-side product search + sort (tri).
     * Returns HTML partial of the product table rows.
     */
    #[Route('/ajax-search', name: 'app_back_product_ajax_search', methods: ['GET'])]
    public function ajaxSearch(Request $request, ProductRepository $productRepository): Response
    {
        $keyword = $request->query->get('q', '');
        $sortBy  = $request->query->get('sort', 'name');
        $sortDir = $request->query->get('dir', 'ASC');

        $products = $productRepository->searchAndSort($keyword, $sortBy, $sortDir);

        return $this->render('backOffice/product/_table_rows.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/new', name: 'app_back_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, UtilisateurRepository $userRepo): Response
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

                try {
                    $imageFile->move(
                        $this->getParameter('products_images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                }

                $product->setImageUrl($newFilename);
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

            $this->addFlash('success', 'Produit ajouté avec succès au marketplace !');

            return $this->redirectToRoute('app_back_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('backOffice/product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_back_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('backOffice/product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_back_product_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('products_images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                }

                $product->setImageUrl($newFilename);
            }

            $product->setUpdatedAt(new \DateTime());

            $entityManager->flush();

            $this->addFlash('success', 'Produit mis à jour avec succès !');

            return $this->redirectToRoute('app_back_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('backOffice/product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_back_product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($product);
            $entityManager->flush();
            $this->addFlash('success', 'Produit supprimé !');
        }

        return $this->redirectToRoute('app_back_product_index', [], Response::HTTP_SEE_OTHER);
    }
}
