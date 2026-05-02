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

        
        $currentUser = $this->getUser();

        // Build wallet data with TND balance for the JS popup
        $userWalletsData = [];
        if ($currentUser instanceof \App\Entity\Utilisateur) {
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
    
    
    $user = $this->getUser();

    if (!$user instanceof \App\Entity\Utilisateur) {
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
        
        // Store formation data in session for Google Calendar button
        $session = $request->getSession();
        $session->set('last_enrolled_formation', [
            'titre'       => $formation->getTitre(),
            'dateDebut'   => $formation->getDateDebut()->format('Ymd'),
            'dateFin'     => $formation->getDateFin()->format('Ymd'),
            'description' => substr($formation->getDescription(), 0, 150),
        ]);

    } catch (\Exception $e) {
        $this->addFlash('error', 'Transaction failed: ' . $e->getMessage());
    }

    return $this->redirectToRoute('app_formations_index');
}

    #[Route('/formations/ai-chat', name: 'app_formations_ai_chat', methods: ['POST'])]
    public function chatAi(Request $request, FormationRepository $formationRepository, \Symfony\Contracts\HttpClient\HttpClientInterface $httpClient): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userMessage = $data['message'] ?? '';

        if (empty($userMessage)) {
            return $this->json(['error' => 'Message vide'], 400);
        }

        

        $rawKey = $_ENV['GEMINI_API_KEY'] ?? $_SERVER['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ;
        $apiKey = is_array($rawKey) ? (string) end($rawKey) : (string) $rawKey;
        $apiKey = trim(str_replace(['"', "'"], '', $apiKey));

        if (empty($apiKey)) {
            $projectDir = $this->getParameter('kernel.project_dir');
            foreach (['.env.local', '.env'] as $envFile) {
                $envPath = $projectDir . '/' . $envFile;
                if (file_exists($envPath) && preg_match('/^GEMINI_API_KEY=(.+)$/m', file_get_contents($envPath), $matches)) {
                    $apiKey = trim(str_replace(['"', "'"], '', $matches[1]));
                    if (!empty($apiKey)) break;
                }
            }
        }
        
        if (empty($apiKey)) {
            return $this->json(['error' => 'Clé API Gemini non configurée.'], 500);
        }

        // Get open formations to build context
        $formations = $formationRepository->findBy(['statut' => 'OUVERTE']);
        $context = "Tu es un assistant virtuel pour la plateforme ChampionsPi. Voici la liste des formations disponibles actuellement :\n";
        foreach ($formations as $f) {
            $context .= "- " . $f->getTitre() . " (Domaine: " . $f->getDomaine() . ", Prix: " . $f->getPrix() . " TND)\n";
        }
        $context .= "\nRéponds à la question de l'utilisateur de manière concise, polie et utile en te basant UNIQUEMENT sur ces informations. Si l'utilisateur demande quelque chose qui n'est pas dans la liste, dis-lui poliment que tu n'as pas l'information.\n\nQuestion de l'utilisateur : " . $userMessage;

        try {
            $response = $httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent?key=' . $apiKey, [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $context]
                            ]
                        ]
                    ]
                ]
            ]);

            if ($response->getStatusCode() === 429) {
                return $this->json(['reply' => "Désolé, j'ai atteint ma limite de questions pour cette minute. Veuillez patienter 60 secondes avant de me reposer une question !"]);
            }

            $result = $response->toArray();
            $generatedText = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Désolé, je ne peux pas répondre pour le moment.';

            return $this->json(['reply' => trim($generatedText)]);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), '429')) {
                return $this->json(['reply' => "Quota dépassé (429). Attendez une minute."]);
            }
            return $this->json(['error' => 'Erreur lors de la génération avec IA : ' . $e->getMessage()], 500);
        }
    }

    #[Route('/formations/mes-certificats', name: 'app_formations_mes_certificats', methods: ['GET'])]
    public function mesCertificats(): Response
    {
        /** @var \App\Entity\Utilisateur $user */
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour voir vos certificats.');
            return $this->redirectToRoute('app_login');
        }

        $certificats = [];
        if (method_exists($user, 'getParticipations')) {
            $participations = $user->getParticipations();
            if ($participations) {
                foreach ($participations as $p) {
                    if (method_exists($p, 'getCertificats') && $p->getCertificats()) {
                        foreach ($p->getCertificats() as $c) {
                            $certificats[] = $c;
                        }
                    }
                }
            }
        }

        // Sort by date_emission descending if possible
        usort($certificats, function(\App\Entity\Certificat $a, \App\Entity\Certificat $b) {
    $dateA = $a->getDateEmission();
    $dateB = $b->getDateEmission();
    if ($dateA == $dateB) return 0;
    return $dateA > $dateB ? -1 : 1;
});

        return $this->render('formation/mes_certificats.html.twig', [
            'certificats' => $certificats,
        ]);
    }

    #[Route('/formations/certificat/{id}/pdf', name: 'app_formations_certificat_pdf', methods: ['GET'])]
    public function downloadPdf(\App\Entity\Certificat $certificat, \Symfony\Component\Routing\Generator\UrlGeneratorInterface $urlGenerator): Response
    {
        $user = $this->getUser();
        if (!$user || $certificat->getParticipation()->getUtilisateur() !== $user) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à télécharger ce certificat.');
            return $this->redirectToRoute('app_formations_mes_certificats');
        }

        // Generate Verification URL
        $verifyUrl = $urlGenerator->generate('app_verify_certificat', ['id' => $certificat->getIdCertificat()], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
        
        // Generate QR Code via external API as Base64 to embed in PDF (Using JPEG to avoid GD extension requirement)
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($verifyUrl) . '&format=jpeg';
        
        // Fetch image safely
        $qrCodeBase64 = '';
        try {
            $qrContent = @file_get_contents($qrCodeUrl);
            if ($qrContent !== false) {
                $qrCodeBase64 = 'data:image/jpeg;base64,' . base64_encode($qrContent);
            }
        } catch (\Exception $e) {}

        // Render HTML
        $html = $this->renderView('formation/pdf_certificat.html.twig', [
            'certificat' => $certificat,
            'qrCode' => $qrCodeBase64,
            'verifyUrl' => $verifyUrl
        ]);

        // Configure Dompdf
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true); // Allow remote images if any
        $options->set('isHtml5ParserEnabled', true);
        
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="Certificat_'.$certificat->getIdCertificat().'.pdf"'
            ]
        );
    }

    #[Route('/verify-certificat/{id}', name: 'app_verify_certificat', methods: ['GET'])]
    public function verify(\App\Entity\Certificat $certificat): Response
    {
        return $this->render('formation/verify_certificat.html.twig', [
            'certificat' => $certificat,
        ]);
    }
    #[Route('/formations/{id}/rate', name: 'app_formations_rate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rate(int $id, Request $request, \App\Repository\FormationRepository $formationRepository, EntityManagerInterface $em): \Symfony\Component\HttpFoundation\JsonResponse
    {
        /** @var \App\Entity\Utilisateur $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $note = (float) ($data['note'] ?? 0);
        
        if ($note < 1 || $note > 5) {
            return $this->json(['error' => 'Note invalide'], 400);
        }

        $formation = $formationRepository->find($id);
        if (!$formation) {
            return $this->json(['error' => 'Formation introuvable'], 404);
        }

        // Find existing participation and update the note
        $participation = $em->getRepository(\App\Entity\Participation::class)->findOneBy([
            'formation'   => $formation,
            'utilisateur' => $user,
        ]);

        if ($participation) {
            // Scale 1-5 stars → 1-20 note
            $participation->setNote($note * 4);
            $em->flush();
            return $this->json(['success' => true, 'note_saved' => $note * 4]);
        }

        return $this->json(['error' => 'Participation introuvable'], 404);
    }

    #[Route('/formations/mes-favoris', name: 'app_formations_mes_favoris', methods: ['GET'])]
    public function mesFavoris(\App\Repository\FormationRepository $formationRepository): Response
    {
        $formations = $formationRepository->findAll();
        return $this->render('formation/mes_favoris.html.twig', [
            'formations' => $formations,
        ]);
    }
}