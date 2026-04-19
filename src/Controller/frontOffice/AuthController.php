<?php

namespace App\Controller\frontOffice;

use App\Form\GeneralInfoType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Form\LoginType;
use App\Entity\Utilisateur;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Form\KYCInfoType;
use App\Service\SmsVerificationService;
use Psr\Log\LoggerInterface;

final class AuthController extends AbstractController
{
    #[Route('/auth', name: 'app_auth')]
    public function index(): Response
    {
        // Redirect directly to login page
        return $this->redirectToRoute('app_login');
    }

   #[Route('/login', name: 'app_login')]
public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
{
     $error     = $authenticationUtils->getLastAuthenticationError();
    $lastEmail = $authenticationUtils->getLastUsername();

    $loginForm    = $this->createForm(LoginType::class);
    $registerForm = $this->createForm(GeneralInfoType::class);
    $kycForm      = $this->createForm(KYCInfoType::class, new Utilisateur());

    $initialStep = $request->query->get('step', 'login');

    // If login failed because account is pending → show pending screen
    if ($error && $error->getMessage() === 'ACCOUNT_PENDING') {
        $initialStep = 'pending';
        $error = null; // don't show the raw error message
    }

    // If login failed because account is banned → keep on login with message
    if ($error && $error->getMessage() === 'ACCOUNT_BANNED') {
        // error will display normally in the login error block
    }

    return $this->render('auth/index.html.twig', [
        'loginForm'    => $loginForm->createView(),
        'registerForm' => $registerForm->createView(),
        'kycForm'      => $kycForm->createView(),
        'error'        => $error,
        'last_email'   => $lastEmail,
        'initial_step' => $initialStep,
        'otp_phone'    => null,   
        'otp_error'    => null,   
    ]);
}


 #[Route('/register/step1', name: 'app_register_step1', methods: ['GET', 'POST'])]
    public function step1(Request $request): Response
    {
        
        $utilisateur = new Utilisateur();
        $form = $this->createForm(GeneralInfoType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Store in session and move to step 2
            $request->getSession()->set('register_step1', [
                'prenom'     => $utilisateur->getPrenom(),
                'nom'        => $utilisateur->getNom(),
                'telephone'  => $utilisateur->getTelephone(),
                'email'      => $utilisateur->getEmail(),
                'motDePasse' => $utilisateur->getMotDePasse(),
            ]);

            // Redirect back to login page at step 2
            return $this->redirectToRoute('app_send_otp');
        }

        // If form has errors, go back to login page and show them
        return $this->render('auth/index.html.twig', [
            'loginForm'    => $this->createForm(LoginType::class)->createView(),
            'registerForm' => $form->createView(),
            'kycForm'      => $this->createForm(KYCInfoType::class, new Utilisateur())->createView(),
            'error'        => null,
            'last_email'   => '',
            'initial_step' => 'signup1',
            'formErrors'   => $form->getErrors(true),
        ]);
    }



 /*    #[Route('/register/step2', name: 'app_register_step2', methods: ['POST'])]
    public function step2(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        SluggerInterface $slugger
    ): Response {
        $step1 = $request->getSession()->get('register_step1');

        if (!$step1) {
            $this->addFlash('error', 'Session expired. Please start again.');
            return $this->redirectToRoute('app_login');
        }

        // ── SELFIE UPLOAD ──
        $selfieFile     = $request->files->get('selfie');
        $selfieFilename = 'default_avatar.png';

        if ($selfieFile) {
            $safeName       = $slugger->slug(strtolower($step1['email']));
            $selfieFilename = 'userimage_' . $safeName . '_' . uniqid() . '.' . $selfieFile->guessExtension();
            $selfieFile->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/users/selfies',
                $selfieFilename
            );
        }

        // ── ID DOCUMENT UPLOAD ──
        $idDocFile     = $request->files->get('id_document');
        $idDocFilename = 'default_id.png';

        if ($idDocFile) {
            $safeName      = $slugger->slug(strtolower($step1['email']));
            $idDocFilename = 'identity_' . $safeName . '_' . uniqid() . '.' . $idDocFile->guessExtension();
            $idDocFile->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/users/ids',
                $idDocFilename
            );
        }

        // ── BUILD AND SAVE ENTITY ──
        $role = $request->request->get('role', 'CLIENT');

        $user = new Utilisateur();
        $user->setPrenom($step1['prenom']);
        $user->setNom($step1['nom']);
        $user->setEmail($step1['email']);
        $user->setTelephone($step1['telephone']);
        $user->setRole($role);
        $user->setStatut('pending');
        $user->setDateCreation(new \DateTime());
        $user->setDateDerniereConnexion(new \DateTime());
        $user->setUserImage($selfieFilename);
        $user->setPieceIdentite($idDocFilename);

        $hashed = $hasher->hashPassword($user, $step1['motDePasse']);
        $user->setMotDePasse($hashed);

        $em->persist($user);
        $em->flush();

        $request->getSession()->remove('register_step1');

        return $this->redirectToRoute('app_login', ['step' => 'pending']);
    }
*/

#[Route('/register/step2', name: 'app_register_step2', methods: ['GET', 'POST'])]
    public function step2(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        SluggerInterface $slugger,
        SmsVerificationService $sms
    ): Response {
        $step1 = $request->getSession()->get('register_step1');
        if (!$sms->isVerified()) {
        return $this->redirectToRoute('app_login', ['step' => 'signup1']);
        }
        $request->getSession()->remove('phone_verified');
        if (!$step1) {
            $this->addFlash('error', 'Session expired. Please start again.');
            return $this->redirectToRoute('app_login');
        }

        $user = new Utilisateur();
        $form = $this->createForm(KYCInfoType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ── SELFIE UPLOAD ──
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $selfieFile */
            $selfieFile     = $form->get('selfie')->getData();
            $selfieFilename = 'default_avatar.png';

            if ($selfieFile) {
                $safeName       = $slugger->slug(strtolower($step1['email']));
                $selfieFilename = 'userimage_' . $safeName . '_' . uniqid() . '.' . $selfieFile->guessExtension();
                $selfieFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/users/selfies',
                    $selfieFilename
                );
            }

            // ── ID DOCUMENT UPLOAD ──
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $idFile */
            $idFile         = $form->get('pieceIdentiteFile')->getData();
            $idDocFilename  = 'default_id.png';

            if ($idFile) {
                $safeName      = $slugger->slug(strtolower($step1['email']));
                $idDocFilename = 'identity_' . $safeName . '_' . uniqid() . '.' . $idFile->guessExtension();
                $idFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/users/ids',
                    $idDocFilename
                );
            }

            // ── MERGE STEP 1 + STEP 2 DATA AND SAVE ──
            $user->setPrenom($step1['prenom']);
            $user->setNom($step1['nom']);
            $user->setEmail($step1['email']);
            $user->setTelephone($step1['telephone']);
            $user->setMotDePasse($hasher->hashPassword($user, $step1['motDePasse']));
            // role is already set on $user by KYCInfoType (it's mapped)
            $user->setUserImage($selfieFilename);
            $user->setPieceIdentite($idDocFilename);
            $user->setStatut('PENDING');
            $user->setDateCreation(new \DateTime());
            $user->setDateDerniereConnexion(new \DateTime());

            $em->persist($user);
            $em->flush();

            $request->getSession()->remove('register_step1');

            return $this->redirectToRoute('app_login', ['step' => 'pending']);
        }

        // GET request or validation failure — re-render at KYC screen
        return $this->render('auth/index.html.twig', [
            'loginForm'    => $this->createForm(LoginType::class)->createView(),
            'registerForm' => $this->createForm(GeneralInfoType::class, new Utilisateur())->createView(),
            'kycForm'      => $form->createView(),   // ← pass the form with errors
            'error'        => null,
            'last_email'   => '',
            'initial_step' => '2',
            'otp_phone'    => null, // ← add
            'otp_error'    => null, // ← add                   // ← keep user on KYC screen
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }



#[Route('/register/send-otp', name: 'app_send_otp', methods: ['GET', 'POST'])]
public function sendOtp(
    Request $request,
    SmsVerificationService $sms,
    LoggerInterface $logger
): Response {   
    $step1 = $request->getSession()->get('register_step1');

    // Guard: step1 must be completed first
    if (!$step1) {
        return $this->redirectToRoute('app_login', ['step' => 'signup1']);
    }

    $phone = $step1['telephone'];

    try {
        $sms->sendCode($phone);
    }  catch (\Throwable $e) {
    $logger->error('SMS send failed: ' . $e->getMessage()); // ← $logger not $this->logger

    return $this->render('auth/index.html.twig', [
        'loginForm'    => $this->createForm(LoginType::class)->createView(),
        'registerForm' => $this->createForm(GeneralInfoType::class, new Utilisateur())->createView(),
        'kycForm'      => $this->createForm(KYCInfoType::class, new Utilisateur())->createView(),
        'error'        => null,
        'last_email'   => '',
        'initial_step' => 'otp',
        'otp_error'    => 'SMS failed: ' . $e->getMessage(),
        'otp_phone'    => $phone,
    ]);
}

    return $this->render('auth/index.html.twig', [
        'loginForm'    => $this->createForm(LoginType::class)->createView(),
        'registerForm' => $this->createForm(GeneralInfoType::class, new Utilisateur())->createView(),
        'kycForm'      => $this->createForm(KYCInfoType::class, new Utilisateur())->createView(),
        'error'        => null,
        'last_email'   => '',
        'initial_step' => 'otp',
        'otp_phone'    => $phone,
    ]);
}

#[Route('/register/verify-otp', name: 'app_verify_otp', methods: ['POST'])]
public function verifyOtp(
    Request $request,
    SmsVerificationService $sms
): Response {
    $step1 = $request->getSession()->get('register_step1');

    if (!$step1) {
        return $this->redirectToRoute('app_login', ['step' => 'signup1']);
    }

    $submitted = trim($request->request->get('otp_code', ''));
    $phone     = $step1['telephone'];
    $result    = $sms->verifyCode($phone, $submitted); // ← phone added

    if ($result === 'ok') {
        return $this->redirectToRoute('app_login', ['step' => '2']);
    }

    $messages = [
        'expired'  => 'Your code has expired. Please request a new one.',
        'invalid'  => 'Incorrect code. Please try again.',
        'too_many' => 'Too many attempts. Please request a new code.',
    ];

    return $this->render('auth/index.html.twig', [
        'loginForm'    => $this->createForm(LoginType::class)->createView(),
        'registerForm' => $this->createForm(GeneralInfoType::class, new Utilisateur())->createView(),
        'kycForm'      => $this->createForm(KYCInfoType::class, new Utilisateur())->createView(),
        'error'        => null,
        'last_email'   => '',
        'initial_step' => 'otp',
        'otp_error'    => $messages[$result] ?? 'Verification failed.',
        'otp_phone'    => $phone,
    ]);
}
}