<?php

namespace App\Controller\backOffice;

use App\Entity\Formation;
use App\Form\FormationType;
use App\Repository\FormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class FormationAdminController extends AbstractController
{
    #[Route('/admin/formation', name: 'app_admin_formation_index', methods: ['GET'])]
    public function index(Request $request, FormationRepository $formationRepository): Response
    {
        $search = $request->query->get('q');
        $domaine = $request->query->get('domaine');
        $sort = $request->query->get('sort');

        $formations = $formationRepository->findForAdminList($search, $domaine, $sort);
        $domaines = $formationRepository->findDistinctDomaines();

        return $this->render('admin_panel/formation/index.html.twig', [
            'formations' => $formations,
            'domaines' => $domaines,
            'search' => $search,
            'domaine' => $domaine,
            'sort' => $sort,
        ]);
    }

    #[Route('/admin/formation/new', name: 'app_admin_formation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $formation = new Formation();
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($formation);
            $entityManager->flush();

            $this->addFlash('success', 'La formation a bien été créée.');

            return $this->redirectToRoute('app_admin_formation_index');
        }

        return $this->render('admin_panel/formation/new.html.twig', [
            'formation' => $formation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/formation/{id}/show', name: 'app_admin_formation_show', methods: ['GET'])]
    public function show(Formation $formation): Response
    {
        return $this->render('admin_panel/formation/show.html.twig', [
            'formation' => $formation,
        ]);
    }

    #[Route('/admin/formation/{id}/edit', name: 'app_admin_formation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'La formation a été mise à jour.');

            return $this->redirectToRoute('app_admin_formation_index');
        }

        return $this->render('admin_panel/formation/edit.html.twig', [
            'formation' => $formation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/formation/{id}/delete', name: 'app_admin_formation_delete', methods: ['POST'])]
    public function delete(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-formation' . $formation->getIdFormation(), $request->request->get('_token'))) {
            $entityManager->remove($formation);
            $entityManager->flush();
            $this->addFlash('success', 'La formation a été supprimée.');
        }

        return $this->redirectToRoute('app_admin_formation_index');
    }
}
