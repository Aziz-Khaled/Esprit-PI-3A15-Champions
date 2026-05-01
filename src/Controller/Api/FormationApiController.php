<?php

namespace App\Controller\Api;

use App\Entity\Formation;
use App\Repository\FormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[Route('/api/formations')]
class FormationApiController extends AbstractController
{
    #[Route('', name: 'api_formation_index', methods: ['GET'])]
    public function index(FormationRepository $formationRepository, SerializerInterface $serializer): JsonResponse
    {
        $formations = $formationRepository->findAll();
        
        $json = $serializer->serialize($formations, 'json', [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                return method_exists($object, 'getId') ? $object->getId() : (method_exists($object, 'getIdFormation') ? $object->getIdFormation() : null);
            }
        ]);

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route('/{idFormation}', name: 'api_formation_show', methods: ['GET'])]
    public function show(Formation $formation, SerializerInterface $serializer): JsonResponse
    {
        $json = $serializer->serialize($formation, 'json', [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                return method_exists($object, 'getId') ? $object->getId() : (method_exists($object, 'getIdFormation') ? $object->getIdFormation() : null);
            }
        ]);

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route('', name: 'api_formation_create', methods: ['POST'])]
    public function create(Request $request, SerializerInterface $serializer, EntityManagerInterface $entityManager, ValidatorInterface $validator): JsonResponse
    {
        try {
            $formation = $serializer->deserialize($request->getContent(), Formation::class, 'json');
            
            // Check for validation errors
            $errors = $validator->validate($formation);
            if (count($errors) > 0) {
                $errorsString = (string) $errors;
                return new JsonResponse(['error' => $errorsString], Response::HTTP_BAD_REQUEST);
            }

            $entityManager->persist($formation);
            $entityManager->flush();

            $json = $serializer->serialize($formation, 'json', [
                AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                    return method_exists($object, 'getId') ? $object->getId() : (method_exists($object, 'getIdFormation') ? $object->getIdFormation() : null);
                }
            ]);

            return new JsonResponse($json, Response::HTTP_CREATED, [], true);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{idFormation}', name: 'api_formation_update', methods: ['PUT', 'PATCH'])]
    public function update(Request $request, Formation $formation, SerializerInterface $serializer, EntityManagerInterface $entityManager, ValidatorInterface $validator): JsonResponse
    {
        try {
            $serializer->deserialize($request->getContent(), Formation::class, 'json', [
                AbstractNormalizer::OBJECT_TO_POPULATE => $formation
            ]);
            
            // Check for validation errors
            $errors = $validator->validate($formation);
            if (count($errors) > 0) {
                $errorsString = (string) $errors;
                return new JsonResponse(['error' => $errorsString], Response::HTTP_BAD_REQUEST);
            }

            $entityManager->flush();

            $json = $serializer->serialize($formation, 'json', [
                AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                    return method_exists($object, 'getId') ? $object->getId() : (method_exists($object, 'getIdFormation') ? $object->getIdFormation() : null);
                }
            ]);

            return new JsonResponse($json, Response::HTTP_OK, [], true);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{idFormation}', name: 'api_formation_delete', methods: ['DELETE'])]
    public function delete(Formation $formation, EntityManagerInterface $entityManager): JsonResponse
    {
        $entityManager->remove($formation);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
