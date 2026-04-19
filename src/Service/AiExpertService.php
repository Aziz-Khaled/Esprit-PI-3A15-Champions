<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiExpertService
{
    private $httpClient;
    private $apiKey;

    public function __construct(HttpClientInterface $httpClient, string $apiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
    }

    public function getRiskAnalysis(string $projectTitle, float $amount, float $balance): string
    {
        $formattedAmount = number_format($amount, 2, '.', ' ');
        $formattedBalance = number_format($balance, 2, '.', ' ');

        // Prompt ultra-détaillé pour forcer une analyse longue
        $prompt = "Rapport d'Audit de Risque Crédit Approfondi :
                   -------------------------------------------
                   PROJET : '$projectTitle'
                   MONTANT DEMANDÉ : $formattedAmount DT
                   SOLDE INVESTISSEUR : $formattedBalance DT
                   
                   En tant qu'expert en analyse de risques bancaires, rédige un rapport complet structuré comme suit :
                   
                   1. ANALYSE DE LA COHÉRENCE MÉTIER : Évalue si le titre du projet '$projectTitle' justifie un investissement de $formattedAmount DT dans le contexte économique actuel. Est-ce réaliste ?
                   2. ÉVALUATION DE LA VIABILITÉ : Analyse les chances de réussite de ce type de projet et les sources potentielles de revenus pour le remboursement.
                   3. DIAGNOSTIC DE L'EXPOSITION : Analyse l'impact critique sur le capital de l'investisseur (solde actuel : $formattedBalance DT). 
                   4. VERDICT ET RECOMMANDATIONS : Donne un avis final (Favorable, Réservé ou Défavorable) avec des conseils pour sécuriser l'investissement.
                   
                   Sois technique, précis et utilise un ton formel.";

        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'llama-3.3-70b-versatile', 
                    'messages' => [
                        [
                            'role' => 'system', 
                            'content' => 'Tu es un analyste de risques senior en banque d\'affaires. Tes rapports sont détaillés, structurés et sans complaisance.'
                        ],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0.4,
                    'max_tokens' => 1000, // Augmenté pour permettre un texte long
                ],
            ]);

            $data = $response->toArray();
            return $data['choices'][0]['message']['content'];

        } catch (\Exception $e) {
            throw new \Exception("Le scanner n'a pas pu finaliser l'audit : " . $e->getMessage());
        }
    }
}