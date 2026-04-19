<?php

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class EmailService
{
    private $mailer;
    private $twig;

    public function __construct(
        MailerInterface $mailer,
        Environment $twig,
        private readonly OrderReceiptPdfGenerator $orderReceiptPdfGenerator
    ) {
        $this->mailer = $mailer;
        $this->twig = $twig;
    }

    public function sendOrderConfirmation(Order $order, string $recipientEmail): void
    {
        $email = (new Email())
         ->from('alaahefi123@gmail.com')
            ->to($recipientEmail)
            ->subject('Confirmation de votre commande #' . $order->getId())
            ->html($this->twig->render('emails/order_confirmation.html.twig', [
                'order' => $order,
            ]));

        try {
            $pdf = $this->orderReceiptPdfGenerator->generateForOrder($order);
            $email->attach(
                $pdf,
                sprintf('recu-commande-%d.pdf', $order->getId()),
                'application/pdf'
            );
        } catch (\Throwable) {
            // Email still sends without PDF if Dompdf fails
        }

        $this->mailer->send($email);
    }
}
