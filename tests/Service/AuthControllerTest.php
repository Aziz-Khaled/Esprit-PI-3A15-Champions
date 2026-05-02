<?php

namespace App\Tests\Service;

use App\Controller\frontOffice\AuthController;
use App\Entity\Utilisateur;
use App\Service\SmsVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\String\Slugger\SluggerInterface;
use Twig\Environment;

class AuthControllerTest extends TestCase
{
    // -------------------------------------------------------------------------
    //  Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array{AuthController, MockObject&ContainerInterface}
     */
    private function makeController(): array
    {
        /** @var MockObject&ContainerInterface $container */
        $container = $this->createMock(ContainerInterface::class);

        $controller = new AuthController();
        $controller->setContainer($container);

        return [$controller, $container];
    }

    /**
     * Build a stub form.
     */
  private function makeForm(bool $submitted = false, bool $valid = false): MockObject
{
    $form = $this->createMock(FormInterface::class);
    $form->method('handleRequest')->willReturnSelf();
    $form->method('isSubmitted')->willReturn($submitted);
    $form->method('isValid')->willReturn($valid);
    $form->method('createView')->willReturn($this->createMock(FormView::class));
   
    // FIX: Return a FormErrorIterator instead of ArrayIterator
    $formErrors = $this->createMock(\Symfony\Component\Form\FormErrorIterator::class);
    $form->method('getErrors')->willReturn($formErrors);
   
    return $form;
}            

    /**
     * Build a RequestStack mock so addFlash() works in the controller.
     */
    private function makeRequestStack(): MockObject
    {
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getSession')
                     ->willReturn($this->createMock(SessionInterface::class));
        return $requestStack;
    }

    /**
     * Wire container with twig + form.factory + router + request_stack.
     * Any of the four can be overridden via $overrides.
     */
    private function wireContainer(
        MockObject $container,
        MockObject $twig,
        MockObject $formFactory,
        MockObject $router = null,
        array $overrides = []
    ): void {
        $services = array_merge(
            [
                'twig'          => $twig,
                'form.factory'  => $formFactory,
                'router'        => $router,
                'request_stack' => $this->makeRequestStack(),
            ],
            $overrides
        );

        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnCallback(
            fn(string $id) => $services[$id] ?? null
        );
    }

    // =========================================================================
    //  index()
    // =========================================================================

    public function testIndexRedirectsToLogin(): void
    {
        [$controller, $container] = $this->makeController();

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/login');

        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnCallback(
            fn($id) => $id === 'router' ? $router : null
        );

        $response = $controller->index();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/login', $response->getTargetUrl());
    }

    // =========================================================================
    //  login()
    // =========================================================================

    public function testLoginRendersTemplate(): void
    {
        [$controller, $container] = $this->makeController();

        $authUtils = $this->createMock(AuthenticationUtils::class);
        $authUtils->method('getLastAuthenticationError')->willReturn(null);
        $authUtils->method('getLastUsername')->willReturn('');

        $twig        = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<html>login</html>');
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($this->makeForm());

        $this->wireContainer($container, $twig, $formFactory);

        $response = $controller->login($authUtils, new Request(['step' => 'login']));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testLoginSetsPendingStepOnPendingError(): void
    {
        [$controller, $container] = $this->makeController();

        $error = new \Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException(
            'ACCOUNT_PENDING'
        );

        $authUtils = $this->createMock(AuthenticationUtils::class);
        $authUtils->method('getLastAuthenticationError')->willReturn($error);
        $authUtils->method('getLastUsername')->willReturn('user@example.com');

        $capturedVars = [];
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(
            function ($tpl, $vars) use (&$capturedVars) {
                $capturedVars = $vars;
                return '<html></html>';
            }
        );

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($this->makeForm());

        $this->wireContainer($container, $twig, $formFactory);

        $controller->login($authUtils, new Request());

        $this->assertSame('pending', $capturedVars['initial_step']);
        $this->assertNull($capturedVars['error']);
    }

    // =========================================================================
    //  step1()
    // =========================================================================

    public function testStep1RedirectsToSendOtpOnValidSubmit(): void
    {
        [$controller, $container] = $this->makeController();

        $utilisateur = new Utilisateur();
        $utilisateur->setPrenom('Alice');
        $utilisateur->setNom('Smith');
        $utilisateur->setEmail('alice@example.com');
        $utilisateur->setTelephone('+21600000000');
        $utilisateur->setMotDePasse('hashed');

        $validForm = $this->makeForm(submitted: true, valid: true);
        $validForm->method('getData')->willReturn($utilisateur);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($validForm);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/register/send-otp');

        $twig = $this->createMock(Environment::class);

        $this->wireContainer($container, $twig, $formFactory, $router);

        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())->method('set')->with('register_step1', $this->isArray());

        $request = $this->createMock(Request::class);
        $request->method('getSession')->willReturn($session);

        $response = $controller->step1($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testStep1ReRendersOnInvalidSubmit(): void
    {
        [$controller, $container] = $this->makeController();

        $invalidForm = $this->makeForm(submitted: true, valid: false);

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<html>form errors</html>');

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($invalidForm);

        $this->wireContainer($container, $twig, $formFactory);

        $request = $this->createMock(Request::class);
        $request->method('getSession')->willReturn($this->createMock(SessionInterface::class));

        $response = $controller->step1($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertNotInstanceOf(RedirectResponse::class, $response);
    }

    // =========================================================================
    //  step2()
    // =========================================================================

    public function testStep2RedirectsToSignup1WhenOtpNotVerified(): void
    {
        [$controller, $container] = $this->makeController();

        $sms = $this->createMock(SmsVerificationService::class);
        $sms->method('isVerified')->willReturn(false);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/login?step=signup1');

        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnCallback(
            fn($id) => $id === 'router' ? $router : null
        );

        $em      = $this->createMock(EntityManagerInterface::class);
        $hasher  = $this->createMock(UserPasswordHasherInterface::class);
        $slugger = $this->createMock(SluggerInterface::class);

        $request = $this->createMock(Request::class);
        $request->method('getSession')->willReturn($this->createMock(SessionInterface::class));

        $response = $controller->step2($request, $em, $hasher, $slugger, $sms);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }



    public function testStep2PersistsUserOnValidSubmit(): void
    {
        [$controller, $container] = $this->makeController();

        $sms = $this->createMock(SmsVerificationService::class);
        $sms->method('isVerified')->willReturn(true);

        $step1Data = [
            'prenom'     => 'Alice',
            'nom'        => 'Smith',
            'email'      => 'alice@example.com',
            'telephone'  => '+21600000000',
            'motDePasse' => 'plaintext',
        ];

        $form = $this->makeForm(submitted: true, valid: true);
        // Simulate no file uploads from the form fields
        $subForm = $this->createMock(FormInterface::class);
        $subForm->method('getData')->willReturn(null);
        $form->method('get')->willReturn($subForm);
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/login?step=pending');

        $twig = $this->createMock(Environment::class);

        $this->wireContainer($container, $twig, $formFactory, $router);

        // getParameter() for upload paths
        $container->method('getParameter')
                  ->with('kernel.project_dir')
                  ->willReturn(sys_get_temp_dir());

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('register_step1')->willReturn($step1Data);
        $session->method('remove');

        $request = $this->createMock(Request::class);
        $request->method('getSession')->willReturn($session);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed_pw');

        $slugger = $this->createMock(SluggerInterface::class);
        $slugger->method('slug')->willReturn(
            new \Symfony\Component\String\UnicodeString('alice-smith')
        );

        $response = $controller->step2($request, $em, $hasher, $slugger, $sms);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    // =========================================================================
    //  logout()
    // =========================================================================

    public function testLogoutThrowsLogicException(): void
    {
        [$controller] = $this->makeController();

        $this->expectException(\LogicException::class);

        $controller->logout();
    }

    // =========================================================================
    //  sendOtp()
    // =========================================================================

    public function testSendOtpRedirectsWhenStep1Missing(): void
    {
        [$controller, $container] = $this->makeController();

        $sms    = $this->createMock(SmsVerificationService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/login?step=signup1');

        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnCallback(
            fn($id) => $id === 'router' ? $router : null
        );

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('register_step1')->willReturn(null);

        $request = $this->createMock(Request::class);
        $request->method('getSession')->willReturn($session);

        $response = $controller->sendOtp($request, $sms, $logger);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testSendOtpRendersOtpScreenOnSuccess(): void
    {
        [$controller, $container] = $this->makeController();

        $sms = $this->createMock(SmsVerificationService::class);
        $sms->expects($this->once())->method('sendCode')->with('+21600000000');

        $logger = $this->createMock(LoggerInterface::class);

        $capturedVars = [];
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(
            function ($tpl, $vars) use (&$capturedVars) {
                $capturedVars = $vars;
                return '<html>otp</html>';
            }
        );

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($this->makeForm());

        $this->wireContainer($container, $twig, $formFactory);

        $step1 = ['telephone' => '+21600000000', 'email' => 'alice@example.com'];

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('register_step1')->willReturn($step1);

        $request = $this->createMock(Request::class);
        $request->method('getSession')->willReturn($session);

        $controller->sendOtp($request, $sms, $logger);

        $this->assertSame('otp', $capturedVars['initial_step']);
        $this->assertSame('+21600000000', $capturedVars['otp_phone']);
    }

    public function testSendOtpRendersErrorOnSmsFail(): void
    {
        [$controller, $container] = $this->makeController();

        $sms = $this->createMock(SmsVerificationService::class);
        $sms->method('sendCode')->willThrowException(new \RuntimeException('Twilio error'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $capturedVars = [];
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(
            function ($tpl, $vars) use (&$capturedVars) {
                $capturedVars = $vars;
                return '<html>otp error</html>';
            }
        );

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($this->makeForm());

        $this->wireContainer($container, $twig, $formFactory);

        $step1 = ['telephone' => '+21600000000'];

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('register_step1')->willReturn($step1);

        $request = $this->createMock(Request::class);
        $request->method('getSession')->willReturn($session);

        $controller->sendOtp($request, $sms, $logger);

        $this->assertStringContainsString('SMS failed', $capturedVars['otp_error']);
    }

    // =========================================================================
    //  verifyOtp()
    // =========================================================================

    public function testVerifyOtpRedirectsWhenStep1Missing(): void
    {
        [$controller, $container] = $this->makeController();

        $sms    = $this->createMock(SmsVerificationService::class);
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/login?step=signup1');

        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnCallback(
            fn($id) => $id === 'router' ? $router : null
        );

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('register_step1')->willReturn(null);

        $request = $this->createMock(Request::class);
        $request->request = new ParameterBag(['otp_code' => '123456']);
        $request->method('getSession')->willReturn($session);

        $response = $controller->verifyOtp($request, $sms);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testVerifyOtpRedirectsOnOkResult(): void
    {
        [$controller, $container] = $this->makeController();

        $sms = $this->createMock(SmsVerificationService::class);
        $sms->method('verifyCode')->with('+21600000000', '654321')->willReturn('ok');

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/login?step=2');

        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnCallback(
            fn($id) => $id === 'router' ? $router : null
        );

        $step1 = ['telephone' => '+21600000000'];

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('register_step1')->willReturn($step1);

        $request = $this->createMock(Request::class);
        $request->request = new ParameterBag(['otp_code' => '654321']);
        $request->method('getSession')->willReturn($session);

        $response = $controller->verifyOtp($request, $sms);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

  #[\PHPUnit\Framework\Attributes\DataProvider('invalidOtpResultProvider')]
    public function testVerifyOtpRendersErrorForInvalidResult(
        string $result,
        string $expectedMessage
    ): void {
        [$controller, $container] = $this->makeController();

        $sms = $this->createMock(SmsVerificationService::class);
        $sms->method('verifyCode')->willReturn($result);

        $capturedVars = [];
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(
            function ($tpl, $vars) use (&$capturedVars) {
                $capturedVars = $vars;
                return '<html>otp error</html>';
            }
        );

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($this->makeForm());

        $this->wireContainer($container, $twig, $formFactory);

        $step1 = ['telephone' => '+21600000000'];

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('register_step1')->willReturn($step1);

        $request = $this->createMock(Request::class);
        $request->request = new ParameterBag(['otp_code' => '000000']);
        $request->method('getSession')->willReturn($session);

        $controller->verifyOtp($request, $sms);

        $this->assertSame('otp', $capturedVars['initial_step']);
        $this->assertStringContainsString($expectedMessage, $capturedVars['otp_error']);
    }

    public static function invalidOtpResultProvider(): array
    {
        return [
            'expired'  => ['expired',  'expired'],
            'invalid'  => ['invalid',  'Incorrect'],
            'too_many' => ['too_many', 'Too many'],
            'unknown'  => ['unknown_result', 'Verification failed'],
        ];
    }
}
