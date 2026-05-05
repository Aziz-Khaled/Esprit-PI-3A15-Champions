<?php

namespace App\Tests\Service;

use App\Controller\backOffice\AdminPanelController;
use App\Entity\Utilisateur;
use App\Form\AdminEditUserType;
use App\Repository\UtilisateurRepository;
use App\Service\AdminLogger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * Unit tests for AdminPanelController.
 *
 * Strategy: we instantiate the controller directly and inject all dependencies
 * via a minimal Symfony DI container mock so that AbstractController helpers
 * (render, redirectToRoute, addFlash, getUser, createForm, …) work without a
 * full kernel boot.
 */
class AdminPanelControllerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        // Prevent "Undefined array key MAILER_FROM" warnings in controller code
        // that reads $_ENV['MAILER_FROM'] when building emails.
        $_ENV['MAILER_FROM'] = 'no-reply@test.com';
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a controller with a pre-wired container mock.
     *
     * @param array<string,object> $services  map of service-id → mock
     */
    private function buildController(array $services = []): AdminPanelController
    {
        $controller = new AdminPanelController();
        $container  = new Container();

        // Twig (used by render())
        if (!isset($services['twig'])) {
            $twig = $this->createMock(Environment::class);
            $twig->method('render')->willReturn('<html></html>');
            $services['twig'] = $twig;
        }

        // Router (used by redirectToRoute / generateUrl)
        if (!isset($services['router'])) {
            $router = $this->createMock(UrlGeneratorInterface::class);
            $router->method('generate')->willReturn('/some/url');
            $services['router'] = $router;
        }

        // Flash / Session
        $flashBag = $this->createMock(FlashBagInterface::class);
        $session  = $this->createMock(Session::class);
        $session->method('getFlashBag')->willReturn($flashBag);

        if (!isset($services['request_stack'])) {
            $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
            $requestStack->method('getSession')->willReturn($session);
            $services['request_stack'] = $requestStack;
        }

        // Security token storage (used by getUser())
        if (!isset($services['security.token_storage'])) {
            $user  = $this->createMock(UserInterface::class);
            $user->method('getUserIdentifier')->willReturn('admin@test.com');

            $token = $this->createMock(TokenInterface::class);
            $token->method('getUser')->willReturn($user);

            $tokenStorage = $this->createMock(TokenStorageInterface::class);
            $tokenStorage->method('getToken')->willReturn($token);

            $services['security.token_storage'] = $tokenStorage;
        }

        // Form factory (used by createForm())
        if (!isset($services['form.factory'])) {
            $formView    = $this->createMock(FormView::class);
            $formFactory = $this->createMock(FormFactoryInterface::class);
            $form        = $this->createMock(FormInterface::class);
            $form->method('createView')->willReturn($formView);
            $form->method('isSubmitted')->willReturn(false);
            $form->method('handleRequest')->willReturn($form);
            $formFactory->method('create')->willReturn($form);
            $services['form.factory'] = $formFactory;
        }

        foreach ($services as $id => $service) {
            $container->set($id, $service);
        }

        $controller->setContainer($container);
        return $controller;
    }

    /** Build a minimal Utilisateur entity. */
    private function makeUser(int $id = 1, string $statut = 'pending'): Utilisateur
    {
        $user = $this->createMock(Utilisateur::class);
        $user->method('getIdUser')->willReturn($id);
        $user->method('getStatut')->willReturn($statut);
        $user->method('getEmail')->willReturn("user{$id}@test.com");
        $user->method('getPrenom')->willReturn('Jane');
        $user->method('getNom')->willReturn('Doe');
        return $user;
    }

    /** Build a QueryBuilder stub that returns a Query stub. */
    private function makeQb(): MockObject
    {
        $query = $this->createMock(Query::class);

        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $query->method('getSingleScalarResult')->willReturn(0);

        return $qb;
    }

    // =========================================================================
    // index()
    // =========================================================================

    public function testIndexReturnsResponse(): void
    {
        // ── EntityManagerInterface ──
        $qb   = $this->makeQb();
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn([]);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        // ── AdminLogger ──
        $logger = $this->createMock(AdminLogger::class);
        $logger->method('findRecent')->willReturn([]);

        // ── UtilisateurRepository ──
        $userRepo = $this->createMock(UtilisateurRepository::class);
        $userRepo->method('count')->willReturn(42);

        $controller = $this->buildController();
        $response   = $controller->index($em, $logger, $userRepo);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    // =========================================================================
    // pendingUsers()
    // =========================================================================

    public function testPendingUsersReturnsPaginatedResponse(): void
    {
        $qb         = $this->makeQb();
        $pagination = $this->createMock(PaginationInterface::class);

        $repo = $this->createMock(UtilisateurRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $paginator = $this->createMock(PaginatorInterface::class);
        $paginator->method('paginate')->willReturn($pagination);

        $request    = new Request();
        $controller = $this->buildController();
        $response   = $controller->pendingUsers($repo, $request, $paginator);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    // =========================================================================
    // approveUser()
    // =========================================================================

    public function testApproveUserSetsActiveAndFlushes(): void
    {
        $user = $this->makeUser(1, 'pending');
        $user->expects($this->once())->method('setStatut')->with('ACTIVE');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $logger = $this->createMock(AdminLogger::class);
        $logger->expects($this->once())->method('log');

        $mailer = $this->createMock(MailerInterface::class);
        // send() returns void — no willReturn needed

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<p>approved</p>');

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);

        $request = new Request([], ['_token' => 'valid-token']);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/login');

        $controller = $this->buildController([
            'twig'   => $twig,
            'router' => $router,
        ]);

        $response = $controller->approveUser($user, $em, $request, $csrf, $logger, $mailer, $twig);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testApproveUserThrowsOnInvalidCsrf(): void
    {
        $user  = $this->makeUser();
        $em    = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(AdminLogger::class);
        $mailer = $this->createMock(MailerInterface::class);
        $twig  = $this->createMock(Environment::class);

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(false);

        $request    = new Request([], ['_token' => 'bad']);
        $controller = $this->buildController();

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $controller->approveUser($user, $em, $request, $csrf, $logger, $mailer, $twig);
    }

    public function testApproveUserContinuesWhenMailerThrows(): void
    {
        $user = $this->makeUser(2, 'pending');
        // setStatut() returns Utilisateur (fluent), not null
        $user->method('setStatut')->willReturnSelf();

        $_ENV['MAILER_FROM'] = 'no-reply@test.com';

        $em = $this->createMock(EntityManagerInterface::class);

        $logger = $this->createMock(AdminLogger::class);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willThrowException(new \RuntimeException('SMTP down'));

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('');

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);

        $request    = new Request([], ['_token' => 'tok']);
        $controller = $this->buildController(['twig' => $twig]);

        // Must not throw; must still redirect
        $response = $controller->approveUser($user, $em, $request, $csrf, $logger, $mailer, $twig);
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    // =========================================================================
    // rejectUser()
    // =========================================================================

    public function testRejectUserSetsBannedAndFlushes(): void
    {
        $user = $this->makeUser(3, 'pending');
        $user->expects($this->once())->method('setStatut')->with('BANNED');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $logger = $this->createMock(AdminLogger::class);
        $mailer = $this->createMock(MailerInterface::class);

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('');

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);

        $request    = new Request([], ['_token' => 'tok']);
        $controller = $this->buildController(['twig' => $twig]);

        $response = $controller->rejectUser($user, $em, $request, $csrf, $logger, $mailer, $twig);
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testRejectUserThrowsOnInvalidCsrf(): void
    {
        $user   = $this->makeUser();
        $em     = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(AdminLogger::class);
        $mailer = $this->createMock(MailerInterface::class);
        $twig   = $this->createMock(Environment::class);

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(false);

        $controller = $this->buildController();
        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $controller->rejectUser($user, $em, new Request([], ['_token' => 'x']), $csrf, $logger, $mailer, $twig);
    }

    // =========================================================================
    // usersList()
    // =========================================================================

    public function testUsersListWithoutFilters(): void
    {
        $qb         = $this->makeQb();
        $pagination = $this->createMock(PaginationInterface::class);

        $repo = $this->createMock(UtilisateurRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $paginator = $this->createMock(PaginatorInterface::class);
        $paginator->method('paginate')->willReturn($pagination);

        $controller = $this->buildController();
        $response   = $controller->usersList($repo, new Request(), $paginator);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUsersListWithRoleAndStatutFilters(): void
    {
        $qb         = $this->makeQb();
        $pagination = $this->createMock(PaginationInterface::class);

        $repo = $this->createMock(UtilisateurRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $paginator = $this->createMock(PaginatorInterface::class);
        $paginator->method('paginate')->willReturn($pagination);

        $request    = new Request(['role' => 'ADMIN', 'statut' => 'active']);
        $controller = $this->buildController();
        $response   = $controller->usersList($repo, $request, $paginator);

        $this->assertSame(200, $response->getStatusCode());
    }

    // =========================================================================
    // editUser()
    // =========================================================================

    public function testEditUserRendersFormOnGet(): void
    {
        $user      = $this->makeUser();
        $em        = $this->createMock(EntityManagerInterface::class);
        $logger    = $this->createMock(AdminLogger::class);
        $request   = new Request();        // GET, not submitted

        $formView    = $this->createMock(FormView::class);
        $form        = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->willReturn($form);
        $form->method('isSubmitted')->willReturn(false);
        $form->method('createView')->willReturn($formView);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $controller = $this->buildController(['form.factory' => $formFactory]);
        $response   = $controller->editUser($user, $request, $em, $logger);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testEditUserRedirectsOnValidPost(): void
    {
        $user   = $this->makeUser();
        $em     = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');
        $logger = $this->createMock(AdminLogger::class);
        $logger->expects($this->once())->method('log');

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->willReturn($form);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('createView')->willReturn($this->createMock(FormView::class));

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $controller = $this->buildController(['form.factory' => $formFactory]);
        $response   = $controller->editUser($user, new Request(), $em, $logger);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    // =========================================================================
    // deleteUser()
    // =========================================================================

    public function testDeleteUserRemovesEntityAndRedirects(): void
    {
        $user = $this->makeUser(5, 'active');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($user);
        $em->expects($this->once())->method('flush');

        $logger = $this->createMock(AdminLogger::class);
        $logger->expects($this->once())->method('log');

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);

        $controller = $this->buildController();
        $response   = $controller->deleteUser($user, $em, new Request([], ['_token' => 'tok']), $csrf, $logger);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testDeleteUserThrowsOnInvalidCsrf(): void
    {
        $user   = $this->makeUser();
        $em     = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(AdminLogger::class);

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(false);

        $controller = $this->buildController();
        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $controller->deleteUser($user, $em, new Request([], ['_token' => 'bad']), $csrf, $logger);
    }

    // =========================================================================
    // search()
    // =========================================================================

    public function testSearchWithShortQueryReturnsEmpty(): void
    {
        $repo = $this->createMock(UtilisateurRepository::class);
        $repo->expects($this->never())->method('searchByKeyword');

        $request    = new Request(['q' => 'a']); // only 1 char → no search
        $controller = $this->buildController();
        $response   = $controller->search($request, $repo);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testSearchWithValidQueryCallsRepository(): void
    {
        $activeUser  = $this->makeUser(1, 'active');
        $pendingUser = $this->makeUser(2, 'pending');

        $repo = $this->createMock(UtilisateurRepository::class);
        $repo->expects($this->once())
             ->method('searchByKeyword')
             ->with('jane')
             ->willReturn([$activeUser, $pendingUser]);

        $request    = new Request(['q' => 'jane']);
        $controller = $this->buildController();
        $response   = $controller->search($request, $repo);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testSearchTrimsWhitespaceFromQuery(): void
    {
        $repo = $this->createMock(UtilisateurRepository::class);
        $repo->expects($this->once())
             ->method('searchByKeyword')
             ->with('test')
             ->willReturn([]);

        $request    = new Request(['q' => '  test  ']);
        $controller = $this->buildController();
        $controller->search($request, $repo);
    }

    // =========================================================================
    // logs()
    // =========================================================================

    public function testLogsWithoutDateRange(): void
    {
        $logger = $this->createMock(AdminLogger::class);
        $logger->method('findByDateRange')->willReturn([]);

        $controller = $this->buildController();
        $response   = $controller->logs(new Request(), $logger);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testLogsWithDateRange(): void
    {
        $logger = $this->createMock(AdminLogger::class);
        $logger->expects($this->once())
               ->method('findByDateRange')
               ->with('2024-01-01', '2024-12-31')
               ->willReturn([]);

        $request    = new Request(['from' => '2024-01-01', 'to' => '2024-12-31']);
        $controller = $this->buildController();
        $response   = $controller->logs($request, $logger);

        $this->assertSame(200, $response->getStatusCode());
    }

    // =========================================================================
    // Private helpers — tested via parseIdCard through reflection
    // =========================================================================

    private function callParseIdCard(AdminPanelController $ctrl, string $text): array
    {
        $ref = new \ReflectionMethod(AdminPanelController::class, 'parseIdCard');
        $ref->setAccessible(true);
        return $ref->invoke($ctrl, $text);
    }

    private function callMrzDateToHuman(AdminPanelController $ctrl, string $mrzDate): string
    {
        $ref = new \ReflectionMethod(AdminPanelController::class, 'mrzDateToHuman');
        $ref->setAccessible(true);
        return $ref->invoke($ctrl, $mrzDate);
    }

    public function testParseIdCardExtractsCin16Digits(): void
    {
        $ctrl = $this->buildController();
        $text = "Some text\n1234567890123456\nmore text";
        $data = $this->callParseIdCard($ctrl, $text);
        $this->assertSame('1234567890123456', $data['cin']);
    }

    public function testParseIdCardExtractsCin8Digits(): void
    {
        $ctrl = $this->buildController();
        $text = "line1\n12345678\nline3";
        $data = $this->callParseIdCard($ctrl, $text);
        $this->assertSame('12345678', $data['cin']);
    }

    public function testParseIdCardExtractsNameBlock(): void
    {
        $ctrl = $this->buildController();
        $text = "NAME\nJane\nDoe";
        $data = $this->callParseIdCard($ctrl, $text);
        $this->assertSame('Jane', $data['prenom']);
        $this->assertSame('Doe',  $data['nom']);
    }

    public function testParseIdCardExtractsDobSlashFormat(): void
    {
        $ctrl = $this->buildController();
        $text = "DOB\n01/06/1990";
        $data = $this->callParseIdCard($ctrl, $text);
        $this->assertSame('01/06/1990', $data['dob']);
    }

    public function testParseIdCardExtractsDobMonthNameFormat(): void
    {
        $ctrl = $this->buildController();
        $text = "DATE OF BIRTH\n5 Jan 1985";
        $data = $this->callParseIdCard($ctrl, $text);
        $this->assertSame('05/01/1985', $data['dob']);
    }

    public function testParseIdCardExtractsExpiryFromLabel(): void
    {
        $ctrl = $this->buildController();
        $text = "Expiry\n31/12/2028";
        $data = $this->callParseIdCard($ctrl, $text);
        $this->assertSame('31/12/2028', $data['expiry']);
    }

    public function testParseIdCardExtractsMrzLine(): void
    {
        $ctrl = $this->buildController();
        // Minimal MRZ line with dob 850615 and expiry 280101
        $text = "IDTUN<<SOMETHING<<<<<<<<<<<<<<\nIDTUN850615M2801012<<<<<<<<<<<<\n";
        $data = $this->callParseIdCard($ctrl, $text);
        $this->assertNotNull($data['mrz_line']);
    }

    public function testParseIdCardReturnsNullsOnEmptyText(): void
    {
        $ctrl = $this->buildController();
        $data = $this->callParseIdCard($ctrl, '');
        $this->assertNull($data['cin']);
        $this->assertNull($data['nom']);
        $this->assertNull($data['prenom']);
        $this->assertNull($data['dob']);
        $this->assertNull($data['expiry']);
        $this->assertNull($data['mrz_line']);
    }

    public function testMrzDateToHumanPastCentury(): void
    {
        $ctrl   = $this->buildController();
        // yy=85 → 1985 (85 > current 2-digit year e.g. 26)
        $result = $this->callMrzDateToHuman($ctrl, '850615');
        $this->assertSame('15/06/1985', $result);
    }

    public function testMrzDateToHumanCurrentCentury(): void
    {
        $ctrl   = $this->buildController();
        // yy=01 → 2001 (01 <= 26)
        $result = $this->callMrzDateToHuman($ctrl, '010615');
        $this->assertSame('15/06/2001', $result);
    }

    public function testMrzDateToHumanFutureDate(): void
    {
        $ctrl   = $this->buildController();
        // yy=28 → 2028 (28 > 26 → this depends on run year; accept either century)
        $result = $this->callMrzDateToHuman($ctrl, '280101');
        $this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4}$/', $result);
    }
}