<?php

namespace App\Controller\frontOffice;

use App\Entity\Utilisateur;
use App\Form\KYCInfoType;
use App\Form\LoginType;
use App\Form\GeneralInfoType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class GoogleKycController extends AbstractController
{
   #[Route('/register/google/kyc', name: 'app_google_kyc', methods: ['GET', 'POST'])]
public function kyc(
    Request $request,
    EntityManagerInterface $em,
    SluggerInterface $slugger
): Response {
    // ── Guard: session flag must exist ──
    if (!$request->getSession()->get('google_kyc_pending')) {
        return $this->redirectToRoute('app_login');
    }

    $googleData = $request->getSession()->get('google_register_data');

    if (!$googleData) {
        return $this->redirectToRoute('app_login');
    }

    $user = $em->getRepository(Utilisateur::class)
        ->findOneBy(['email' => $googleData['email']]);

    if (!$user) {
        return $this->redirectToRoute('app_login');
    }

    $form = $this->createForm(KYCInfoType::class, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {

        // ── SELFIE UPLOAD ──
        $selfieFile     = $form->get('selfie')->getData();
        $selfieFilename = $googleData['userImage'];

        if ($selfieFile) {
            $safeName       = $slugger->slug(strtolower($googleData['email']));
            $selfieFilename = 'userimage_' . $safeName . '_' . uniqid() . '.' . $selfieFile->guessExtension();
            $selfieFile->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/users/selfies',
                $selfieFilename
            );
        }

        // ── ID DOCUMENT UPLOAD ──
        $idFile        = $form->get('pieceIdentiteFile')->getData();
        $idDocFilename = $user->getPieceIdentite(); // keep placeholder if no upload

        if ($idFile) {
            $safeName      = $slugger->slug(strtolower($googleData['email']));
            $idDocFilename = 'identity_' . $safeName . '_' . uniqid() . '.' . $idFile->guessExtension();
            $idFile->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/users/ids',
                $idDocFilename
            );
        }

        // ── UPDATE user with KYC data ──
        $user->setUserImage($selfieFilename);
        $user->setPieceIdentite($idDocFilename);
        $user->setStatut('PENDING'); // already PENDING, but explicit
        // role is set by KYCInfoType on $user

        $em->flush();

        // ── Clear ALL Google session flags ──
        $request->getSession()->remove('google_kyc_pending');
        $request->getSession()->remove('google_register_data');

        // ── Invalidate session so they're logged out cleanly ──
        $request->getSession()->invalidate();

        return $this->redirectToRoute('app_login', ['step' => 'pending']);
    }

    return $this->render('auth/index.html.twig', [
        'loginForm'    => $this->createForm(LoginType::class)->createView(),
        'registerForm' => $this->createForm(GeneralInfoType::class, new Utilisateur())->createView(),
        'kycForm'      => $form->createView(),
        'error'        => null,
        'last_email'   => '',
        'initial_step' => '2',
        'otp_phone'    => null,
        'otp_error'    => null,
    ]);
}
}