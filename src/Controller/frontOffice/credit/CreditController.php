<?php

namespace App\Controller\frontOffice\credit;

use App\Entity\Credit;
use App\Entity\Utilisateur;
use App\Entity\Negociation;
use App\Entity\Wallet;
use App\Form\CreditType;
use App\Form\NegociationType;
use App\Repository\CreditRepository;
use App\Repository\WalletCurrencyRepository;
use App\Service\AiExpertService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/front/credit-gestion')]
class CreditController extends AbstractController
{
    /**
     * Liste des demandes de crédit avec filtres (Version fusionnée)
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
        $user = $this->getUser() ?: $entityManager->getRepository(Utilisateur::class)->find(1);
        
        if (!$user) {
            throw $this->createAccessDeniedException('Aucun utilisateur disponible.');
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
     * Scanner : Étude de risque avec Expert AI (Ta fonctionnalité clé)
     */
    #[Route('/investor/scanner/{id_credit}', name: 'app_credit_scanner', requirements: ['id_credit' => '\d+'], methods: ['GET'])]
public function scanner(
    int $id_credit, 
    CreditRepository $creditRepository, 
    EntityManagerInterface $entityManager,
    WalletCurrencyRepository $walletCurrencyRepository,
    AiExpertService $aiService
): Response {
    // 1. Récupération de l'utilisateur et du dossier
    $user = $this->getUser() ?: $entityManager->getRepository(Utilisateur::class)->find(1);
    $credit = $creditRepository->find($id_credit);

    if (!$credit) {
        throw $this->createNotFoundException('Dossier introuvable.');
    }

    // 2. Récupération du Wallet et du RIB
    $wallet = $entityManager->getRepository(Wallet::class)->findOneBy(['utilisateur' => $user]);
    $rib = $wallet ? $wallet->getRib() : 'RIB non configuré';

    // 3. Gestion de la devise et du solde
    $nomDeviseDuCredit = $credit->getDevise(); 
    $walletCurrency = $walletCurrencyRepository->findOneBy([
        'wallet' => $wallet,
        'nomCurrency' => $nomDeviseDuCredit 
    ]);
    
    $walletBalance = $walletCurrency ? (float) $walletCurrency->getSolde() : 0.0;

    // 4. Calcul du montant restant à financer
    $totalInvested = 0.0;
    $acceptedNegocs = $entityManager->getRepository(Negociation::class)->findBy([
        'credit' => $credit, 
        'status' => 'ACCEPTED'
    ]);

    foreach ($acceptedNegocs as $n) { 
        $totalInvested += (float) $n->getMontant(); 
    }
    
    $remainingAmount = (float)$credit->getMontant() - $totalInvested;

    // 5. Calcul des indicateurs de risque (Mathématiques)
    $pourcentage = ($walletBalance > 0) ? ($remainingAmount / $walletBalance) * 100 : 100;
    $displayPercentage = min($pourcentage, 100);

    $risqueNiveau = ($pourcentage > 70) ? 'CRITIQUE' : (($pourcentage > 30) ? 'MODÉRÉ' : 'FAIBLE');
    $risqueClasse = ($risqueNiveau === 'CRITIQUE') ? 'danger' : (($risqueNiveau === 'MODÉRÉ') ? 'warning' : 'success');

    // 6. Appel à l'IA avec gestion d'erreur transparente
    try {
        $projectTitle = $credit->getProjet() ? $credit->getProjet()->getTitle() : 'Projet sans titre';
        
        // On tente l'analyse via Groq
        $aiAnalysis = $aiService->getRiskAnalysis($projectTitle, $remainingAmount, $walletBalance);

    } catch (\Exception $e) {
        /**
         * Si l'API échoue, on affiche le message technique.
         * Cela te permettra de voir si c'est un problème de "401 Unauthorized" (clé) 
         * ou de "429 Too Many Requests" (quota).
         */
        $aiAnalysis = "⚠️ Erreur AI : " . $e->getMessage();
    }

    // 7. Rendu de la vue
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
        $user = $this->getUser();
if (!$user instanceof Utilisateur) {
    $user = $entityManager->getRepository(Utilisateur::class)->find(1);
}

        if ($credit->getStatus() !== 'OPEN') {
            throw $this->createAccessDeniedException('Crédit non disponible.');
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

            $this->addFlash('success', 'Proposition envoyée !');
            return $this->redirectToRoute('app_credit_investor_index');
        }

        return $this->render('front_office/credit/newNegociation.html.twig', [
            'form' => $form->createView(),
            'credit' => $credit,
        ]);
    }

    /**
     * Création demande (Borrower)
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
            $this->addFlash('success', 'Demande soumise !');
            return $this->redirectToRoute('app_credit_index');
        }

        return $this->render('front_office/credit/ajoutcredit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Edition
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
     * Suppression
     */
    #[Route('/{id_credit}/supprimer', name: 'app_credit_delete', methods: ['POST'])]
    public function delete(Request $request, Credit $credit, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$credit->getIdCredit(), $request->request->get('_token'))) {
            $entityManager->remove($credit);
            $entityManager->flush();
            $this->addFlash('success', 'Suppression réussie.');
        } else {
            $this->addFlash('danger', 'Jeton invalide.');
        }

        return $this->redirectToRoute('app_credit_index');
    }
}