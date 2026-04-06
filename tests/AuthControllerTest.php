<?php

namespace App\Tests\Controller\backOffice;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for App\Controller\backOffice\AdminPanelController
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

    private function getCsrfToken(string $tokenId): string
    {
        return static::getContainer()
            ->get('security.csrf.token_manager')
            ->getToken($tokenId)
            ->getValue();
    }

    // ═══════════════════════════════════════════════════════════
    //  ACCESS CONTROL
    // ═══════════════════════════════════════════════════════════

    /** Unauthenticated user → redirected to /login */
    public function testUnauthenticatedCannotAccessAdminPanel(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/panel');

        $this->assertResponseRedirects('/login');
    }

    /** Non-admin (CLIENT) → 403 Forbidden */
    public function testNonAdminCannotAccessAdminPanel(): void
    {
        $client = static::createClient();
        $user   = $this->createUser(['role' => 'CLIENT', 'statut' => 'ACTIVE']);
        $client->loginUser($user);

        $client->request('GET', '/admin/panel');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /** ADMIN → 200 on /admin/panel */
    public function testAdminCanAccessAdminPanel(): void
    {
        $client = static::createClient();
        $admin  = $this->createUser(['role' => 'ADMIN', 'statut' => 'ACTIVE']);
        $client->loginUser($admin);

        $client->request('GET', '/admin/panel');

        $this->assertResponseIsSuccessful();
    }

    // ═══════════════════════════════════════════════════════════
    //  PENDING USERS
    // ═══════════════════════════════════════════════════════════

    /** /admin/users/pending lists only users with statut=pending */
    public function testPendingUsersListShowsOnlyPendingUsers(): void
    {
        $client  = static::createClient();
        $admin   = $this->createUser(['role' => 'ADMIN', 'statut' => 'ACTIVE']);
        $pending = $this->createUser(['statut' => 'pending', 'email' => 'pend.' . uniqid() . '@test.com']);
        $active  = $this->createUser(['statut' => 'ACTIVE', 'email' => 'actv.' . uniqid() . '@test.com']);

        $client->loginUser($admin);
        $client->request('GET', '/admin/users/pending');

        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString($pending->getEmail(), $content);
        $this->assertStringNotContainsString($active->getEmail(), $content);
    }

    // ═══════════════════════════════════════════════════════════
    //  APPROVE USER
    // ═══════════════════════════════════════════════════════════

    /** POST approve with valid CSRF → sets statut to ACTIVE */
    public function testApproveUserSetsStatusToActive(): void
    {
        $client  = static::createClient();
        $admin   = $this->createUser(['role' => 'ADMIN', 'statut' => 'ACTIVE']);
        $pending = $this->createUser(['statut' => 'pending', 'email' => 'toapprove.' . uniqid() . '@test.com']);

        $client->loginUser($admin);
        $client->request('POST', '/admin/users/approve/' . $pending->getIdUser(), [
            '_token' => $this->getCsrfToken('approve_' . $pending->getIdUser()),
        ]);

        $this->assertResponseRedirects('/admin/users/pending');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->refresh($pending);
        $this->assertSame('ACTIVE', $pending->getStatut());
    }

    /** POST approve with invalid CSRF → 403 */
    public function testApproveUserWithInvalidCsrfThrows403(): void
    {
        $client  = static::createClient();
        $admin   = $this->createUser(['role' => 'ADMIN', 'statut' => 'ACTIVE']);
        $pending = $this->createUser(['statut' => 'pending', 'email' => 'bad.' . uniqid() . '@test.com']);

        $client->loginUser($admin);
        $client->request('POST', '/admin/users/approve/' . $pending->getIdUser(), [
            '_token' => 'invalid-token',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ═══════════════════════════════════════════════════════════
    //  REJECT USER
    // ═══════════════════════════════════════════════════════════

    /** POST reject with valid CSRF → sets statut to BANNED */
    public function testRejectUserSetsStatusToBanned(): void
    {
        $client  = static::createClient();
        $admin   = $this->createUser(['role' => 'ADMIN', 'statut' => 'ACTIVE']);
        $pending = $this->createUser(['statut' => 'pending', 'email' => 'toreject.' . uniqid() . '@test.com']);

        $client->loginUser($admin);
        $client->request('POST', '/admin/users/reject/' . $pending->getIdUser(), [
            '_token' => $this->getCsrfToken('reject_' . $pending->getIdUser()),
        ]);

        $this->assertResponseRedirects('/admin/users/pending');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->refresh($pending);
        $this->assertSame('BANNED', $pending->getStatut());
    }

    /** POST reject with invalid CSRF → 403 */
    public function testRejectUserWithInvalidCsrfThrows403(): void
    {
        $client  = static::createClient();
        $admin   = $this->createUser(['role' => 'ADMIN', 'statut' => 'ACTIVE']);
        $pending = $this->createUser(['statut' => 'pending', 'email' => 'badrej.' . uniqid() . '@test.com']);

        $client->loginUser($admin);
        $client->request('POST', '/admin/users/reject/' . $pending->getIdUser(), [
            '_token' => 'invalid-token',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ═══════════════════════════════════════════════════════════
    //  USERS LIST + FILTERS
    // ═══════════════════════════════════════════════════════════

    /** GET /admin/users/list with no filters → 200 */
    public function testUsersListLoadsWithoutFilter(): void
    {
        $client = static::createClient();
        $admin  = $this->createUser(['role' => 'ADMIN', 'statut' => 'ACTIVE']);
        $client->loginUser($admin);

        $client->request('GET', '/admin/users/list');

        $this->assertResponseIsSuccessful();
    }

    /** GET /admin/users/list?role=CLIENT → shows CLIENT, hides ADMIN */
    public function testUsersListFilterByRole(): void
    {
        $client  = static::createClient();
        $admin   = $this->createUser(['role' => 'ADMIN',  'statut' => 'ACTIVE', 'email' => 'adm.' . uniqid() . '@test.com']);
        $client1 = $this->createUser(['role' => 'CLIENT', 'statut' => 'ACTIVE', 'email' => 'cli.' . uniqid() . '@test.com']);

        $client->loginUser($admin);
        $client->request('GET', '/admin/users/list', ['role' => 'CLIENT']);

        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString($client1->getEmail(), $content);
        $this->assertStringNotContainsString($admin->getEmail(), $content);
    }

    /** GET /admin/users/list?statut=ACTIVE → hides BANNED users */
    public function testUsersListFilterByStatut(): void
    {
        $client = static::createClient();
        $admin  = $this->createUser(['role' => 'ADMIN',  'statut' => 'ACTIVE', 'email' => 'adm2.' . uniqid() . '@test.com']);
        $banned = $this->createUser(['role' => 'CLIENT', 'statut' => 'BANNED', 'email' => 'ban.'  . uniqid() . '@test.com']);

        $client->loginUser($admin);
        $client->request('GET', '/admin/users/list', ['statut' => 'ACTIVE']);

        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString($banned->getEmail(), $client->getResponse()->getContent());
    }

    // ═══════════════════════════════════════════════════════════
    //  EDIT USER
    // ═══════════════════════════════════════════════════════════

    /** GET /admin/users/edit/{id} → renders the edit form */
    public function testEditUserPageLoads(): void
    {
        $client = static::createClient();
        $admin  = $this->createUser(['role' => 'ADMIN', 'statut' => 'ACTIVE']);
        $target = $this->createUser(['email' => 'edit.' . uniqid() . '@test.com']);

        $client->loginUser($admin);
        $client->request('GET', '/admin/users/edit/' . $target->getIdUser());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    /** POST valid edit data → updates user and redirects to list */
    public function testEditUserPostUpdatesUser(): void
    {
        $client = static::createClient();
        $admin  = $this->createUser(['role' => 'ADMIN', 'statut' => 'ACTIVE']);
        $target = $this->createUser(['prenom' => 'OldName', 'email' => 'toedit.' . uniqid() . '@test.com']);

        $client->loginUser($admin);
        $client->request('POST', '/admin/users/edit/' . $target->getIdUser(), [
            'admin_edit_user[prenom]' => 'NewName',
            'admin_edit_user[nom]'    => $target->getNom(),
            'admin_edit_user[email]'  => $target->getEmail(),
            'admin_edit_user[role]'   => $target->getRole(),
            'admin_edit_user[statut]' => $target->getStatut(),
        ]);

        $this->assertResponseRedirects('/admin/users/list');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->refresh($target);
        $this->assertSame('NewName', $target->getPrenom());
    }

    // ═══════════════════════════════════════════════════════════
    //  DELETE USER
    // ═══════════════════════════════════════════════════════════

    /** POST delete with valid CSRF → removes user from DB */
    public function testDeleteUserRemovesFromDatabase(): void
    {
        $client = static::createClient();
        $admin  = $this->createUser(['role' => 'ADMIN', 'statut' => 'ACTIVE']);
        $target = $this->createUser(['email' => 'todelete.' . uniqid() . '@test.com']);
        $id     = $target->getIdUser();

        $client->loginUser($admin);
        $client->request('POST', '/admin/users/delete/' . $id, [
            '_token' => $this->getCsrfToken('delete_' . $id),
        ]);

        $this->assertResponseRedirects('/admin/users/list');

        $repo = static::getContainer()->get(UtilisateurRepository::class);
        $this->assertNull($repo->find($id));
    }

    /** POST delete with invalid CSRF → 403 */
    public function testDeleteUserWithInvalidCsrfThrows403(): void
    {
        $client = static::createClient();
        $admin  = $this->createUser(['role' => 'ADMIN', 'statut' => 'ACTIVE']);
        $target = $this->createUser(['email' => 'nodelete.' . uniqid() . '@test.com']);

        $client->loginUser($admin);
        $client->request('POST', '/admin/users/delete/' . $target->getIdUser(), [
            '_token' => 'bad-token',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ═══════════════════════════════════════════════════════════
    //  SEARCH
    // ═══════════════════════════════════════════════════════════

    /** GET /admin/search?q=<term> → returns matching users */
    public function testSearchReturnsMatchingUsers(): void
    {
        $client = static::createClient();
        $admin  = $this->createUser(['role' => 'ADMIN', 'statut' => 'ACTIVE']);
        $found  = $this->createUser(['prenom' => 'Searchable', 'email' => 'search.' . uniqid() . '@test.com']);

        $client->loginUser($admin);
        $client->request('GET', '/admin/search', ['q' => 'Searchable']);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($found->getEmail(), $client->getResponse()->getContent());
    }

    /** GET /admin/search?q=x (single char) → no DB query, total = 0 */
    public function testSearchWithShortQueryReturnsEmpty(): void
    {
        $client = static::createClient();
        $admin  = $this->createUser(['role' => 'ADMIN', 'statut' => 'ACTIVE']);
        $client->loginUser($admin);

        $client->request('GET', '/admin/search', ['q' => 'x']);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('0', $client->getResponse()->getContent());
    }
}