<?php

namespace App\Service;

use App\Entity\Product;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class GeminiService
{
    private $httpClient;
    private $apiKey;

    public function __construct(
        HttpClientInterface $httpClient,
        #[Autowire('%env(GEMINI_API_KEY)%')] string $apiKey
    ) {
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
    }

    /**
     * Recommends similar products based on a given product using Gemini 2.0 Flash.
     * 
     * @param Product $currentProduct
     * @param Product[] $availableProducts
     * @param int $limit
     * @return int[] Array of recommended product IDs
     */
    public function recommendSimilarProducts(Product $currentProduct, array $availableProducts, int $limit = 4): array
    {
        if (empty($availableProducts)) {
            return [];
        }

        // Prepare the text description of the products for the AI
        $currentDesc = sprintf(
            "ID: %d, Name: %s, Category: %s, Brand: %s, Description: %s",
            $currentProduct->getId(),
            $currentProduct->getName(),
            $currentProduct->getCategory(),
            $currentProduct->getBrand(),
            $currentProduct->getDescription()
        );

        $othersList = [];
        foreach ($availableProducts as $p) {
            if ($p->getId() === $currentProduct->getId())
                continue;
            $othersList[] = sprintf(
                "ID: %d, Name: %s, Category: %s, Brand: %s",
                $p->getId(),
                $p->getName(),
                $p->getCategory(),
                $p->getBrand()
            );
        }

        $prompt = "You are a product recommendation engine for a Fintech/Marketplace app. 
        Based on the current product below, select the top $limit most relevant items from the provided list.
        
        CURRENT PRODUCT:
        $currentDesc
        
        AVALAIBLE PRODUCTS LIST:
        " . implode("\n", $othersList) . "
        
        Return ONLY valid JSON in this format: 
        [
            {\"id\": 12, \"reason\": \"Because this item complements the current product's category...\"},
            ...
        ]
        Your response must be valid JSON only.";

        try {
            $response = $this->httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent?key=' . $this->apiKey, [
                'json' => [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ],
                    'generationConfig' => [
                        'response_mime_type' => 'application/json',
                    ]
                ]
            ]);

            if ($response->getStatusCode() === 429) {
                return []; // Or handle quota error specifically
            }

            $data = $response->toArray();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '[]';

            $recommendations = json_decode($text, true);

            return is_array($recommendations) ? $recommendations : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Generates a professional product description using Gemini AI.
     */
    public function generateDescription(string $name, string $category): string
    {
        $prompt = "Write a professional, engaging, and detailed product description for a product named '$name' in the category '$category'. 
        Focus on value proposition, technical details (if applicable), and benefits. Keep it under 150 words. Return ONLY the description text.";

        return $this->callGemini($prompt);
    }

    /**
     * Corrects and improves an existing product description.
     */
    public function refineDescription(string $description): string
    {
        $prompt = "Refine, correct grammar, and improve the professional tone of the following product description:
        
        $description
        
        Return ONLY the improved text.";

        return $this->callGemini($prompt);
    }

    private function callGemini(string $prompt): string
    {
        try {
            $response = $this->httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent?key=' . $this->apiKey, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ]
                ]
            ]);

            if ($response->getStatusCode() === 429) {
                return "Limite de quota atteinte (429). Veuillez patienter une minute avant de réessayer.";
            }

            $data = $response->toArray();

            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $data['candidates'][0]['content']['parts'][0]['text'];
            }

            return "Désolé, l'IA n'a pas pu générer de texte pour le moment. Vérifiez votre clé API.";
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), '429')) {
                return "Limite de quota atteinte (429). Veuillez patienter une minute avant de réessayer.";
            }
            return "Erreur Gemini API: " . $e->getMessage();
        }
    }
}
