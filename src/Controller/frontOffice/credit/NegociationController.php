<?php

namespace App\Controller\frontOffice\credit;

use App\Entity\Credit;
use App\Entity\Negociation;
use App\Form\NegociationType;
use App\Repository\NegociationRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\CreditScorer;
use OpenAI;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

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
        $investisseur = $userRepo->find(1); // Test user
        if (!$investisseur) {
            throw $this->createNotFoundException("Investisseur test introuvable.");
        }

        $negociation = new Negociation();
        $form = $this->createForm(NegociationType::class, $negociation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $negociation->setCredit($credit);
            $negociation->setUtilisateur($investisseur); 
            $negociation->setStatus('PROPOSED');
            $negociation->setCreatedAt(new \DateTimeImmutable());

            $em->persist($negociation);
            $em->flush();

            $this->addFlash('success', 'Proposition envoyée !');
            return $this->redirectToRoute('app_front_negociation_received'); 
        }

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
        $user = $userRepo->find(2); 
        if (!$user) {
            throw $this->createNotFoundException("Emprunteur test introuvable.");
        }

        $offres = $repo->findByEmprunteur($user); 

        return $this->render('front_office/credit/received.html.twig', [
            'offres' => $offres
        ]);
    }

    /**
     * ÉTAPE 1 : Générer le contrat via OPENAI
     */
#[Route('/accepter/{id}', name: 'app_front_negociation_accept', methods: ['POST'])]
public function accept(
    #[MapEntity(mapping: ['id' => 'id_negociation'])] Negociation $negociation, 
    string $openAiKey,
    \App\Service\CreditScorer $scorer // Injection du nouveau service métier
): Response {
    $credit = $negociation->getCredit();

    // --- 1. APPEL À L'ALGORITHME MÉTIER AVANCÉ ---
    // Cette méthode isolée dans ton fichier CreditScorer calcule les scores dynamiquement.
    $analyseRisque = $scorer->getRiskAnalysis(
        $negociation->getMontant(), 
        $negociation->getTauxPropose()
    );

    // --- 2. GÉNÉRATION DU CONTRAT VIA IA ---
    $client = OpenAI::client($openAiKey);
    $prompt = sprintf(
        "Rédige un contrat de prêt financier formel en français. 
        Prêteur : %s. Emprunteur : %s. Montant : %.2f DT. Taux : %.2f%%. 
        Génère uniquement les clauses juridiques structurées.",
        $negociation->getUtilisateur()->getNom(),
        $credit->getBorrower()->getNom(),
        $negociation->getMontant(),
        $negociation->getTauxPropose()
    );

    try {
        $response = $client->chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => 'Tu es un expert juridique en Fintech.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $texteContrat = $response->choices[0]->message->content;
        
    } catch (\Exception $e) {
        // En cas d'erreur API (quota, débit), on affiche un message clair pour le debug.
        $texteContrat = "Le service de génération IA est indisponible (Erreur: " . $e->getMessage() . "). " .
                        "Veuillez procéder avec le mode de signature manuel.";
    }

    // --- 3. RENDU DE LA VUE ---
    return $this->render('front_office/credit/contract_view.html.twig', [
        'contrat' => $texteContrat,
        'negociation' => $negociation,
        'credit' => $credit,
        'analyse' => $analyseRisque // On envoie l'objet d'analyse complet pour le pop-up
    ]);
}

    /**
     * ÉTAPE 2 : Confirmation et ENVOI EMAIL
     */
    #[Route('/confirmer-signature/{id}', name: 'app_front_negociation_confirm_send', methods: ['POST'])]
    public function confirmAndSend(
        #[MapEntity(mapping: ['id' => 'id_negociation'])] Negociation $negociation,
        Request $request,
        MailerInterface $mailer,
        EntityManagerInterface $em
    ): Response {
        $texteContrat = $request->request->get('contrat_content');
        $credit = $negociation->getCredit();

        if (!$texteContrat) {
            $this->addFlash('error', 'Contenu du contrat manquant.');
            return $this->redirectToRoute('app_front_negociation_received');
        }

        try {
            $email = (new Email())
                ->from('noreply@champions-fintech.tn')
                ->to($credit->getBorrower()->getEmail()) 
                ->cc($negociation->getUtilisateur()->getEmail())
                ->subject('Contrat Signé - Champions Fintech #' . $negociation->getId())
                ->text($texteContrat);

            $mailer->send($email);

            $negociation->setStatus('ACCEPTED'); 
            $credit->setDateContrat(new \DateTime());
            // Hash sécurisé pour l'ID du contrat (Important pour ta spé Cyber)
            $credit->setContratId('OA-' . strtoupper(substr(md5($texteContrat), 0, 8)));

            $em->flush();
            $this->addFlash('success', 'Contrat signé et envoyé !');

        } catch (\Exception $e) {
            $this->addFlash('error', "Erreur lors de l'envoi.");
        }

        return $this->redirectToRoute('app_front_negociation_received');
    }
     #[Route('/rejeter/{id}', name: 'app_front_negociation_delete', methods: ['POST', 'GET'])]
    public function delete(
        #[MapEntity(mapping: ['id' => 'id_negociation'])] Negociation $negociation,
        EntityManagerInterface $entityManager
    ): Response {
        $entityManager->remove($negociation);
        $entityManager->flush();

        $this->addFlash('success', 'L\'offre a été rejetée et supprimée avec succès.');

        return $this->redirectToRoute('app_front_negociation_received');
    }

    #[Route('/contrat/{id}', name: 'app_front_credit_contract')]
    public function viewContract(Credit $credit): Response {
        if (!$credit->getContratId()) {
            $this->addFlash('error', "Contrat inexistant.");
            return $this->redirectToRoute('app_front_negociation_received');
        }
        return $this->render('front_office/credit/contract_view.html.twig', ['credit' => $credit]);
    }
}