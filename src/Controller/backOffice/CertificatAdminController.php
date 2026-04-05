<?php

namespace App\Controller\backOffice;

use App\Entity\Certificat;
use App\Form\CertificatType;
use App\Repository\CertificatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class CertificatAdminController extends AbstractController
{
    #[Route('/admin/certificat', name: 'app_admin_certificat_index', methods: ['GET'])]
    public function index(CertificatRepository $certificatRepository): Response
    {
        $certificats = $certificatRepository->findAll();

        return $this->render('admin_panel/certificat/index.html.twig', [
            'certificats' => $certificats,
        ]);
    }

    #[Route('/admin/certificat/new', name: 'app_admin_certificat_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $certificat = new Certificat();
        $form = $this->createForm(CertificatType::class, $certificat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($certificat);
            $entityManager->flush();

            $this->addFlash('success', 'Le certificat a bien été créé.');

            return $this->redirectToRoute('app_admin_certificat_index');
        }

        return $this->render('admin_panel/certificat/new.html.twig', [
            'certificat' => $certificat,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/certificat/{id}/show', name: 'app_admin_certificat_show', methods: ['GET'])]
    public function show(Certificat $certificat): Response
    {
        return $this->render('admin_panel/certificat/show.html.twig', [
            'certificat' => $certificat,
        ]);
    }

    #[Route('/admin/certificat/{id}/edit', name: 'app_admin_certificat_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Certificat $certificat, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CertificatType::class, $certificat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Le certificat a été mis à jour.');

            return $this->redirectToRoute('app_admin_certificat_index');
        }

        return $this->render('admin_panel/certificat/edit.html.twig', [
            'certificat' => $certificat,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/certificat/{id}/delete', name: 'app_admin_certificat_delete', methods: ['POST'])]
    public function delete(Request $request, Certificat $certificat, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-certificat' . $certificat->getIdCertificat(), $request->request->get('_token'))) {
            $entityManager->remove($certificat);
            $entityManager->flush();
            $this->addFlash('success', 'Le certificat a été supprimé.');
        }

        return $this->redirectToRoute('app_admin_certificat_index');
    }
}
