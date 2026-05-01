<?php

namespace App\Controller\backOffice;

use App\Entity\Formation;
use App\Form\FormationType;
use App\Repository\FormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class FormationAdminController extends AbstractController
{
    #[Route('/admin/formation', name: 'app_admin_formation_index', methods: ['GET'])]
    public function index(Request $request, FormationRepository $formationRepository): Response
    {
        $search = $request->query->get('q');
        $domaine = $request->query->get('domaine');
        $sort = $request->query->get('sort');

        $formations = $formationRepository->findForAdminList($search, $domaine, $sort);
        $domaines = $formationRepository->findDistinctDomaines();

        return $this->render('admin_panel/formation/index.html.twig', [
            'formations' => $formations,
            'domaines' => $domaines,
            'search' => $search,
            'domaine' => $domaine,
            'sort' => $sort,
        ]);
    }

    #[Route('/admin/formation/new', name: 'app_admin_formation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $formation = new Formation();
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($formation);
            $entityManager->flush();

            $this->addFlash('success', 'La formation a bien été créée.');

            return $this->redirectToRoute('app_admin_formation_index');
        }

        return $this->render('admin_panel/formation/new.html.twig', [
            'formation' => $formation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/formation/{id}/show', name: 'app_admin_formation_show', methods: ['GET'])]
    public function show(Formation $formation): Response
    {
        return $this->render('admin_panel/formation/show.html.twig', [
            'formation' => $formation,
        ]);
    }

    #[Route('/admin/formation/{id}/edit', name: 'app_admin_formation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'La formation a été mise à jour.');

            return $this->redirectToRoute('app_admin_formation_index');
        }

        return $this->render('admin_panel/formation/edit.html.twig', [
            'formation' => $formation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/formation/{id}/delete', name: 'app_admin_formation_delete', methods: ['POST'])]
    public function delete(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-formation' . $formation->getIdFormation(), $request->request->get('_token'))) {
            $entityManager->remove($formation);
            $entityManager->flush();
            $this->addFlash('success', 'La formation a été supprimée.');
        }

        return $this->redirectToRoute('app_admin_formation_index');
    }

    #[Route('/admin/formation/generate-description', name: 'app_admin_formation_generate_description', methods: ['POST'])]
    public function generateDescription(Request $request, \Symfony\Contracts\HttpClient\HttpClientInterface $httpClient): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $titre = $data['titre'] ?? '';

        if (empty($titre)) {
            return $this->json(['error' => 'Le titre est requis pour générer une description.'], 400);
        }

        $rawKey = $_ENV['GEMINI_API_KEY'] ?? $_SERVER['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?? '';
        $apiKey = is_array($rawKey) ? (string) end($rawKey) : (string) $rawKey;
        $apiKey = trim(str_replace(['"', "'"], '', $apiKey));
        
        if (empty($apiKey)) {
            $projectDir = $this->getParameter('kernel.project_dir');
            foreach (['.env.local', '.env'] as $envFile) {
                $envPath = $projectDir . '/' . $envFile;
                if (file_exists($envPath) && preg_match('/^GEMINI_API_KEY=(.+)$/m', file_get_contents($envPath), $matches)) {
                    $apiKey = trim(str_replace(['"', "'"], '', $matches[1]));
                    if (!empty($apiKey)) break;
                }
            }
        }

        if (empty($apiKey)) {
            return $this->json(['error' => 'Clé API Gemini non configurée dans .env.'], 500);
        }

        try {
            $response = $httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent?key=' . $apiKey, [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => "Rédige une description professionnelle courte (environ 3-4 phrases) pour une formation intitulée : '$titre'. La description doit être accrocheuse, sans introduction inutile et donner envie de s'inscrire."]
                            ]
                        ]
                    ]
                ]
            ]);

            if ($response->getStatusCode() === 429) {
                return $this->json(['error' => 'Quota API dépassé. Veuillez attendre une minute avant de réessayer.'], 429);
            }

            $result = $response->toArray();
            $generatedText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

            return $this->json(['description' => trim($generatedText)]);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), '429')) {
                return $this->json(['error' => 'Quota dépassé (429). Attendez une minute.'], 429);
            }
            return $this->json(['error' => 'Erreur lors de la génération avec IA : ' . $e->getMessage()], 500);
        }
    }
}
