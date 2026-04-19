<?php

namespace App\Service;

use App\Entity\Negociation;

class SmartContractGenerator
{
    /**
     * Correction du nom pour correspondre au Contrôleur : generateSmartContract
     */
    public function generateSmartContract(Negociation $negociation): array
    {
        $borrower = $negociation->getCredit()->getBorrower();
        $investor = $negociation->getUtilisateur();
        $amount = $negociation->getMontant();
        $rate = $negociation->getTauxPropose();
        $date = new \DateTime();

        // 1. Définition du Template Métier
        $template = "
            CONTRAT DE PRÊT FINANCIER SÉCURISÉ
            Réf : #REF_ID
            
            ENTRE :
            L'investisseur : #INVESTOR_NAME (#INVESTOR_EMAIL)
            ET :
            L'emprunteur : #BORROWER_NAME (#BORROWER_EMAIL)
            
            OBJET : Prêt de capital via la plateforme Champions Fintech.
            
            CLAUSES FINANCIÈRES :
            - Montant du capital : #AMOUNT DT
            - Taux d'intérêt annuel : #RATE %
            - Date de signature : #DATE
            
            SÉCURITÉ ET INTÉGRITÉ :
            Ce document est protégé par un algorithme de hachage SHA-256. 
            Toute modification manuelle invalide la signature numérique.
        ";

        // 2. Injection des données
        $content = strtr($template, [
            '#REF_ID' => 'CF-' . $negociation->getId_negociation() . '-' . time(),
            '#INVESTOR_NAME' => $investor->getNom(),
            '#INVESTOR_EMAIL' => $investor->getEmail(),
            '#BORROWER_NAME' => $borrower->getNom(),
            '#BORROWER_EMAIL' => $borrower->getEmail(),
            '#AMOUNT' => number_format($amount, 2, '.', ' '),
            '#RATE' => $rate,
            '#DATE' => $date->format('d/m/Y H:i:s'),
        ]);

        // 3. Algorithme de Scellement
        $checksum = hash_hmac('sha256', $content, 'CHAMPIONS_SECRET_KEY');

        // On retourne les clés 'body' et 'hash' car le contrôleur les utilise
        return [
            'body' => $content,
            'hash' => $checksum,
            'metadata' => [
                'gen_version' => '1.0-STABLE',
                'encryption' => 'HMAC-SHA256'
            ]
        ];
    }
}