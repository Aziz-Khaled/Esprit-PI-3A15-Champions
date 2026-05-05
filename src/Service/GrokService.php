<?php

namespace App\Service;

use App\Entity\Product;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;

class GrokService
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL = 'llama-3.3-70b-versatile';

    private HttpClientInterface $httpClient;
    private string $grokApiKey;
    private CacheInterface $cache;

    public function __construct(
        HttpClientInterface $httpClient,
        #[Autowire('%env(GROK_API_KEY)%')] string $grokApiKey,
        CacheInterface $cache
    ) {
        $this->httpClient = $httpClient;
        $this->grokApiKey = $grokApiKey;
        $this->cache = $cache;
    }

    /**
     * Generates a professional product description using Grok AI.
     */
    public function generateDescription(string $name, string $category): string
    {
        $systemPrompt = "Tu es un expert en marketing e-commerce. Rédige une description professionnelle, claire et attractive pour un produit. Concentre-toi sur la proposition de valeur et les avantages.";
        $userPrompt = "Produit: '$name'\nCatégorie: '$category'\nRédige une description de moins de 150 mots.";

        return $this->callGrok($systemPrompt, $userPrompt);
    }

    /**
     * Corrects and improves an existing product description using Grok AI.
     */
    public function refineDescription(string $description): string
    {
        $systemPrompt = "Tu es un expert en rédaction web et e-commerce. Corrige les fautes d'orthographe, améliore la grammaire et rends ce texte plus professionnel et vendeur.";
        $userPrompt = "Texte à améliorer:\n\n$description\n\nRetourne UNIQUEMENT le texte amélioré, sans introduction ni conclusion.";

        return $this->callGrok($systemPrompt, $userPrompt);
    }

    /**
     * Recommends similar products based on a given product using Grok AI.
     *
     * @param Product   $currentProduct
     * @param Product[] $availableProducts
     * @param int       $limit
     *
     * @return int[] Array of recommended product IDs and reasons
     */
    public function recommendSimilarProducts(Product $currentProduct, array $availableProducts, int $limit = 4): array
    {
        if (empty($availableProducts) || empty($this->grokApiKey)) {
            return [];
        }

        $currentCategory = $currentProduct->getCategory();
       
        // Pre-filter: only keep products from the SAME category
        $othersList = [];
        foreach ($availableProducts as $p) {
            if ($p->getId() === $currentProduct->getId()) {
                continue;
            }
            if ($p->getCategory() !== $currentCategory) {
                continue;
            }
            $othersList[] = sprintf(
                'ID: %d, Nom: %s, Catégorie: %s, Description: %s',
                $p->getId(),
                $p->getName(),
                $p->getCategory(),
                substr($p->getDescription() ?? '', 0, 100)
            );
        }

        if (empty($othersList)) {
            return [];
        }

        $cacheKey = 'grok_rec_v5_' . md5($currentProduct->getId() . '_' . count($othersList));

        return $this->cache->get($cacheKey, function () use ($currentProduct, $currentCategory, $othersList, $limit) {
            $systemPrompt = "Tu es un moteur de recommandation e-commerce.
INSTRUCTION CRITIQUE: Tu ne dois proposer QUE des produits qui sont RÉELLEMENT du même type que le produit consulté.
- Si l'utilisateur regarde une VOITURE, tu ne dois JAMAIS recommander un abonnement (ex: Netflix), même s'il est dans la liste.
- Si tu ne trouves aucun produit vraiment pertinent dans la liste fournie, renvoie un tableau vide [].
- Ne mentionne JAMAIS de produits qui ne sont pas dans la liste (ex: ne parle pas de Mercedes si elle n'est pas listée).
Règle stricte: Renvoie UNIQUEMENT un tableau JSON valide.
Format: [{\"id\": 12, \"reason\": \"Même type de produit...\"}]";

            $userPrompt = "Produit actuel: ID {$currentProduct->getId()}, Nom: {$currentProduct->getName()}, Catégorie: {$currentCategory}, Description: " . substr($currentProduct->getDescription() ?? '', 0, 150) . "\n\n";
            $userPrompt .= "Liste des produits disponibles (choisis les $limit plus pertinents):\n" . implode("\n", $othersList);

            try {
                $response = $this->httpClient->request('POST', self::API_URL, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->grokApiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => self::MODEL,
                        'messages' => [
                            ['role' => 'user', 'content' => $systemPrompt . "\n\n" . $userPrompt],
                        ],
                        'temperature' => 0.2,
                        'max_tokens' => 1024,
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                if ($statusCode !== 200) {
                    return [];
                }

                $data = $response->toArray(false);
                $text = $data['choices'][0]['message']['content'] ?? '[]';
               
                // Nettoyer les éventuels backticks Markdown (```json ... ```) si l'IA en ajoute quand même
                $text = str_replace(['```json', '```'], '', $text);
                $text = trim($text);

                $recommendations = json_decode($text, true);

                return is_array($recommendations) ? $recommendations : [];
            } catch (\Exception $e) {
                return [];
            }
        });
    }

    private function callGrok(string $systemPrompt, string $userPrompt): string
    {
        if (empty($this->grokApiKey)) {
            return "Erreur: La clé API Grok (GROK_API_KEY) n'est pas configurée dans le fichier .env. Veuillez en obtenir une sur console.x.ai.";
        }

        $cacheKey = 'grok_call_v2_' . md5($systemPrompt . $userPrompt);

        return $this->cache->get($cacheKey, function () use ($systemPrompt, $userPrompt) {
            try {
                $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . trim($this->grokApiKey),
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'llama-3.3-70b-versatile',
                        'messages' => [
                            ['role' => 'user', 'content' => $systemPrompt . "\n\n" . $userPrompt],
                        ],
                        'temperature' => 0.7,
                        'max_tokens' => 1024,
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $content = $response->getContent(false);

                if ($statusCode !== 200) {
                    return "Erreur Groq (HTTP $statusCode): " . ($content ?: "Réponse vide");
                }

                $data = json_decode($content, true);
                $text = $data['choices'][0]['message']['content'] ?? null;

                if ($text) {
                    return trim($text);
                }

                return "Désolé, l'IA n'a pas pu générer de texte (Réponse JSON: " . substr($content, 0, 100) . ")";
            } catch (\Exception $e) {
                return 'Erreur technique: ' . $e->getMessage();
            }
        });
    }
}