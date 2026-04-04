<?php

namespace App\Controller\frontOffice\credit;

use App\Entity\Credit;
use App\Entity\Negociation;
use App\Form\NegociationType;
use App\Repository\NegociationRepository;
use App\Repository\UtilisateurRepository; // Ajout indispensable
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/front/negociation')]
class NegociationController extends AbstractController
{
    /**
     * L'investisseur crée une offre
     */
    #[Route('/nouveau/{id}', name: 'app_front_negociation_new')]
    public function new(
        #[MapEntity(mapping: ['id' => 'id_credit'])] Credit $credit, 
        Request $request, 
        EntityManagerInterface $em,
        UtilisateurRepository $userRepo 
    ): Response {
        
        // SIMULATION : On récupère l'utilisateur ID 1 car l'auth n'est pas intégrée
        // Vérifie bien dans phpMyAdmin que tu as un utilisateur avec id_user = 1
        $user = $userRepo->find(1); 

        if (!$user) {
            throw new \Exception("Test bloqué : Aucun utilisateur trouvé avec l'ID 1 dans la table utilisateur.");
        }

        $negociation = new Negociation();
        $form = $this->createForm(NegociationType::class, $negociation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $negociation->setCredit($credit);
            $negociation->setUtilisateur($user); 
            $negociation->setStatus('PROPOSED');

            $em->persist($negociation);
            $em->flush();

            $this->addFlash('success', 'Proposition enregistrée avec succès !');
            return $this->redirectToRoute('app_front_negociation_received'); 
        }

        // CORRECTION : Le chemin doit être frontOffice (majuscule O) et newNegociation (majuscule N)
        return $this->render('front_office/credit/newNegociation.html.twig', [
            'form' => $form->createView(),
            'credit' => $credit
        ]);
    }

    /**
     * L'emprunteur voit ses offres reçues
     */
    #[Route('/mes-offres', name: 'app_front_negociation_received')]
    public function listReceived(NegociationRepository $repo, UtilisateurRepository $userRepo): Response
    {
        // On simule aussi l'utilisateur ici pour voir les offres
        $user = $userRepo->find(2); 

        // On passe l'utilisateur simulé au repository
        $offres = $repo->findByEmprunteur($user); 

        return $this->render('front_office/credit/received.html.twig', [
            'offres' => $offres
        ]);
    }

    /**
     * Accepter une offre
     */
    #[Route('/accepter/{id}', name: 'app_front_negociation_accept', methods: ['POST'])]
    public function accept(
        #[MapEntity(mapping: ['id' => 'id_negociation'])] Negociation $negociation, 
        EntityManagerInterface $em
    ): Response {
        $negociation->setStatus('ACCEPTED'); 
        $em->flush();

        $this->addFlash('success', 'Offre acceptée !');
        return $this->redirectToRoute('app_front_negociation_received');
    }

    /**
     * Rejeter une offre
     */
    #[Route('/rejeter/{id}', name: 'app_front_negociation_delete', methods: ['POST'])]
    public function delete(
        #[MapEntity(mapping: ['id' => 'id_negociation'])] Negociation $negociation, 
        EntityManagerInterface $em
    ): Response {
        $em->remove($negociation); 
        $em->flush();

        $this->addFlash('danger', 'Offre rejetée.');
        return $this->redirectToRoute('app_front_negociation_received');
    }
}