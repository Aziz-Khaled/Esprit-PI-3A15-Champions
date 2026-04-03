<?php

namespace App\Controller\backOffice;

use App\Entity\Asset;
use App\Entity\Utilisateur;
use App\Form\AssetType;
use App\Repository\AssetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/asset', name: 'app_backoffice_asset_')]
class AssetController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(AssetRepository $repo): Response
    {
        return $this->render('asset/asset.html.twig', [
            'mode'   => 'index',
            'assets' => $repo->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $asset = new Asset();
        $form  = $this->createForm(AssetType::class, $asset);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $asset->setCreatedAt(new \DateTimeImmutable());
            $asset->setUpdatedAt(new \DateTimeImmutable());
            $utilisateur = $em->getReference(Utilisateur::class, 1);
            $asset->setUtilisateur($utilisateur);
            $em->persist($asset);
            $em->flush();
            $this->addFlash('success', 'Asset added successfully.');
            return $this->redirectToRoute('app_backoffice_asset_index');
        }

        return $this->render('asset/asset.html.twig', [
            'mode'  => 'form',
            'asset' => $asset,
            'form'  => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Asset $asset, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(AssetType::class, $asset);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $asset->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Asset updated successfully.');
            return $this->redirectToRoute('app_backoffice_asset_index');
        }

        return $this->render('asset/asset.html.twig', [
            'mode'  => 'form',
            'asset' => $asset,
            'form'  => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Asset $asset, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$asset->getId(), $request->request->get('_token'))) {
            $em->remove($asset);
            $em->flush();
            $this->addFlash('warning', 'Asset deleted successfully.');
        }

        return $this->redirectToRoute('app_backoffice_asset_index');
    }
}