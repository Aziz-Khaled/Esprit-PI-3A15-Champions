<?php

namespace App\Controller\backOffice;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Form\AdminEditUserType;
use Symfony\Component\HttpFoundation\JsonResponse;


#[Route('/admin')]
final class AdminPanelController extends AbstractController
{
    #[Route('/panel', name: 'app_admin_panel')]
    public function index(): Response
    {
        return $this->render('admin_panel/index.html.twig', [
            'controller_name' => 'AdminPanelController',
        ]);
    }

    #[Route('/users/pending', name: 'app_admin_pending_users')]
    public function pendingUsers(UtilisateurRepository $repo): Response
    {
        $pendingUsers = $repo->findBy(
            ['statut' => 'pending'],
            ['dateCreation' => 'DESC']
        );

        return $this->render('admin_panel/pending_users.html.twig', [
            'users' => $pendingUsers,
        ]);
    }

    #[Route('/users/approve/{id}', name: 'app_admin_approve_user', methods: ['POST'])]
    public function approveUser(
    Utilisateur $user,
    EntityManagerInterface $em,
    Request $request,
    CsrfTokenManagerInterface $csrf
    ): Response {
    if (!$csrf->isTokenValid(new CsrfToken('approve_' . $user->getIdUser(), $request->request->get('_token')))) {
        throw $this->createAccessDeniedException('Invalid CSRF token.');
    }

    $user->setStatut('ACTIVE');
    $em->flush();

    $this->addFlash('success', $user->getPrenom() . ' ' . $user->getNom() . ' has been approved.');
    return $this->redirectToRoute('app_admin_pending_users');
}

    #[Route('/users/reject/{id}', name: 'app_admin_reject_user', methods: ['POST'])]
public function rejectUser(
    Utilisateur $user,
    EntityManagerInterface $em,
    Request $request,
    CsrfTokenManagerInterface $csrf
): Response {
    if (!$csrf->isTokenValid(new CsrfToken('reject_' . $user->getIdUser(), $request->request->get('_token')))) {
        throw $this->createAccessDeniedException('Invalid CSRF token.');
    }

    $user->setStatut('BANNED');
    $em->flush();

    $this->addFlash('warning', $user->getPrenom() . ' ' . $user->getNom() . ' has been rejected.');
    return $this->redirectToRoute('app_admin_pending_users');
}

#[Route('/users/list', name: 'app_admin_users_list')]
public function usersList(UtilisateurRepository $repo, Request $request): Response
{
    $role   = $request->query->get('role');
    $statut = $request->query->get('statut');

    $criteria = [];
    if ($role)   $criteria['role']   = $role;
    if ($statut) $criteria['statut'] = $statut;

    $users = $criteria ? $repo->findBy($criteria) : $repo->findAll();

    return $this->render('admin_panel/users_list.html.twig', [
        'users'          => $users,
        'selectedRole'   => $role,
        'selectedStatut' => $statut,
    ]);
}

#[Route('/users/edit/{id}', name: 'app_admin_edit_user', methods: ['GET', 'POST'])]
public function editUser(
    Utilisateur $user,
    Request $request,
    EntityManagerInterface $em
): Response {
    $form = $this->createForm(AdminEditUserType::class, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->flush();
        $this->addFlash('success', $user->getPrenom() . ' ' . $user->getNom() . ' has been updated.');
        return $this->redirectToRoute('app_admin_users_list');
    }

    return $this->render('admin_panel/edit_user.html.twig', [
        'form' => $form,
        'user' => $user,
    ]);
}

#[Route('/users/delete/{id}', name: 'app_admin_delete_user', methods: ['POST'])]
public function deleteUser(
    Utilisateur $user,
    EntityManagerInterface $em,
    Request $request,
    CsrfTokenManagerInterface $csrf
): Response {
    if (!$csrf->isTokenValid(new CsrfToken('delete_' . $user->getIdUser(), $request->request->get('_token')))) {
        throw $this->createAccessDeniedException('Invalid CSRF token.');
    }

    $em->remove($user);
    $em->flush();

    $this->addFlash('success', 'User has been deleted.');
    return $this->redirectToRoute('app_admin_users_list');
}


    #[Route('/admin/search', name: 'app_admin_search', methods: ['GET'])]
    public function search(Request $request, UtilisateurRepository $repo): Response
    {
        $q     = trim($request->query->get('q', ''));
        $users = strlen($q) >= 2 ? $repo->searchByKeyword($q) : [];

        // Split into active vs pending
        $active  = array_filter($users, fn($u) => $u->getStatut() !== 'pending');
        $pending = array_filter($users, fn($u) => $u->getStatut() === 'pending');

       return $this->render('admin_panel/search.html.twig', [
            'q'       => $q,
            'active'  => array_values($active),
            'pending' => array_values($pending),
            'total'   => count($users),
        ]);
    }

    #[Route('/users/face-check/{id}', name: 'app_admin_face_check', methods: ['GET'])]
public function faceCheck(
    Utilisateur $user,
    Request $request
): JsonResponse {
    $projectDir = $this->getParameter('kernel.project_dir');

    $selfiePath = $projectDir . '/public/uploads/users/selfies/' . $user->getUserImage();
    $idPath     = $projectDir . '/public/uploads/users/ids/'     . $user->getPieceIdentite();

    // Guard: files must exist
    if (!file_exists($selfiePath) || !file_exists($idPath)) {
        return new JsonResponse([
            'success' => false,
            'error'   => 'One or both image files not found on disk.',
        ], 404);
    }

    $apiKey    = $_ENV['FACEPP_API_KEY'];
    $apiSecret = $_ENV['FACEPP_API_SECRET'];

    // Call Face++ /compare endpoint using multipart/form-data
    $ch = curl_init('https://api-us.faceplusplus.com/facepp/v3/compare');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'api_key'        => $apiKey,
            'api_secret'     => $apiSecret,
            'image_file1'    => new \CURLFile($selfiePath),
            'image_file2'    => new \CURLFile($idPath),
        ],
        CURLOPT_TIMEOUT        => 20,
    ]);

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return new JsonResponse(['success' => false, 'error' => 'cURL error: ' . $curlErr], 500);
    }

    $data = json_decode($raw, true);

    // Face++ returns error_message on failure (e.g. no face detected)
    if (isset($data['error_message'])) {
        return new JsonResponse([
            'success' => false,
            'error'   => $data['error_message'],
        ], 200);
    }

    $confidence = round($data['confidence'] ?? 0, 1);
    $threshold  = $data['thresholds']['1e-5'] ?? 73.975; // Face++ recommended threshold

    return new JsonResponse([
        'success'    => true,
        'confidence' => $confidence,   // 0–100 float
        'threshold'  => $threshold,
        'match'      => $confidence >= $threshold,
    ]);
}

#[Route('/users/extract-id/{id}', name: 'app_admin_extract_id', methods: ['GET'])]
#[Route('/users/extract-id/{id}', name: 'app_admin_extract_id', methods: ['GET'])]
public function extractId(Utilisateur $user): JsonResponse
{
    $projectDir = $this->getParameter('kernel.project_dir');
    $idPath     = $projectDir . '/public/uploads/users/ids/' . $user->getPieceIdentite();

    if (!file_exists($idPath)) {
        return new JsonResponse(['success' => false, 'error' => 'ID file not found.'], 404);
    }

    // Use the project's var/ directory — always writable, no OneDrive interference
    $outputDir  = $projectDir . '\\var\\ocr_tmp';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }
    $outputBase   = $outputDir . '\\ocr_' . $user->getIdUser();
    $tesseractBin = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
    $tessdataDir  = $projectDir . '\\tessdata';

    // Normalize the ID path to Windows backslashes
    $idPathWin = str_replace('/', '\\', $idPath);

    $cmd = sprintf(
        '"%s" "%s" "%s" --tessdata-dir "%s" -l fra+ara 2>&1',
        $tesseractBin,
        $idPathWin,
        $outputBase,
        $tessdataDir
    );

    exec($cmd, $out, $code);

    $txtFile = $outputBase . '.txt';

    if (!file_exists($txtFile)) {
        return new JsonResponse([
            'success' => false,
            'error'   => 'OCR failed.',
            'debug'   => [
                'exit_code'    => $code,
                'cmd'          => $cmd,
                'output'       => $out,       
                'output_base'  => $outputBase,
                'id_path'      => $idPathWin,
                'id_exists'    => file_exists($idPathWin),
                'out_dir_ok'   => is_writable($outputDir),
            ]
        ], 500);
    }

    $rawText = file_get_contents($txtFile);
    unlink($txtFile);

    $parsed = $this->parseIdCard($rawText);
    return new JsonResponse(['success' => true, 'raw' => $rawText, 'parsed' => $parsed]);
}

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

    // Helper: try to parse "DD Mon YYYY" or "DD/MM/YYYY" into "DD/MM/YYYY"
    $parseDate = function(string $raw) use ($monthMap): ?string {
        $raw = trim($raw);
        // DD Mon YYYY  (e.g. "31 Mar 2028")
        if (preg_match('/(\d{1,2})\s+([A-Za-z]{3})\s+(\d{4})/', $raw, $m)) {
            $mon = $monthMap[strtolower($m[2])] ?? null;
            if ($mon) return sprintf('%02d/%s/%s', $m[1], $mon, $m[3]);
        }
        // DD/MM/YYYY or DD.MM.YYYY or DD-MM-YYYY
        if (preg_match('/(\d{2})[\/\.\-](\d{2})[\/\.\-](\d{4})/', $raw, $m)) {
            return "{$m[1]}/{$m[2]}/{$m[3]}";
        }
        return null;
    };

    $total = count($lines);

    for ($i = 0; $i < $total; $i++) {
        $line = $lines[$i];
        $lineUpper = strtoupper($line);

        // ── Name block: grab the 2 lines after "Name"
        if ($lineUpper === 'NAME' && !$data['prenom']) {
            if (isset($lines[$i + 1])) $data['prenom'] = $lines[$i + 1];
            if (isset($lines[$i + 2])) $data['nom']    = $lines[$i + 2];
        }

        // ── DoB label → next non-empty line is the date
        if (preg_match('/^dob$/i', $line) || str_contains($lineUpper, 'DATE OF BIRTH')) {
            // Look ahead up to 3 lines for a date
            for ($j = $i + 1; $j <= $i + 3 && $j < $total; $j++) {
                $candidate = $lines[$j];
                // OCR often garbles month names in DoB — try direct parse first
                $parsed = $parseDate($candidate);
                if ($parsed) { $data['dob'] = $parsed; break; }

                // Fallback: if line looks like "14380 2000", extract the year
                // and mark dob as partially extracted
                if (preg_match('/\b(\d{4})\b/', $candidate, $m) && (int)$m[1] > 1920) {
                    $data['dob'] = '??/?? /' . $m[1] . ' (OCR unclear)';
                    break;
                }
            }
        }

        // ── Expiry: "Expires on" / "Expiry" / "Valid until"
        if (preg_match('/expir|valid until/i', $line)) {
            for ($j = $i + 1; $j <= $i + 3 && $j < $total; $j++) {
                $parsed = $parseDate($lines[$j]);
                if ($parsed) { $data['expiry'] = $parsed; break; }
            }
            // Also try parsing the rest of the same line
            if (!$data['expiry']) {
                $rest = trim(preg_replace('/expir\w*\s*(on)?/i', '', $line));
                $parsed = $parseDate($rest);
                if ($parsed) $data['expiry'] = $parsed;
            }
        }

        // ── Card number: 16 digits in groups (like "5843 2166 1964 8792")
        if (!$data['cin']) {
            $digits = preg_replace('/\s/', '', $line);
            if (preg_match('/^\d{16}$/', $digits)) {
                $data['cin'] = $digits;
            }
            // Also try Tunisian CIN: exactly 8 digits
            elseif (preg_match('/(?<!\d)(\d{8})(?!\d)/', $digits, $m)) {
                $data['cin'] = $m[1];
            }
        }

        // ── MRZ (Tunisian CIN cards)
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

#[Route('/users/tesseract-debug', name: 'app_admin_tesseract_debug', methods: ['GET'])]
public function tesseractDebug(): JsonResponse
{
    $projectDir  = $this->getParameter('kernel.project_dir');
    $tessdataDir = $projectDir . '/tessdata';

    $tesseractBin = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';

exec('"' . $tesseractBin . '" --version 2>&1', $verOut);

return new JsonResponse([
    'bin_exists' => file_exists($tesseractBin),  // must be true
    'version'    => $verOut,
    'tessdata_path' => $tessdataDir,
    'tessdata_files' => array_diff(scandir($tessdataDir), ['.', '..']), // list files
]);
}

#[Route('/users/extract-id-raw/{id}', name: 'app_admin_extract_id_raw', methods: ['GET'])]
#[Route('/users/extract-id-raw/{id}', name: 'app_admin_extract_id_raw', methods: ['GET'])]
public function extractIdRaw(Utilisateur $user): JsonResponse
{
    $projectDir   = $this->getParameter('kernel.project_dir');
    $idPath       = $projectDir . '/public/uploads/users/ids/' . $user->getPieceIdentite();
    $idPathWin    = str_replace('/', '\\', $idPath);

    $outputDir  = $projectDir . '\\var\\ocr_tmp';
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

}
