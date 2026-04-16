<?php

namespace App\Controller\frontOffice\credit;

use App\Entity\Credit;
use App\Entity\Negociation;
use App\Entity\Utilisateur;
use App\Form\NegociationType;
use App\Repository\NegociationRepository;
use App\Repository\UtilisateurRepository;
use App\Service\CreditScorer;
use App\Service\SignatureProvider;
use App\Service\SmartContractGenerator;
use App\Service\ContractPdfService; // Ton service métier
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Wrapper pour la compatibilité SignatureProvider
 */
class UserSignatureWrapper implements UserInterface
{
    private $user;
    public function __construct(Utilisateur $user) { $this->user = $user; }
    public function getRoles(): array { return ['ROLE_USER']; }
    public function eraseCredentials(): void {}
    public function getUserIdentifier(): string { return (string) $this->user->getEmail(); }
    public function getPassword(): ?string { return $this->user->getMotDePasse(); }
}

#[Route('/front/negociation')]
class NegociationController extends AbstractController
{
    #[Route('/nouveau/{id}', name: 'app_front_negociation_new')]
    public function new(
        #[MapEntity(mapping: ['id' => 'id_credit'])] Credit $credit, 
        Request $request, 
        EntityManagerInterface $em,
        UtilisateurRepository $userRepo 
    ): Response {
        $investisseur = $userRepo->find(1); 
        if (!$investisseur) throw $this->createNotFoundException("Investisseur test introuvable.");

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

            $this->addFlash('success', 'Proposition envoyée avec succès !');
            return $this->redirectToRoute('app_front_negociation_received'); 
        }

        return $this->render('front_office/credit/newNegociation.html.twig', [
            'form' => $form->createView(),
            'credit' => $credit
        ]);
    }

    #[Route('/mes-offres', name: 'app_front_negociation_received')]
    public function listReceived(NegociationRepository $repo): Response
    {
        // Affiche toutes les offres pour tes tests en BDD
        return $this->render('front_office/credit/received.html.twig', [
            'offres' => $repo->findAll()
        ]);
    }

    #[Route('/accepter/{id}', name: 'app_front_negociation_accept', methods: ['POST', 'GET'])]
    public function accept(
        #[MapEntity(mapping: ['id' => 'id_negociation'])] Negociation $negociation, 
        CreditScorer $scorer,
        SmartContractGenerator $contractGenerator
    ): Response {
        $credit = $negociation->getCredit();
        $analyseRisque = $scorer->getRiskAnalysis($negociation->getMontant(), $negociation->getTauxPropose());

        // GÉNÉRATION MÉTIER LOCALE (Plus d'IA externe)
        $contractData = $contractGenerator->generateSmartContract($negociation);

        return $this->render('front_office/credit/contract_view.html.twig', [
            'contrat' => $contractData['body'],
            'fingerprint' => $contractData['hash'],
            'negociation' => $negociation,
            'credit' => $credit,
            'analyse' => $analyseRisque
        ]);
    }

    #[Route('/confirmer-signature/{id}', name: 'app_front_negociation_confirm_send', methods: ['POST'])]
public function confirmAndSend(
    #[MapEntity(mapping: ['id' => 'id_negociation'])] Negociation $negociation,
    Request $request,
    MailerInterface $mailer,
    EntityManagerInterface $em,
    SignatureProvider $signatureProvider
): Response {
    $texteContrat = $request->request->get('contrat_content');
    $credit = $negociation->getCredit();
    
    // On récupère l'investisseur (l'utilisateur lié à la négociation)
    $investisseur = $negociation->getUtilisateur();

    if (!$texteContrat) {
        $this->addFlash('error', 'Contenu du contrat manquant.');
        return $this->redirectToRoute('app_front_negociation_received');
    }

    try {
        // 1. Signature JWT technique (Preuve cryptographique)
        $jwtSignature = $signatureProvider->signContract($negociation, $texteContrat);

        // 2. Destinataire (Utilise l'email de l'emprunteur ou ton email de test)
        $recipientEmail = "gharbisarra38@gmail.com"; 

        // 3. Envoi de l'email avec preuve d'intégrité (Hash)
        $email = (new Email())
            ->from('noreply@champions-fintech.tn')
            ->to($recipientEmail) 
            ->subject('Contrat Scellé Numériquement - Champions Fintech #' . $negociation->getId_negociation())
            ->html(
                "<div style='font-family: Arial, sans-serif; color: #333; padding: 20px; border: 1px solid #eee;'>
                    <h2 style='color: #2c3e50;'>Signature Confirmée 🔐</h2>
                    <p>Le contrat de prêt a été généré et signé numériquement par l'investisseur.</p>
                    <p><b>Empreinte SHA-256 (Vérification d'intégrité) :</b> <br>
                    <code style='background:#f4f4(244, 244, 244); padding:5px; display:block; word-break: break-all;'>" . hash('sha256', $texteContrat) . "</code></p>
                    <hr style='border: 0; border-top: 1px solid #eee;'>
                    <div style='background-color:#fdfdfd; padding:20px; white-space: pre-wrap;'>
                        " . nl2br(htmlspecialchars($texteContrat)) . "
                    </div>
                    <p style='font-size: 0.8em; color: #7f8c8d; margin-top: 20px;'>
                        Signature ID : " . substr($jwtSignature, 0, 30) . "...
                    </p>
                </div>"
            );

        $mailer->send($email);

        // 4. MISE À JOUR DE LA BASE DE DONNÉES (Lien Investisseur-Crédit)
        
        // On lie l'investisseur au crédit
        if ($investisseur) {
            $credit->setInvestisseur($investisseur);
        }

        // Mise à jour de la négociation
        $negociation->setStatus('ACCEPTED'); 

        // Mise à jour du crédit
        $credit->setDateContrat(new \DateTime());
        $credit->setStatus('SIGNED'); // On passe le statut à SIGNED
        
        // On stocke une version courte ou complète du JWT comme ID de contrat
        $credit->setContratId('SECURE-' . substr($jwtSignature, -12));

        // On sauve tout d'un coup
        $em->flush();

        // 5. Flash JSON pour ton SweetAlert2
        $this->addFlash('success_json', json_encode([
            'titre' => 'Contrat Scellé !',
            'message' => 'L\'ID de l\'investisseur est enregistré et le contrat a été envoyé à : ' . $recipientEmail,
            'isComplet' => true
        ]));

    } catch (\Exception $e) {
        $this->addFlash('error', 'Échec du scellement : ' . $e->getMessage());
    }

    return $this->redirectToRoute('app_front_negociation_received');
}

    #[Route('/rejeter/{id}', name: 'app_front_negociation_delete', methods: ['POST', 'GET'])]
    public function delete(#[MapEntity(mapping: ['id' => 'id_negociation'])] Negociation $negociation, EntityManagerInterface $em): Response {
        $em->remove($negociation);
        $em->flush();
        $this->addFlash('success', 'Offre supprimée.');
        return $this->redirectToRoute('app_front_negociation_received');
    }
    #[Route('/client/sign-finish/{id}', name: 'app_front_client_sign_finish', methods: ['POST'])]
    public function clientSignFinish(
        #[MapEntity(mapping: ['id' => 'id_credit'])] Credit $credit, 
        EntityManagerInterface $em,
        ContractPdfService $pdfService,
        SignatureProvider $signatureProvider
    ): Response {
        
        // 1. On récupère l'utilisateur s'il existe
        $user = $this->getUser();

        try {
            // 2. Générer la preuve finale
            // Si l'utilisateur n'est pas connecté, on utilise une valeur par défaut pour éviter le plantage
            $emailForHash = $user ? $user->getEmail() : 'client-non-authentifie@champions.tn';
            
            // On s'assure que le contrat ID ne soit pas null pour le hash
            $baseId = $credit->getContratId() ?? 'STUB_ID_' . $credit->getIdCredit();
            
            $finalHash = hash('sha256', $baseId . $emailForHash . time());

            // 3. Contenu HTML pour le PDF
            $html = $this->renderView('front_office/credit/pdf_contract.html.twig', [
                'credit' => $credit,
                'final_hash' => $finalHash,
                'date' => new \DateTime()
            ]);

            // 4. Générer et sauvegarder le PDF
            $filename = 'Contract_Final_' . $credit->getIdCredit() . '.pdf';
            $pdfPath = $pdfService->generateAndSaveContract($html, $filename);

            // 5. Mise à jour de l'entité
            $credit->setStatus('COMPLETED');
            $credit->setContratId($finalHash); 

            $em->flush();

            $this->addFlash('success', 'Félicitations ! Le contrat est officiellement signé.');

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la signature : ' . $e->getMessage());
        }

        // Redirection vers la liste
        return $this->redirectToRoute('app_credit_index');
    }}