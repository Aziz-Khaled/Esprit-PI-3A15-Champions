<?php

namespace App\Controller\backOffice;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use App\Service\AdminLogger;
use App\Form\AdminEditUserType;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/admin')]
final class AdminPanelController extends AbstractController
{
    // =========================================================================
    //  DASHBOARD — merged: your branch (user stats + chart) + collaborator
    //  (order/product revenue stats). Both sets of data are passed to the view.
    // =========================================================================
    #[Route('/panel', name: 'app_admin_panel')]
    public function index(
        EntityManagerInterface $em,
        AdminLogger $logger,

        UtilisateurRepository $userRepo
    ): Response {
        $repo = $em->getRepository(Utilisateur::class);

        // ── User status counts (your banch) ──
        $activeCount   = count($repo->findBy(['statut' => 'active']));
        $pendingCount  = count($repo->findBy(['statut' => 'pending']));
        $disabledCount = count($repo->findBy(['statut' => 'desactive']));

        // ── Registrations per month — last 12 months (your branch) ──
        $registrationsByMonth = [];
        $monthLabels          = [];

        for ($i = 11; $i >= 0; $i--) {
            $date      = new \DateTime("first day of -$i months");
            $dateStart = (clone $date)->modify('first day of this month')->setTime(0, 0, 0);
            $dateEnd   = (clone $date)->modify('last day of this month')->setTime(23, 59, 59);

            $monthLabels[] = $date->format('M Y');

            $count = $repo->createQueryBuilder('u')
                ->select('COUNT(u.idUser)')
                ->where('u.dateCreation >= :start')
                ->andWhere('u.dateCreation <= :end')
                ->setParameter('start', $dateStart)
                ->setParameter('end', $dateEnd)
                ->getQuery()
                ->getSingleScalarResult();

            $registrationsByMonth[] = (int) $count;
        }

        $recentLogs = $logger->findRecent(5);

        return $this->render('admin_panel/index.html.twig', [
            // user stats
            'activeCount'          => $activeCount,
            'pendingCount'         => $pendingCount,
            'disabledCount'        => $disabledCount,
            'registrationsByMonth' => $registrationsByMonth,
            'monthLabels'          => $monthLabels,
            'recentLogs'           => $recentLogs,
            'userCount'            => $userRepo->count([]),
        ]);
    }

    // =========================================================================
    //  PENDING USERS — with KnpPaginator (dev branch)
    // =========================================================================
    #[Route('/users/pending', name: 'app_admin_pending_users')]
    public function pendingUsers(
        UtilisateurRepository $repo,
        Request $request,
        PaginatorInterface $paginator
    ): Response {
        $query = $repo->createQueryBuilder('u')
            ->where('u.statut = :statut')
            ->setParameter('statut', 'pending')
            ->orderBy('u.dateCreation', 'DESC')
            ->getQuery();

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('admin_panel/pending_users.html.twig', [
            'users' => $pagination,
        ]);
    }

    // =========================================================================
    //  APPROVE USER — with AdminLogger + mailer notification (your branch)
    // =========================================================================
    #[Route('/users/approve/{id}', name: 'app_admin_approve_user', methods: ['POST'])]
    public function approveUser(
        Utilisateur $user,
        EntityManagerInterface $em,
        Request $request,
        CsrfTokenManagerInterface $csrf,
        AdminLogger $logger,
        MailerInterface $mailer,
        Environment $twig
    ): Response {
        if (!$csrf->isTokenValid(new CsrfToken('approve_' . $user->getIdUser(), $request->request->get('_token')))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user->setStatut('ACTIVE');
        $em->flush();

        $logger->log(
            action:      'APPROVE_USER',
            entity:      'Utilisateur',
            performedBy: $this->getUser()->getUserIdentifier(),
            details:     'Approved user: ' . $user->getEmail()
        );

        // Send approval email
        try {
            $html = $twig->render('emails/account_approved.html.twig', [
                'user'      => $user,
                'login_url' => $this->generateUrl('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

            $email = (new Email())
                ->from($_ENV['MAILER_FROM'])
                ->to($user->getEmail())
                ->subject('✅ Your NexVault account has been approved')
                ->html($html);

            $mailer->send($email);
        } catch (\Throwable $e) {
            // Email failure should not block the approval action
            $this->addFlash('warning', 'User approved but notification email could not be sent.');
        }

        $this->addFlash('success', $user->getPrenom() . ' ' . $user->getNom() . ' has been approved and notified by email.');
        return $this->redirectToRoute('app_admin_pending_users');
    }

    // =========================================================================
    //  REJECT USER — with AdminLogger + mailer notification (your branch)
    // =========================================================================
    #[Route('/users/reject/{id}', name: 'app_admin_reject_user', methods: ['POST'])]
    public function rejectUser(
        Utilisateur $user,
        EntityManagerInterface $em,
        Request $request,
        CsrfTokenManagerInterface $csrf,
        AdminLogger $logger,
        MailerInterface $mailer,
        Environment $twig
    ): Response {
        if (!$csrf->isTokenValid(new CsrfToken('reject_' . $user->getIdUser(), $request->request->get('_token')))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user->setStatut('BANNED');
        $em->flush();

        $logger->log(
            action:      'REJECT_USER',
            entity:      'Utilisateur',
            performedBy: $this->getUser()->getUserIdentifier(),
            details:     'Rejected user: ' . $user->getEmail()
        );

        // Send rejection email
        try {
            $html = $twig->render('emails/account_rejected.html.twig', [
                'user' => $user,
            ]);

            $email = (new Email())
                ->from($_ENV['MAILER_FROM'])
                ->to($user->getEmail())
                ->subject('❌ Your NexVault account application was not approved')
                ->html($html);

            $mailer->send($email);
        } catch (\Throwable $e) {
            $this->addFlash('warning', 'User rejected but notification email could not be sent.');
        }

        $this->addFlash('warning', $user->getPrenom() . ' ' . $user->getNom() . ' has been rejected and notified by email.');
        return $this->redirectToRoute('app_admin_pending_users');
    }

    // =========================================================================
    //  ALL USERS LIST — with KnpPaginator + filters (dev branch)
    // =========================================================================
    #[Route('/users/list', name: 'app_admin_users_list')]
    public function usersList(
        UtilisateurRepository $repo,
        Request $request,
        PaginatorInterface $paginator
    ): Response {
        $role   = $request->query->get('role');
        $statut = $request->query->get('statut');

        $qb = $repo->createQueryBuilder('u');

        if ($role)   { $qb->andWhere('u.role = :role')->setParameter('role', $role); }
        if ($statut) { $qb->andWhere('u.statut = :statut')->setParameter('statut', $statut); }

        $pagination = $paginator->paginate(
            $qb->getQuery(),
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('admin_panel/users_list.html.twig', [
            'users'          => $pagination,
            'selectedRole'   => $role,
            'selectedStatut' => $statut,
        ]);
    }

    // =========================================================================
    //  EDIT USER — with AdminLogger (your branch)
    // =========================================================================
    #[Route('/users/edit/{id}', name: 'app_admin_edit_user', methods: ['GET', 'POST'])]
    public function editUser(
        Utilisateur $user,
        Request $request,
        EntityManagerInterface $em,
        AdminLogger $logger
    ): Response {
        $form = $this->createForm(AdminEditUserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $logger->log(
                action:      'EDIT_USER',
                entity:      'Utilisateur',
                performedBy: $this->getUser()->getUserIdentifier(),
                details:     'Edited user: ' . $user->getEmail()
            );

            $this->addFlash('success', $user->getPrenom() . ' ' . $user->getNom() . ' has been updated.');
            return $this->redirectToRoute('app_admin_users_list');
        }

        return $this->render('admin_panel/edit_user.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    // =========================================================================
    //  DELETE USER — with AdminLogger (your branch)
    // =========================================================================
    #[Route('/users/delete/{id}', name: 'app_admin_delete_user', methods: ['POST'])]
    public function deleteUser(
        Utilisateur $user,
        EntityManagerInterface $em,
        Request $request,
        CsrfTokenManagerInterface $csrf,
        AdminLogger $logger
    ): Response {
        if (!$csrf->isTokenValid(new CsrfToken('delete_' . $user->getIdUser(), $request->request->get('_token')))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $em->remove($user);
        $em->flush();

        $logger->log(
            action:      'DELETE_USER',
            entity:      'Utilisateur',
            performedBy: $this->getUser()->getUserIdentifier(),
            details:     'Deleted user: ' . $user->getEmail()
        );

        $this->addFlash('success', 'User has been deleted.');
        return $this->redirectToRoute('app_admin_users_list');
    }

    // =========================================================================
    //  SEARCH
    // =========================================================================
    #[Route('/admin/search', name: 'app_admin_search', methods: ['GET'])]
    public function search(Request $request, UtilisateurRepository $repo): Response
    {
        $q     = trim($request->query->get('q', ''));
        $users = strlen($q) >= 2 ? $repo->searchByKeyword($q) : [];

        $active  = array_filter($users, fn($u) => $u->getStatut() !== 'pending');
        $pending = array_filter($users, fn($u) => $u->getStatut() === 'pending');

        return $this->render('admin_panel/search.html.twig', [
            'q'       => $q,
            'active'  => array_values($active),
            'pending' => array_values($pending),
            'total'   => count($users),
        ]);
    }

    // =========================================================================
    //  ADMIN LOGS
    // =========================================================================
    #[Route('/logs', name: 'app_admin_logs', methods: ['GET'])]
    public function logs(Request $request, AdminLogger $logger): Response
    {
        $from = $request->query->get('from');
        $to   = $request->query->get('to');

        $logs = $logger->findByDateRange($from, $to);

        return $this->render('admin_panel/logs.html.twig', [
            'logs' => $logs,
            'from' => $from,
            'to'   => $to,
        ]);
    }

    // =========================================================================
    //  FACE CHECK (dev branch)
    // =========================================================================
    #[Route('/users/face-check/{id}', name: 'app_admin_face_check', methods: ['GET'])]
    public function faceCheck(Utilisateur $user): JsonResponse
    {
        $projectDir = $this->getParameter('kernel.project_dir');

        $selfiePath = $projectDir . '/public/uploads/users/selfies/' . $user->getUserImage();
        $idPath     = $projectDir . '/public/uploads/users/ids/'     . $user->getPieceIdentite();

        if (!file_exists($selfiePath) || !file_exists($idPath)) {
            return new JsonResponse([
                'success' => false,
                'error'   => 'One or both image files not found on disk.',
            ], 404);
        }

        $apiKey    = $_ENV['FACEPP_API_KEY'];
        $apiSecret = $_ENV['FACEPP_API_SECRET'];

        $ch = curl_init('https://api-us.faceplusplus.com/facepp/v3/compare');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'api_key'     => $apiKey,
                'api_secret'  => $apiSecret,
                'image_file1' => new \CURLFile($selfiePath),
                'image_file2' => new \CURLFile($idPath),
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        $raw     = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return new JsonResponse(['success' => false, 'error' => 'cURL error: ' . $curlErr], 500);
        }

        $data = json_decode($raw, true);

        if (isset($data['error_message'])) {
            return new JsonResponse(['success' => false, 'error' => $data['error_message']]);
        }

        $confidence = round($data['confidence'] ?? 0, 1);
        $threshold  = $data['thresholds']['1e-5'] ?? 73.975;

        return new JsonResponse([
            'success'    => true,
            'confidence' => $confidence,
            'threshold'  => $threshold,
            'match'      => $confidence >= $threshold,
        ]);
    }

    // =========================================================================
    //  EXTRACT ID — OCR via Tesseract (dev branch)
    // =========================================================================
    #[Route('/users/extract-id/{id}', name: 'app_admin_extract_id', methods: ['GET'])]
    public function extractId(Utilisateur $user): JsonResponse
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $idPath     = $projectDir . '/public/uploads/users/ids/' . $user->getPieceIdentite();

        if (!file_exists($idPath)) {
            return new JsonResponse(['success' => false, 'error' => 'ID file not found.'], 404);
        }

        $outputDir = $projectDir . '\\var\\ocr_tmp';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $outputBase   = $outputDir . '\\ocr_' . $user->getIdUser();
        $tesseractBin = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
        $tessdataDir  = $projectDir . '\\tessdata';
        $idPathWin    = str_replace('/', '\\', $idPath);

        $cmd = sprintf(
            '"%s" "%s" "%s" --tessdata-dir "%s" -l fra+ara 2>&1',
            $tesseractBin, $idPathWin, $outputBase, $tessdataDir
        );

        exec($cmd, $out, $code);

        $txtFile = $outputBase . '.txt';

        if (!file_exists($txtFile)) {
            return new JsonResponse([
                'success' => false,
                'error'   => 'OCR failed.',
                'debug'   => [
                    'exit_code'   => $code,
                    'cmd'         => $cmd,
                    'output'      => $out,
                    'output_base' => $outputBase,
                    'id_path'     => $idPathWin,
                    'id_exists'   => file_exists($idPathWin),
                    'out_dir_ok'  => is_writable($outputDir),
                ],
            ], 500);
        }

        $rawText = file_get_contents($txtFile);
        unlink($txtFile);

        $parsed = $this->parseIdCard($rawText);
        return new JsonResponse(['success' => true, 'raw' => $rawText, 'parsed' => $parsed]);
    }

    // =========================================================================
    //  EXTRACT ID RAW (dev branch)
    // =========================================================================
    #[Route('/users/extract-id-raw/{id}', name: 'app_admin_extract_id_raw', methods: ['GET'])]
    public function extractIdRaw(Utilisateur $user): JsonResponse
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $idPath     = $projectDir . '/public/uploads/users/ids/' . $user->getPieceIdentite();
        $idPathWin  = str_replace('/', '\\', $idPath);

        $outputDir = $projectDir . '\\var\\ocr_tmp';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $outputBase   = $outputDir . '\\ocr_raw_' . $user->getIdUser();
        $tesseractBin = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
        $tessdataDir  = $projectDir . '\\tessdata';

        $cmd = sprintf('"%s" "%s" "%s" --tessdata-dir "%s" -l fra+ara 2>&1',
            $tesseractBin, $idPathWin, $outputBase, $tessdataDir);

        exec($cmd, $out, $code);

        $txtFile = $outputBase . '.txt';
        $raw = file_exists($txtFile) ? file_get_contents($txtFile) : '(no output)';
        if (file_exists($txtFile)) unlink($txtFile);

        return new JsonResponse([
            'raw'       => $raw,
            'lines'     => explode("\n", $raw),
            'exit_code' => $code,
            'cmd_out'   => $out,
            'cmd'       => $cmd,
        ]);
    }

    // =========================================================================
    //  TESSERACT DEBUG (dev branch)
    // =========================================================================
    #[Route('/users/tesseract-debug', name: 'app_admin_tesseract_debug', methods: ['GET'])]
    public function tesseractDebug(): JsonResponse
    {
        $projectDir   = $this->getParameter('kernel.project_dir');
        $tessdataDir  = $projectDir . '/tessdata';
        $tesseractBin = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';

        exec('"' . $tesseractBin . '" --version 2>&1', $verOut);

        return new JsonResponse([
            'bin_exists'     => file_exists($tesseractBin),
            'version'        => $verOut,
            'tessdata_path'  => $tessdataDir,
            'tessdata_files' => array_diff(scandir($tessdataDir), ['.', '..']),
        ]);
    }

    // =========================================================================
    //  PRIVATE HELPERS
    // =========================================================================

    /**
 * @return array{cin: string|null, nom: string|null, prenom: string|null, dob: string|null, expiry: string|null, mrz_line: string|null}
 */
    private function parseIdCard(string $text): array
    {
        $text  = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn($l) => $l !== ''
        ));

        $data = [
            'cin'      => null,
            'nom'      => null,
            'prenom'   => null,
            'dob'      => null,
            'expiry'   => null,
            'mrz_line' => null,
        ];

        $monthMap = [
            'jan'=>'01','feb'=>'02','mar'=>'03','apr'=>'04',
            'may'=>'05','jun'=>'06','jul'=>'07','aug'=>'08',
            'sep'=>'09','oct'=>'10','nov'=>'11','dec'=>'12',
        ];

        $parseDate = function(string $raw) use ($monthMap): ?string {
            $raw = trim($raw);
            if (preg_match('/(\d{1,2})\s+([A-Za-z]{3})\s+(\d{4})/', $raw, $m)) {
                $mon = $monthMap[strtolower($m[2])] ?? null;
                if ($mon) return sprintf('%02d/%s/%s', $m[1], $mon, $m[3]);
            }
            if (preg_match('/(\d{2})[\/\.\-](\d{2})[\/\.\-](\d{4})/', $raw, $m)) {
                return "{$m[1]}/{$m[2]}/{$m[3]}";
            }
            return null;
        };

        $total = count($lines);

        for ($i = 0; $i < $total; $i++) {
            $line      = $lines[$i];
            $lineUpper = strtoupper($line);

            if ($lineUpper === 'NAME' && !$data['prenom']) {
                if (isset($lines[$i + 1])) $data['prenom'] = $lines[$i + 1];
                if (isset($lines[$i + 2])) $data['nom']    = $lines[$i + 2];
            }

            if (preg_match('/^dob$/i', $line) || str_contains($lineUpper, 'DATE OF BIRTH')) {
                for ($j = $i + 1; $j <= $i + 3 && $j < $total; $j++) {
                    $candidate = $lines[$j];
                    $parsed    = $parseDate($candidate);
                    if ($parsed) { $data['dob'] = $parsed; break; }
                    if (preg_match('/\b(\d{4})\b/', $candidate, $m) && (int)$m[1] > 1920) {
                        $data['dob'] = '??/?? /' . $m[1] . ' (OCR unclear)';
                        break;
                    }
                }
            }

            if (preg_match('/expir|valid until/i', $line)) {
                for ($j = $i + 1; $j <= $i + 3 && $j < $total; $j++) {
                    $parsed = $parseDate($lines[$j]);
                    if ($parsed) { $data['expiry'] = $parsed; break; }
                }
                if (!$data['expiry']) {
                    $rest   = trim(preg_replace('/expir\w*\s*(on)?/i', '', $line));
                    $parsed = $parseDate($rest);
                    if ($parsed) $data['expiry'] = $parsed;
                }
            }

            if (!$data['cin']) {
                $digits = preg_replace('/\s/', '', $line);
                if (preg_match('/^\d{16}$/', $digits)) {
                    $data['cin'] = $digits;
                } elseif (preg_match('/(?<!\d)(\d{8})(?!\d)/', $digits, $m)) {
                    $data['cin'] = $m[1];
                }
            }

            if (preg_match('/^(IDTUN|ID TUN|I<TUN)[<A-Z0-9 ]{5,}/', $line)) {
                $data['mrz_line'] = $line;
                if (preg_match('/(\d{6})[MF<](\d{6})/', $line, $mrzM)) {
                    $data['dob']    = $this->mrzDateToHuman($mrzM[1]);
                    $data['expiry'] = $this->mrzDateToHuman($mrzM[2]);
                }
            }
        }

        return $data;
    }

    private function mrzDateToHuman(string $mrzDate): string
    {
        $yy   = substr($mrzDate, 0, 2);
        $mm   = substr($mrzDate, 2, 2);
        $dd   = substr($mrzDate, 4, 2);
        $year = (int)$yy > (int)date('y') ? '19' . $yy : '20' . $yy;
        return "$dd/$mm/$year";
    }
}