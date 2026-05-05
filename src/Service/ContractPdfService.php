<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ContractPdfService
{
    private ParameterBagInterface $params;
    private LoggerInterface $logger;

    public function __construct(ParameterBagInterface $params, LoggerInterface $logger)
    {
        $this->params = $params;
        $this->logger = $logger;
    }

    /**
     * Génère un PDF à partir de HTML et le sauvegarde physiquement sur le serveur.
     * * @param string $htmlContent Le contenu Twig rendu en HTML
     * @param string $filename Le nom du fichier (ex: contrat_123.pdf)
     * @return string Le chemin relatif du fichier pour la base de données
     * @throws \Exception Si la génération ou l'écriture échoue
     */
    public function generateAndSaveContract(string $htmlContent, string $filename): string
    {
        try {
            // 1. Configuration des options de Dompdf
            $pdfOptions = new Options();
            $pdfOptions->set('defaultFont', 'Helvetica'); // Plus standard que Arial
            $pdfOptions->set('isHtml5ParserEnabled', true);
            $pdfOptions->set('isRemoteEnabled', true); // Permet de charger des images/logos via URL
            $pdfOptions->set('chroot', $this->params->get('kernel.project_dir') . '/public');

            // 2. Initialisation de Dompdf
            $dompdf = new Dompdf($pdfOptions);
            
            // On ajoute un encodage UTF-8 pour supporter les caractères spéciaux (DT, €, etc.)
            $html = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>' . $htmlContent;
            
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');

            // 3. Rendu du PDF
            $dompdf->render();

            // 4. Gestion du répertoire de stockage
            $storagePath = $this->params->get('kernel.project_dir') . '/public/uploads/contracts/';
            
            if (!file_exists($storagePath)) {
                if (!mkdir($storagePath, 0775, true) && !is_dir($storagePath)) {
                    throw new \RuntimeException(sprintf('Le répertoire "%s" n\'a pas pu être créé', $storagePath));
                }
            }

            // 5. Sauvegarde physique
            $filePath = $storagePath . $filename;
            $output = $dompdf->output();

            if (empty($output)) {
                throw new \RuntimeException("Le contenu du PDF généré est vide.");
            }

            file_put_contents($filePath, $output);

            // Retourne le chemin relatif pour l'enregistrement en BDD
            return 'uploads/contracts/' . $filename;

        } catch (\Exception $e) {
            $this->logger->error('Erreur Génération PDF: ' . $e->getMessage());
            throw new \Exception("Impossible de générer le contrat PDF : " . $e->getMessage());
        }
    }
}