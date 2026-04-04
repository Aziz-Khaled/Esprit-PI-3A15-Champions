<?php

namespace App\Controller\frontOffice\credit;

use App\Entity\Projet;
use App\Entity\Utilisateur;
use App\Form\ProjetType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/front/credit')]
class ProjetcrController extends AbstractController
{
    /**
     * Liste des projets
     */
    #[Route('/liste', name: 'app_projet_index')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Récupération de tous les projets
        $projets = $entityManager->getRepository(Projet::class)->findAll();

        return $this->render('front_office/credit/listeprojet.html.twig', [
            'projets' => $projets,
        ]);
    }

    /**
     * Création d'un nouveau projet
     */
    #[Route('/nouveau-projet', name: 'app_projet_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $projet = new Projet();
        $form = $this->createForm(ProjetType::class, $projet);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Simulation de l'utilisateur connecté ID 1
                $user = $entityManager->getRepository(Utilisateur::class)->find(1);
                
                if ($user) {
                    $projet->setUtilisateur($user);
                    $projet->setStatus('DRAFT'); 

                    $entityManager->persist($projet);
                    $entityManager->flush();

                    $this->addFlash('success', 'Projet créé avec succès !');
                    return $this->redirectToRoute('app_projet_index');
                }
            } else {
                $this->addFlash('danger', 'Erreur lors de la création. Vérifiez les contraintes (titre, montant, dates).');
            }
        }

        return $this->render('front_office/credit/ajoutprojet.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Modification d'un projet existant
     */
    #[Route('/{id}/modifier', name: 'app_projet_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Projet $projet, EntityManagerInterface $entityManager): Response
    {
        // Symfony injecte automatiquement l'objet grâce à l'ID
        $form = $this->createForm(ProjetType::class, $projet);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Enregistre les modifications en base
                $entityManager->flush();

                $this->addFlash('success', 'Le projet a été mis à jour avec succès !');
                return $this->redirectToRoute('app_projet_index');
            } else {
                // Message d'erreur si les contraintes (@Assert) ne sont pas respectées
                $this->addFlash('danger', 'La modification a échoué. Veuillez vérifier les erreurs dans le formulaire.');
            }
        }

        return $this->render('front_office/credit/modifierprojet.html.twig', [
            'projet' => $projet,
            'form' => $form->createView(),
        ]);
    }
    // src/Controller/frontOffice/credit/ProjetcrController.php

#[Route('/{id}/supprimer', name: 'app_projet_delete', methods: ['POST'])]
public function delete(Request $request, Projet $projet, EntityManagerInterface $entityManager): Response
{
    // Vérification du jeton de sécurité CSRF
    if ($this->isCsrfTokenValid('delete'.$projet->getIdProjet(), $request->request->get('_token'))) {
        $entityManager->remove($projet);
        $entityManager->flush();
        
        $this->addFlash('success', 'Le projet a été supprimé avec succès.');
    } else {
        $this->addFlash('danger', 'Jeton de sécurité invalide. Suppression annulée.');
    }

    return $this->redirectToRoute('app_projet_index');
}
}