<?php

namespace App\Controller\frontOffice\credit;

use App\Entity\Credit;
use App\Entity\Negociation;
use App\Entity\Utilisateur;
use App\Form\NegociationType;
use App\Repository\NegociationRepository;
use App\Service\AmortissementService;
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
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
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
    Credit $credit,
    EntityManagerInterface $em,
    ContractPdfService $pdfService,
    AmortissementService $amortissementService,
    MailerInterface $mailer
): Response {
    $user = $this->getUser();

    // 1. SÉCURITÉ : Statut
    if ($credit->getStatus() === 'CLOSED') {
        $this->addFlash('warning', 'Ce contrat est déjà clôturé.');
        return $this->redirectToRoute('app_credit_index');
    }

    // Récupération sécurisée des emails
    $borrowerEmail = $credit->getBorrower() ? $credit->getBorrower()->getEmail() : null;
    $investorEmail = $credit->getInvestisseur() ? $credit->getInvestisseur()->getEmail() : null;

    if (!$borrowerEmail || !$investorEmail) {
        $this->addFlash('error', 'Erreur : L\'un des acteurs n\'a pas d\'adresse email renseignée.');
        return $this->redirectToRoute('app_credit_index');
    }

    try {
        // 2. LOGIQUE MÉTIER & HASH
        $emailForHash = $user ? $user->getUserIdentifier() : 'client-externe@champions.tn';
        $finalHash = hash('sha256', $credit->getIdCredit() . $emailForHash . bin2hex(random_bytes(8)) . time());

        $credit->setStatus('CLOSED');
        $credit->setContratId($finalHash);
        $credit->setDateContrat(new \DateTime());

        // 3. GÉNÉRATION DES DOCUMENTS
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/contracts/';
        
        // PDF 1 : Contrat
        $htmlContract = $this->renderView('front_office/credit/pdf_contract.html.twig', [
            'credit' => $credit,
            'final_hash' => $finalHash,
            'date' => new \DateTime(),
            'is_preview' => false
        ]);
        $contractFilename = 'Contract_Final_' . $credit->getIdCredit() . '.pdf';
        $pdfService->generateAndSaveContract($htmlContract, $contractFilename);

        // PDF 2 : Échéancier
        $tableauEcheance = $amortissementService->calculerTableau(
            $credit->getMontant(),
            $credit->getTaux(),
            $credit->getDuree()
        );
        $htmlEcheancier = $this->renderView('front_office/credit/pdf_echeancier.html.twig', [
            'credit' => $credit,
            'tableau' => $tableauEcheance
        ]);
        $echeancierFilename = 'Echeancier_' . $credit->getIdCredit() . '.pdf';
        $pdfService->generateAndSaveContract($htmlEcheancier, $echeancierFilename);

        // 4. SAUVEGARDE BDD
        $em->flush();

        // 5. ENVOI DE L'EMAIL (Mode Synchrone forcé)
        try {
            $email = (new TemplatedEmail())
                ->from('support@champions.tn')
                ->to($borrowerEmail)
                ->cc($investorEmail)
                ->subject('🏁 Financement Actif - ChampionsFinance #' . $credit->getIdCredit())
                ->htmlTemplate('front_office/credit/email_confirmation.html.twig')
                ->context([
                    'credit' => $credit,
                    'date' => new \DateTime()
                ]);

            // Vérification physique des fichiers avant attachement
            $contractPath = $uploadDir . $contractFilename;
            $echeancierPath = $uploadDir . $echeancierFilename;

            if (file_exists($contractPath)) {
                $email->attachFromPath($contractPath);
            }
            if (file_exists($echeancierPath)) {
                $email->attachFromPath($echeancierPath);
            }

            $mailer->send($email);
            
            $this->addFlash('success', 'Contrat signé ! Email envoyé à l\'emprunteur et à l\'investisseur.');

        } catch (\Exception $mailEx) {
            // Ici, on capture l'erreur exacte de Gmail
            $this->addFlash('warning', 'Contrat enregistré, mais erreur mail : ' . $mailEx->getMessage());
        }

    } catch (\Exception $e) {
        $this->addFlash('error', 'Erreur système : ' . $e->getMessage());
    }

    return $this->redirectToRoute('app_credit_index');
}


    #[Route('/client/contrat-previsualisation/{id}', name: 'app_front_credit_preview', methods: ['GET'])]
    public function previewContrat(Credit $credit): Response 
    {
        // 1. Sécurité : On s'assure que le crédit a bien un investisseur assigné
        if (!$credit->getInvestisseur()) {
            $this->addFlash('error', 'Ce crédit n\'a pas encore d\'investisseur.');
            return $this->redirectToRoute('app_credit_index');
        }

        // 2. Rendu de la vue avec toutes les variables nécessaires
        return $this->render('front_office/credit/pdf_contract.html.twig', [
            'credit'     => $credit,
            'investor'   => $credit->getInvestisseur(),
            'borrower'   => $credit->getBorrower(),
            'date'       => new \DateTime(),
            'is_preview' => true, 
            'final_hash' => bin2hex(random_bytes(16)), // Hash fictif pour la prévisualisation
        ]);
    }
 }// <--- Cette accolade ferme la classe NegociationController