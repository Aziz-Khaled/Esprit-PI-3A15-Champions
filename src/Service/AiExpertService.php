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
        $prompt = "En tant qu'expert financier Fintech, analyse ce risque : 
                   Projet: $projectTitle, Montant demandé: $amount DT, 
                   Solde de l'investisseur: $balance DT. 
                   Donne un avis très court et pro.";

        $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'llama3-8b-8192', // Modèle ultra-rapide mentionné dans votre guide
                'messages' => [
                    ['role' => 'system', 'content' => 'Tu es un analyste financier expert.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.5,
            ],
        ]);

        $data = $response->toArray();
        return $data['choices'][0]['message']['content'] ?? "Analyse indisponible.";
    }
}