<?php

namespace App\Controller\frontOffice;

use App\Entity\Participation;
use App\Repository\FormationRepository;
use App\Repository\WalletRepository;
use App\Repository\WalletCurrencyRepository;
use App\Service\TransactionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
class FormationController extends AbstractController
{
    #[Route('/formations', name: 'app_formations_index', methods: ['GET'])]
    public function index(
        FormationRepository $formationRepository,
        WalletRepository    $walletRepository,
        Request             $request
    ): Response {
        $search  = $request->query->get('search');
        $domaine = $request->query->get('domaine');
        $statut  = $request->query->get('statut');
        $sort    = $request->query->get('sort', 'date_asc');

        $formations = $formationRepository->findFiltered($search, $domaine, $statut, $sort);
        $domaines   = $formationRepository->findDistinctDomaines();

        /** @var \App\Entity\Utilisateur $currentUser */
        $currentUser = $this->getUser();

        // Build wallet data with TND balance for the JS popup
        $userWalletsData = [];
        if ($currentUser) {
            $wallets = $walletRepository->findBy([
                'utilisateur' => $currentUser,
                'typeWallet'  => 'fiat',
                'statut'      => 'actif',
            ]);

            foreach ($wallets as $wallet) {
                $tndBalance = 0.0;
                foreach ($wallet->getWalletCurrencys() as $wc) {
                    $nom = strtolower($wc->getNomCurrency());
                    $code = $wc->getCurrency() ? strtoupper($wc->getCurrency()->getCode()) : '';
                    if ($code === 'TND' || str_contains($nom, 'dinar')) {
                        $tndBalance = (float) $wc->getSolde();
                        break;
                    }
                }
                $userWalletsData[] = [
                    'id'         => $wallet->getIdWallet(),
                    'rib'        => $wallet->getRib(),
                    'tndBalance' => $tndBalance,
                ];
            }
        }

        return $this->render('formation/formation.html.twig', [
            'formations'      => $formations,
            'domaines'        => $domaines,
            'currentSearch'   => $search,
            'currentDomaine'  => $domaine,
            'currentStatut'   => $statut,
            'currentSort'     => $sort,
            'userWalletsData' => $userWalletsData,
        ]);
    }

    #[Route('/formations/{id}', name: 'app_formations_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, FormationRepository $formationRepository): Response
    {
        $formation = $formationRepository->find($id);
        if (!$formation) {
            throw $this->createNotFoundException('Formation not found.');
        }
        return $this->render('formation/show.html.twig', ['formation' => $formation]);
    }

  #[Route('/formations/enroll', name: 'app_formation_enroll', methods: ['POST'])]
public function enroll(
    Request $request,
    FormationRepository $formationRepository,
    WalletRepository $walletRepository,
    EntityManagerInterface $em,
    TransactionManager $transactionManager,
    MailerInterface $mailer 
): Response {
    $formationId = (int) $request->request->get('formation_id');
    $walletId = (int) $request->request->get('wallet_id');
    
    /** @var \App\Entity\Utilisateur $user */
    $user = $this->getUser();

    if (!$user) {
        $this->addFlash('error', 'You must be logged in to enroll.');
        return $this->redirectToRoute('app_login');
    }

    $formation = $formationRepository->find($formationId);
    if (!$formation || $formation->getStatut() !== 'OUVERTE') {
        $this->addFlash('error', 'Training program unavailable.');
        return $this->redirectToRoute('app_formations_index');
    }

    $existing = $em->getRepository(Participation::class)->findOneBy([
        'formation' => $formation,
        'utilisateur' => $user
    ]);
    if ($existing) {
        $this->addFlash('error', 'You are already enrolled in this program.');
        return $this->redirectToRoute('app_formations_index');
    }

    $buyerWallet = $walletRepository->find($walletId);
    $formateur = $formation->getUtilisateur();
    $sellerWallet = $walletRepository->findOneBy([
        'utilisateur' => $formateur,
        'typeWallet'  => 'fiat',
        'statut'      => 'actif'
    ]);

    if (!$buyerWallet || !$sellerWallet) {
        $this->addFlash('error', 'Wallet configuration error.');
        return $this->redirectToRoute('app_formations_index');
    }

    $currencyId = null;
    foreach ($buyerWallet->getWalletCurrencys() as $wc) {
        if (strtoupper($wc->getCurrency()->getCode()) === 'TND') {
            $currencyId = $wc->getCurrency()->getId();
            break;
        }
    }

    try {
        
        $transactionManager->execute(
            $buyerWallet->getRib(),
            $sellerWallet->getRib(),
            (float) $formation->getPrix(),
            'TRANSFERT', 
            $currencyId
        );

        // 2. Register Participation
        $participation = new Participation();
        $participation->setFormation($formation);
        $participation->setUtilisateur($user);
        $participation->setDateInscription(new \DateTime());
        $participation->setStatut('PAYEE');

        $em->persist($participation);
        $em->flush();

        
        try {
            $email = (new Email())
                ->from('eya.bouraoui2005@gmail.com')
                ->to($user->getEmail())
                ->subject('Enrollment Confirmation: ' . $formation->getTitre())
                ->html("
                    <div style='font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif; color: #333; max-width: 600px; margin: auto; border: 1px solid #eee; padding: 20px;'>
                        <h2 style='color: #1a73e8; text-align: center;'>Enrollment Successful!</h2>
                        <p>Dear <strong>" . $user->getNom() . "</strong>,</p>
                        <p>Thank you for choosing <strong>ChampionsPi</strong>. This email confirms your successful enrollment in the following training program:</p>
                        
                        <div style='background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                            <p style='margin: 5px 0;'><strong>Program:</strong> " . $formation->getTitre() . "</p>
                            <p style='margin: 5px 0;'><strong>Amount Paid:</strong> " . number_format($formation->getPrix(), 2) . " TND</p>
                            <p style='margin: 5px 0;'><strong>Transaction ID:</strong> Verified via Blockchain</p>
                            <p style='margin: 5px 0;'><strong>Date:</strong> " . (new \DateTime())->format('M d, Y - H:i') . "</p>
                        </div>

                        <p>You can now access your course materials and schedule from your student dashboard.</p>
                        
                        <p style='font-size: 0.9em; color: #777;'>
                            Your transaction has been securely processed and recorded on our internal blockchain for audit purposes.
                        </p>
                        
                        <hr style='border: 0; border-top: 1px solid #eee;'>
                        <p style='text-align: center; color: #1a73e8; font-weight: bold;'>ChampionsPi Learning Team</p>
                    </div>
                ");

            $mailer->send($email);
        } catch (\Exception $mailEx) {
            error_log("Mail delivery failed: " . $mailEx->getMessage());
        }

        $this->addFlash('success', 'Success! You have been enrolled. Please check your email for confirmation.');

    } catch (\Exception $e) {
        $this->addFlash('error', 'Transaction failed: ' . $e->getMessage());
    }

    return $this->redirectToRoute('app_formations_index');
}
}