<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class SecurityController extends AbstractController
{
    #[Route('/api/register', name: 'app_register', methods: ['POST'])]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Validación básica
        if (!isset($data['email']) || !isset($data['password']) || !isset($data['firstName']) || !isset($data['lastName'])) {
            return $this->json(['message' => 'Faltan datos requeridos'], Response::HTTP_BAD_REQUEST);
        }
        
        // Verificar si el usuario ya existe
        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return $this->json(['message' => 'El usuario ya existe'], Response::HTTP_CONFLICT);
        }
        
        // Crear nuevo usuario
        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        
        // Hashear contraseña
        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);
        
        // Asignar rol (usuario normal por defecto)
        $user->setRoles(['ROLE_USER']);
        
        // Guardar en la base de datos
        $entityManager->persist($user);
        $entityManager->flush();
        
        return $this->json([
            'message' => 'Usuario registrado exitosamente',
            'userId' => $user->getId()
        ], Response::HTTP_CREATED);
    }
    
    #[Route('/api/profile', name: 'app_profile', methods: ['GET'])]
    public function profile(): JsonResponse
    {
        // Obtener el usuario autenticado
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['message' => 'Usuario no encontrado'], Response::HTTP_UNAUTHORIZED);
        }
        
        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles()
        ], Response::HTTP_OK, [], ['groups' => 'user:read']);
    }
}
