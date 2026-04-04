<?php

namespace App\Controller\frontOffice\credit;

use App\Entity\Credit;
use App\Form\CreditType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class CreditController extends AbstractController
{
    #[Route('/front/credit', name: 'app_front_credit')]
    public function index(Request $req, EntityManagerInterface $em): Response
    {
        $credit = new Credit();
        $form = $this->createForm(CreditType::class, $credit);

        $form->handleRequest($req);

        if ($form->isSubmitted() && $form->isValid()) {
            // AJOUT : Initialisation du statut
            // Pourquoi : Ta table possède une colonne 'status' qui ne peut pas être vide.
            $credit->setStatus('En attente');

            // AJOUT : Initialisation de la date de demande
            // Pourquoi : Pour remplir la colonne 'date_demande' automatiquement avec la date du jour.
            $credit->setDateDemande(new \DateTime());

            // AJOUT : Gestion des relations (Simulation de l'ID emprunteur)
            // Pourquoi : Ta base contient 'borrower_id' et 'project_id'. 
            // Si ces colonnes sont obligatoires (NOT NULL) dans ta base, l'insertion échouera sans ces valeurs.
            // $credit->setProject($someProjectEntity); 

            // ÉTAPE DE PERSISTANCE
            $em->persist($credit); // Prépare l'objet
            $em->flush(); // Exécute la requête INSERT réelle dans la table 'credit'

            // AJOUT : Message Flash
            // Pourquoi : Pour confirmer visuellement à l'utilisateur que l'ajout a réussi.
            $this->addFlash('success', 'Votre demande de crédit a été enregistrée avec succès.');

            return $this->redirectToRoute('app_front_credit');
        }

        return $this->render('front_office/credit/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}