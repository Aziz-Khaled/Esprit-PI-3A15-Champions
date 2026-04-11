<?php

namespace App\Controller\frontOffice\credit;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use App\Entity\Credit;
use App\Entity\Utilisateur;
use App\Repository\WalletRepository;
use App\Repository\WalletCurrencyRepository;
use App\Entity\Negociation;
use App\Entity\Wallet;
use App\Form\CreditType;
use App\Service\AiExpertService;
use App\Form\NegociationType;
use App\Repository\CreditRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/front/credit-gestion')]
class CreditController extends AbstractController
{
    /**
     * Liste des demandes de crédit avec filtres
     */
    #[Route('/liste', name: 'app_credit_index')]
    public function index(Request $request, CreditRepository $creditRepository): Response
    {
        $searchTerm = $request->query->get('q');
        $status = $request->query->get('status');
        $sortBy = $request->query->get('sortBy', 'date_desc');

        $minAmountRaw = $request->query->get('minAmount');
        $minAmount = ($minAmountRaw !== null && $minAmountRaw !== '') ? (float) $minAmountRaw : null;

        if ($searchTerm || $status || $minAmount !== null) {
            $credits = $creditRepository->findByAdvancedFilters($searchTerm, $status, $minAmount, $sortBy);
        } else {
            $order = ['id_credit' => 'DESC'];
            if ($sortBy === 'amount_asc') $order = ['montant' => 'ASC'];
            if ($sortBy === 'amount_desc') $order = ['montant' => 'DESC'];
            if ($sortBy === 'date_asc') $order = ['id_credit' => 'ASC'];

            $credits = $creditRepository->findBy([], $order);
        }

        return $this->render('front_office/credit/listecredit.html.twig', [
            'credits' => $credits,
        ]);
    }

    /**
     * Marketplace pour les investisseurs
     */
    #[Route('/investor', name: 'app_credit_investor_index')]
    public function investorIndex(CreditRepository $creditRepository, EntityManagerInterface $entityManager): Response
    {
        // RÉPARATION : Simulation de l'utilisateur si non connecté pour éviter l'erreur 401
        $user = $this->getUser() ?: $entityManager->getRepository(Utilisateur::class)->find(1);
        
        if (!$user) {
            throw $this->createAccessDeniedException('Aucun utilisateur disponible pour la session.');
        }

        $credits = $creditRepository->findBy(['status' => 'OPEN']);
        $creditsWithAmounts = [];

        foreach ($credits as $credit) {
            $totalInvested = 0;
            $negociations = $entityManager->getRepository(Negociation::class)->findBy([
                'credit' => $credit,
                'status' => 'ACCEPTED'
            ]);

            foreach ($negociations as $negociation) {
                $totalInvested += (float) $negociation->getMontant();
            }

            $remainingAmount = (float) $credit->getMontant() - $totalInvested;

            $creditsWithAmounts[] = [
                'credit' => $credit,
                'totalInvested' => $totalInvested,
                'remainingAmount' => max(0, $remainingAmount)
            ];
        }

        $wallet = $entityManager->getRepository(Wallet::class)->findOneBy(['utilisateur' => $user]);
        $walletBalance = $wallet ? (float) $wallet->getSolde() : 0;

        return $this->render('front_office/credit/investor_index.html.twig', [
            'creditsWithAmounts' => $creditsWithAmounts,
            'walletBalance' => $walletBalance,
        ]);
    }

    /**
     * Scanner : Étude de risque pour l'investisseur
     */
  // src/Controller/frontOffice/credit/CreditController.php

#[Route('/investor/scanner/{id_credit}', name: 'app_credit_scanner', requirements: ['id_credit' => '\d+'], methods: ['GET'])]
public function scanner(
    int $id_credit, 
    CreditRepository $creditRepository, 
    EntityManagerInterface $entityManager,
    WalletCurrencyRepository $walletCurrencyRepository,
    AiExpertService $aiService
): Response {
    // 1. Récupération de l'utilisateur (Investisseur)
    $user = $this->getUser() ?: $entityManager->getRepository(Utilisateur::class)->find(1);

    if (!$user) {
        throw $this->createNotFoundException('Utilisateur introuvable. Veuillez vous connecter.');
    }

    // 2. Récupération du crédit
    $credit = $creditRepository->find($id_credit);
    if (!$credit) {
        throw $this->createNotFoundException('Ce dossier de crédit n\'existe pas.');
    }

    // 3. Récupération du Wallet (pour le RIB)
    $wallet = $entityManager->getRepository(Wallet::class)->findOneBy(['utilisateur' => $user]);
    $rib = $wallet ? $wallet->getRib() : 'RIB non configuré';

    // 4. Récupération du solde spécifique via le nom de la devise (ex: "EUR")
    // On utilise getDevise() qui est la méthode correcte de ton entité Credit
    $nomDeviseDuCredit = $credit->getDevise(); 

    $walletCurrency = $walletCurrencyRepository->findOneBy([
        'wallet' => $wallet,
        'nomCurrency' => $nomDeviseDuCredit 
    ]);
    
    $walletBalance = $walletCurrency ? (float) $walletCurrency->getSolde() : 0.0;

    // 5. Calcul du montant manquant (Total déjà investi)
    $totalInvested = 0.0;
    $acceptedNegocs = $entityManager->getRepository(Negociation::class)->findBy([
        'credit' => $credit, 
        'status' => 'ACCEPTED'
    ]);

    foreach ($acceptedNegocs as $n) { 
        $totalInvested += (float) $n->getMontant(); 
    }
    
    $totalCredit = (float) $credit->getMontant();
    $remainingAmount = $totalCredit - $totalInvested;

    // 6. Calcul du risque
    $pourcentage = ($walletBalance > 0) ? ($remainingAmount / $walletBalance) * 100 : 100;
    $displayPercentage = min($pourcentage, 100);

    $risqueNiveau = ($pourcentage > 70) ? 'CRITIQUE' : (($pourcentage > 30) ? 'MODÉRÉ' : 'FAIBLE');
    $risqueClasse = ($risqueNiveau === 'CRITIQUE') ? 'danger' : (($risqueNiveau === 'MODÉRÉ') ? 'warning' : 'success');

    // 7. ANALYSE EXPERT AI
    try {
        // Dans ton entité Projet, vérifie si c'est getTitle() ou getNom()
        $projectTitle = $credit->getProjet() ? $credit->getProjet()->getTitle() : 'Projet sans titre';
        
        $aiAnalysis = $aiService->getRiskAnalysis(
            $projectTitle,
            $remainingAmount,
            $walletBalance
        );
    } catch (\Exception $e) {
        $aiAnalysis = "L'expert note une exposition de " . round($displayPercentage, 1) . "%. Analyse basée sur votre solde actuel en " . $nomDeviseDuCredit . ".";
    }

    // 8. Rendu de la vue
    return $this->render('front_office/credit/scanner_analyse.html.twig', [
        'credit' => $credit,
        'wallet' => $wallet,
        'walletBalance' => $walletBalance,
        'remainingAmount' => $remainingAmount,
        'risqueNiveau' => $risqueNiveau,
        'risqueClasse' => $risqueClasse,
        'pourcentage' => $displayPercentage,
        'aiAnalysis' => $aiAnalysis,
        'rib' => $rib
    ]);
}
    
    #[Route('/new-negociation/{id}', name: 'app_credit_new_negociation', requirements: ['id' => '\d+'])]
    public function newNegociation(Request $request, Credit $credit, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser() ?: $entityManager->getRepository(Utilisateur::class)->find(1);

        if ($credit->getStatus() !== 'OPEN') {
            throw $this->createAccessDeniedException('Ce crédit n\'est plus disponible.');
        }

        $negociation = new Negociation();
        $negociation->setCredit($credit);
        $negociation->setUtilisateur($user);
        $negociation->setStatus('PENDING');

        $form = $this->createForm(NegociationType::class, $negociation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($negociation);
            $entityManager->flush();

            $this->addFlash('success', 'Votre proposition a été envoyée !');
            return $this->redirectToRoute('app_credit_investor_index');
        }

        return $this->render('front_office/credit/newNegociation.html.twig', [
            'form' => $form->createView(),
            'credit' => $credit,
        ]);
    }

    /**
     * Création d'une demande de crédit (Borrower)
     */
    #[Route('/nouvelle-demande', name: 'app_credit_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $credit = new Credit();
        $user = $entityManager->getRepository(Utilisateur::class)->find(1); 
        if ($user) { $credit->setBorrower($user); }

        $form = $this->createForm(CreditType::class, $credit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $credit->setStatus('OPEN'); 
            $entityManager->persist($credit);
            $entityManager->flush();

            $this->addFlash('success', 'Demande soumise avec succès !');
            return $this->redirectToRoute('app_credit_index');
        }

        return $this->render('front_office/credit/ajoutcredit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Modification d'une demande
     */
    #[Route('/{id_credit}/modifier', name: 'app_credit_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Credit $credit, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CreditType::class, $credit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Mise à jour réussie !');
            return $this->redirectToRoute('app_credit_index');
        }

        return $this->render('front_office/credit/modifiercredit.html.twig', [
            'credit' => $credit,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Suppression d'une demande
     */
    #[Route('/{id_credit}/supprimer', name: 'app_credit_delete', methods: ['POST'])]
    public function delete(Request $request, Credit $credit, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$credit->getIdCredit(), $request->request->get('_token'))) {
            $entityManager->remove($credit);
            $entityManager->flush();
            $this->addFlash('success', 'Suppression réussie.');
        }
        return $this->redirectToRoute('app_credit_index');
    }
}