<?php

namespace App\Controller;

use App\Entity\Space;
use App\Repository\SpaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/spaces')]
class SpaceController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private SerializerInterface $serializer;
    private SpaceRepository $spaceRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        SpaceRepository $spaceRepository
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->spaceRepository = $spaceRepository;
    }

    #[Route('', name: 'space_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        // Solo mostrar espacios activos para usuarios normales
        if ($this->isGranted('ROLE_ADMIN')) {
            $spaces = $this->spaceRepository->findAll();
        } else {
            $spaces = $this->spaceRepository->findActiveSpaces();
        }
        
        return $this->json($spaces, Response::HTTP_OK, [], ['groups' => 'space:read']);
    }

    #[Route('/{id}', name: 'space_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $space = $this->spaceRepository->find($id);
        
        if (!$space) {
            return $this->json(['message' => 'Espacio no encontrado'], Response::HTTP_NOT_FOUND);
        }
        
        // Verificar si el espacio está activo o el usuario es admin
        if (!$space->isIsActive() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['message' => 'Espacio no disponible'], Response::HTTP_FORBIDDEN);
        }
        
        return $this->json($space, Response::HTTP_OK, [], ['groups' => 'space:read']);
    }

    #[Route('', name: 'space_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // Solo los administradores pueden crear espacios
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $data = json_decode($request->getContent(), true);
        
        $space = new Space();
        $space->setName($data['name']);
        $space->setDescription($data['description']);
        $space->setPrice($data['price']);
        $space->setCapacity($data['capacity']);
        $space->setIsActive($data['isActive'] ?? true);
        
        if (isset($data['location'])) {
            $space->setLocation($data['location']);
        }
        
        if (isset($data['amenities'])) {
            $space->setAmenities($data['amenities']);
        }
        
        if (isset($data['imageUrl'])) {
            $space->setImageUrl($data['imageUrl']);
        }
        
        $this->entityManager->persist($space);
        $this->entityManager->flush();
        
        return $this->json($space, Response::HTTP_CREATED, [], ['groups' => 'space:read']);
    }

    #[Route('/{id}', name: 'space_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        // Solo los administradores pueden actualizar espacios
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $space = $this->spaceRepository->find($id);
        
        if (!$space) {
            return $this->json(['message' => 'Espacio no encontrado'], Response::HTTP_NOT_FOUND);
        }
        
        $data = json_decode($request->getContent(), true);
        
        if (isset($data['name'])) {
            $space->setName($data['name']);
        }
        
        if (isset($data['description'])) {
            $space->setDescription($data['description']);
        }
        
        if (isset($data['price'])) {
            $space->setPrice($data['price']);
        }
        
        if (isset($data['capacity'])) {
            $space->setCapacity($data['capacity']);
        }
        
        if (isset($data['location'])) {
            $space->setLocation($data['location']);
        }
        
        if (isset($data['isActive'])) {
            $space->setIsActive($data['isActive']);
        }
        
        if (isset($data['amenities'])) {
            $space->setAmenities($data['amenities']);
        }
        
        if (isset($data['imageUrl'])) {
            $space->setImageUrl($data['imageUrl']);
        }
        
        $this->entityManager->flush();
        
        return $this->json($space, Response::HTTP_OK, [], ['groups' => 'space:read']);
    }

    #[Route('/{id}', name: 'space_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        // Solo los administradores pueden eliminar espacios
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $space = $this->spaceRepository->find($id);
        
        if (!$space) {
            return $this->json(['message' => 'Espacio no encontrado'], Response::HTTP_NOT_FOUND);
        }
        
        // Verificar si tiene reservas activas antes de eliminar
        if (!$space->getReservations()->isEmpty()) {
            // En lugar de eliminar físicamente, deshabilitar el espacio
            $space->setIsActive(false);
            $this->entityManager->flush();
            
            return $this->json(['message' => 'Espacio deshabilitado por tener reservas asociadas'], Response::HTTP_OK);
        }
        
        $this->entityManager->remove($space);
        $this->entityManager->flush();
        
        return $this->json(['message' => 'Espacio eliminado correctamente'], Response::HTTP_OK);
    }

    #[Route('/{id}/availability', name: 'space_check_availability', methods: ['POST'])]
    public function checkAvailability(int $id, Request $request): JsonResponse
    {
        $space = $this->spaceRepository->find($id);
        
        if (!$space) {
            return $this->json(['message' => 'Espacio no encontrado'], Response::HTTP_NOT_FOUND);
        }
        
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['startTime']) || !isset($data['endTime'])) {
            return $this->json(['message' => 'Se requiere hora de inicio y fin'], Response::HTTP_BAD_REQUEST);
        }
        
        $startTime = new \DateTime($data['startTime']);
        $endTime = new \DateTime($data['endTime']);
        
        $isAvailable = $this->spaceRepository->isAvailable($id, $startTime, $endTime);
        
        return $this->json([
            'isAvailable' => $isAvailable,
            'spaceId' => $id,
            'startTime' => $startTime->format('Y-m-d H:i:s'),
            'endTime' => $endTime->format('Y-m-d H:i:s')
        ]);
    }
}
