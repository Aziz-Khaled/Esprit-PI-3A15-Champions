<?php

namespace App\Controller\backOffice;

use App\Entity\Participation;
use App\Form\ParticipationType;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ParticipationAdminController extends AbstractController
{
    #[Route('/admin/participation', name: 'app_admin_participation_index', methods: ['GET'])]
    public function index(Request $request, ParticipationRepository $participationRepository): Response
    {
        $search = $request->query->get('q');
        
        $qb = $participationRepository->createQueryBuilder('p')
            ->leftJoin('p.formation', 'f')
            ->addSelect('f')
            ->leftJoin('p.utilisateur', 'u')
            ->addSelect('u')
            ->orderBy('p.dateInscription', 'DESC');

        if ($search) {
            $qb->andWhere('f.titre LIKE :search OR u.email LIKE :search OR p.statut LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $participations = $qb->getQuery()->getResult();

        return $this->render('admin_panel/participation/index.html.twig', [
            'participations' => $participations,
            'search' => $search,
        ]);
    }

    #[Route('/admin/participation/{id}/show', name: 'app_admin_participation_show', methods: ['GET'])]
    public function show(Participation $participation): Response
    {
        return $this->render('admin_panel/participation/show.html.twig', [
            'participation' => $participation,
        ]);
    }

    #[Route('/admin/participation/{id}/edit', name: 'app_admin_participation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Participation $participation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ParticipationType::class, $participation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'La participation a été mise à jour.');

            return $this->redirectToRoute('app_admin_participation_index');
        }

        return $this->render('admin_panel/participation/edit.html.twig', [
            'participation' => $participation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/participation/{id}/delete', name: 'app_admin_participation_delete', methods: ['POST'])]
    public function delete(Request $request, Participation $participation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-participation' . $participation->getIdParticipation(), $request->request->get('_token'))) {
            $entityManager->remove($participation);
            $entityManager->flush();
            $this->addFlash('success', 'La participation a été supprimée.');
        }

        return $this->redirectToRoute('app_admin_participation_index');
    }
}
