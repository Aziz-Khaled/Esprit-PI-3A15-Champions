<?php

namespace App\Controller\frontOffice\credit;

use App\Entity\Credit;
use App\Entity\Utilisateur;
use App\Form\CreditType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/front/credit-gestion')]
class CreditController extends AbstractController
{
    /**
     * Liste des demandes de crédit
     */
    #[Route('/liste', name: 'app_credit_index')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Récupération de toutes les demandes de crédit
        $credits = $entityManager->getRepository(Credit::class)->findAll();

        return $this->render('front_office/credit/listecredit.html.twig', [
            'credits' => $credits,
        ]);
    }

    /**
     * Création d'une nouvelle demande de crédit
     */
   // src/Controller/frontOffice/credit/CreditController.php

#[Route('/nouvelle-demande', name: 'app_credit_new')]
public function new(Request $request, EntityManagerInterface $entityManager): Response
{
    $credit = new Credit();
    
    // FIX : On attribue l'emprunteur ICI, avant de gérer le formulaire
    $user = $entityManager->getRepository(Utilisateur::class)->find(1);
    if ($user) {
        $credit->setBorrower($user);
    }

    $form = $this->createForm(CreditType::class, $credit);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        // Le reste de tes réglages par défaut
        $credit->setStatus('OPEN'); 
        
        $entityManager->persist($credit);
        $entityManager->flush();

        $this->addFlash('success', 'Demande de crédit soumise avec succès !');
        return $this->redirectToRoute('app_credit_index');
    }

    return $this->render('front_office/credit/ajoutcredit.html.twig', [
        'form' => $form->createView(),
    ]);
}

    /**
     * Modification d'une demande de crédit existante
     */
    #[Route('/{id_credit}/modifier', name: 'app_credit_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Credit $credit, EntityManagerInterface $entityManager): Response
    {
        // Le ParamConverter récupère automatiquement l'objet Credit via id_credit
        $form = $this->createForm(CreditType::class, $credit);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $entityManager->flush();

                $this->addFlash('success', 'La demande de crédit a été mise à jour !');
                return $this->redirectToRoute('app_credit_index');
            } else {
                $this->addFlash('danger', 'Modification échouée. Vérifiez les erreurs.');
            }
        }

        return $this->render('front_office/credit/modifiercredit.html.twig', [
            'credit' => $credit,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Suppression d'une demande de crédit
     */
    #[Route('/{id_credit}/supprimer', name: 'app_credit_delete', methods: ['POST'])]
    public function delete(Request $request, Credit $credit, EntityManagerInterface $entityManager): Response
    {
        // On utilise getIdCredit() conformément à ton entité
        if ($this->isCsrfTokenValid('delete'.$credit->getIdCredit(), $request->request->get('_token'))) {
            $entityManager->remove($credit);
            $entityManager->flush();
            
            $this->addFlash('success', 'La demande de crédit a été supprimée.');
        } else {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
        }

        return $this->redirectToRoute('app_credit_index');
    }
}