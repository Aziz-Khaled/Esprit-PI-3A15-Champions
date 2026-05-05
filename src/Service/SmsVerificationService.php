<?php

namespace App\Service;

use Twilio\Rest\Client;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;

class SmsVerificationService
{
    private Client $twilio;
    private RequestStack $requestStack;
    private string $verifySid;
    private LoggerInterface $logger;

    public function __construct(
        string $twilioSid,
        string $twilioToken,
        string $twilioVerifySid,
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {
        $this->twilio       = new Client($twilioSid, $twilioToken);
        $this->verifySid    = $twilioVerifySid;
        $this->requestStack = $requestStack;
        $this->logger       = $logger;
    }

    /**
     * Send OTP via Twilio Verify — no sender number needed.
     */
    public function sendCode(string $phoneNumber): void
    {
        try {
            $verification = $this->twilio
                ->verify
                ->v2
                ->services($this->verifySid)
                ->verifications
                ->create($phoneNumber, 'sms');

            $this->logger->info('Twilio Verify SMS sent', [
                'to'     => $phoneNumber,
                'status' => $verification->status,
            ]);

        } catch (\Twilio\Exceptions\RestException $e) {
            $this->logger->error('Twilio Verify error', [
                'code'    => $e->getStatusCode(),
                'message' => $e->getMessage(),
                'to'      => $phoneNumber,
            ]);
            throw $e;
        }
    }

    /**
     * Check the code against Twilio Verify — no session storage needed,
     * Twilio handles the code on their end.
     * Returns: 'ok' | 'invalid' | 'expired' | 'too_many'
     */
    public function verifyCode(string $phoneNumber, string $submitted): string
    {
        try {
            $check = $this->twilio
                ->verify
                ->v2
                ->services($this->verifySid)
                ->verificationChecks
                ->create([
                    'to'   => $phoneNumber,
                    'code' => $submitted,
                ]);

            $this->logger->info('Twilio Verify check', [
                'to'     => $phoneNumber,
                'status' => $check->status,
            ]);

            if ($check->status === 'approved') {
                $this->requestStack->getSession()->set('phone_verified', true);
                return 'ok';
            }

            return 'invalid';

        } catch (\Twilio\Exceptions\RestException $e) {
            $this->logger->error('Twilio Verify check error', [
                'code'    => $e->getStatusCode(),
                'message' => $e->getMessage(),
            ]);

            // 60202 = max attempts reached
            if ($e->getStatusCode() === 60202) return 'too_many';
            // 60203 = no pending verification (expired)
            if ($e->getStatusCode() === 60203) return 'expired';

            return 'invalid';
        }
    }

    public function isVerified(): bool
    {
        return (bool) $this->requestStack->getSession()->get('phone_verified', false);
    }
}