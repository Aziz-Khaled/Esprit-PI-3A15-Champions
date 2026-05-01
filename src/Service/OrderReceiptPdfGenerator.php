<?php

namespace App\Service;

use App\Entity\Order;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class OrderReceiptPdfGenerator
{
    public function __construct(
        private readonly Environment $twig
    ) {
    }

    public function generateForOrder(Order $order): string
    {
        $html = $this->twig->render('pdf/order_receipt.html.twig', [
            'order' => $order,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $output = $dompdf->output();
        if (empty($output)) {
        throw new \RuntimeException('Dompdf output is empty.');
        }

        return $output;
    }
}
