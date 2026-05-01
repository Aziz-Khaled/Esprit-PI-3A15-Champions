<?php

namespace App\Tests\Controller\frontOffice;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for App\Controller\frontOffice\AuthController
 */
class AdminPanelControllerTest extends WebTestCase
{
    // ═══════════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════════

    private function createUser(array $overrides = []): Utilisateur
    {
        $em     = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new Utilisateur();
        $user->setPrenom($overrides['prenom']       ?? 'John');
        $user->setNom($overrides['nom']             ?? 'Doe');
        $user->setEmail($overrides['email']         ?? 'john.' . uniqid() . '@test.com');
        $user->setTelephone($overrides['telephone'] ?? '0600000000');
        $user->setRole($overrides['role']           ?? 'CLIENT');
        $user->setStatut($overrides['statut']       ?? 'ACTIVE');
        $user->setDateCreation(new \DateTime());
        $user->setDateDerniereConnexion(new \DateTime());
        $user->setUserImage($overrides['userImage']         ?? 'default_avatar.png');
        $user->setPieceIdentite($overrides['pieceIdentite'] ?? 'default_id.png');

        $plain = $overrides['motDePasse'] ?? 'Password1';
        $user->setMotDePasse($hasher->hashPassword($user, $plain));

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function loginAs(object $client, string $email, string $password): void
    {
        $client->request('POST', '/login', [
            'login_form[email]'      => $email,
            'login_form[motDePasse]' => $password,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  GET ROUTES
    // ═══════════════════════════════════════════════════════════

    /** GET /auth redirects to /login */
    public function testAuthRootRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/auth');

        $this->assertResponseRedirects('/login');
    }

    /** GET /login returns 200 and renders the login form */
    public function testLoginPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form#login-form');
    }

    /** GET /login?step=2 sets initial_step to 2 (KYC screen) */
    public function testLoginPageWithStep2Parameter(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login', ['step' => '2']);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString(
            "initialStep === '2'",
            $client->getResponse()->getContent()
        );
    }

    // ═══════════════════════════════════════════════════════════
    //  LOGIN LOGIC
    // ═══════════════════════════════════════════════════════════

    /** Valid credentials for an ACTIVE user → redirect to app_projet */
    public function testSuccessfulLoginRedirectsToProjet(): void
    {
        $client = static::createClient();
        $user   = $this->createUser(['statut' => 'ACTIVE', 'motDePasse' => 'Password1']);

        $this->loginAs($client, $user->getEmail(), 'Password1');

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertRouteSame('app_projet');
    }

    /** ADMIN credentials → redirect to app_admin_panel */
    public function testAdminLoginRedirectsToAdminPanel(): void
    {
        $client = static::createClient();
        $admin  = $this->createUser([
            'role'       => 'ADMIN',
            'statut'     => 'ACTIVE',
            'motDePasse' => 'Password1',
        ]);

        $this->loginAs($client, $admin->getEmail(), 'Password1');

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertRouteSame('app_admin_panel');
    }

    /** Wrong password → stays on login page with error div rendered */
    public function testWrongPasswordShowsError(): void
    {
        $client = static::createClient();
        $user   = $this->createUser(['statut' => 'ACTIVE', 'motDePasse' => 'Password1']);

        $this->loginAs($client, $user->getEmail(), 'WrongPassword');

        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[style*="FF4D6A"]');
    }

    /** Pending user → login page renders with initial_step=pending */
    public function testPendingUserSeesPendingScreen(): void
    {
        $client = static::createClient();
        $user   = $this->createUser(['statut' => 'pending', 'motDePasse' => 'Password1']);

        $this->loginAs($client, $user->getEmail(), 'Password1');
        $client->followRedirect();

        $this->assertStringContainsString(
            "initialStep === 'pending'",
            $client->getResponse()->getContent()
        );
    }

    /** Banned user → login page shows suspended message */
    public function testBannedUserSeesBannedMessage(): void
    {
        $client = static::createClient();
        $user   = $this->createUser(['statut' => 'BANNED', 'motDePasse' => 'Password1']);

        $this->loginAs($client, $user->getEmail(), 'Password1');
        $client->followRedirect();

        $this->assertSelectorTextContains('body', 'suspended');
    }

    // ═══════════════════════════════════════════════════════════
    //  REGISTRATION — STEP 1
    // ═══════════════════════════════════════════════════════════

    /** POST valid step-1 data → session stored, redirects to step 2 */
    public function testRegisterStep1ValidDataRedirectsToStep2(): void
    {
        $client = static::createClient();
        $client->request('POST', '/register/step1', [
            'general_info[prenom]'     => 'Alice',
            'general_info[nom]'        => 'Martin',
            'general_info[telephone]'  => '0611223344',
            'general_info[email]'      => 'alice.' . uniqid() . '@test.com',
            'general_info[motDePasse]' => 'Password1',
        ]);

        $this->assertResponseRedirects('/login?step=2');
    }

    /** POST empty step-1 form → stays on signup1 screen with errors */
    public function testRegisterStep1EmptyFormShowsErrors(): void
    {
        $client = static::createClient();
        $client->request('POST', '/register/step1', []);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('signup1', $client->getResponse()->getContent());
    }

    /** POST step-1 with invalid email → validation error shown */
    public function testRegisterStep1InvalidEmailShowsError(): void
    {
        $client = static::createClient();
        $client->request('POST', '/register/step1', [
            'general_info[prenom]'     => 'Alice',
            'general_info[nom]'        => 'Martin',
            'general_info[telephone]'  => '0611223344',
            'general_info[email]'      => 'not-an-email',
            'general_info[motDePasse]' => 'Password1',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('valid email', $client->getResponse()->getContent());
    }

    // ═══════════════════════════════════════════════════════════
    //  REGISTRATION — STEP 2 (KYC)
    // ═══════════════════════════════════════════════════════════

    /** GET /register/step2 without session data → redirects to login */
    public function testRegisterStep2WithoutSessionRedirects(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register/step2');

        $this->assertResponseRedirects('/login');
    }

    /** Full flow: step1 → step2 → user persisted with statut=pending */
    public function testFullRegistrationFlowCreatesUserAsPending(): void
    {
        $client = static::createClient();
        $email  = 'newuser.' . uniqid() . '@test.com';

        $client->request('POST', '/register/step1', [
            'general_info[prenom]'     => 'Bob',
            'general_info[nom]'        => 'Builder',
            'general_info[telephone]'  => '0622334455',
            'general_info[email]'      => $email,
            'general_info[motDePasse]' => 'Password1',
        ]);
        $this->assertResponseRedirects('/login?step=2');

        $client->request('POST', '/register/step2', [
            'kyc_info[role]' => 'CLIENT',
        ]);

        $this->assertResponseRedirects('/login?step=pending');

        $repo = static::getContainer()->get(UtilisateurRepository::class);
        $user = $repo->findOneBy(['email' => $email]);

        $this->assertNotNull($user);
        $this->assertSame('pending', $user->getStatut());
        $this->assertSame('CLIENT', $user->getRole());
    }

    // ═══════════════════════════════════════════════════════════
    //  LOGOUT
    // ═══════════════════════════════════════════════════════════

    /** GET /logout redirects (firewall handles it) */
    public function testLogoutRedirects(): void
    {
        $client = static::createClient();
        $user   = $this->createUser(['statut' => 'ACTIVE']);
        $client->loginUser($user);

        $client->request('GET', '/logout');

        $this->assertResponseRedirects();
    }
}