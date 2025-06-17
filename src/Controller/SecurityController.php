<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class SecurityController extends AbstractController
{
    #[Route('/api/register', name: 'app_register', methods: ['POST'])]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, JWTTokenManagerInterface $jwtManager): JsonResponse
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
        
        // Generar token JWT
        $token = $jwtManager->create($user);
        
        // Construir respuesta con formato compatible con lo esperado por el frontend
        return $this->json([
            'message' => 'Usuario registrado exitosamente',
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles()
            ]
        ], Response::HTTP_CREATED);
    }
    
    #[Route('/api/profile', name: 'app_profile', methods: ['GET'])]
    #[Route('/api/users/profile', name: 'app_users_profile', methods: ['GET'])]
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
    
    #[Route('/api/profile', name: 'app_profile_update', methods: ['PUT'])]
    #[Route('/api/users/profile', name: 'app_users_profile_update', methods: ['PUT'])]
    public function updateProfile(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        // Obtener el usuario autenticado
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['message' => 'Usuario no encontrado'], Response::HTTP_UNAUTHORIZED);
        }
        
        $data = json_decode($request->getContent(), true);
        $updated = false;
        $debugInfo = [];
        
        // Actualizar nombre y apellido si se proporcionan
        if (isset($data['firstName']) && $data['firstName'] !== $user->getFirstName()) {
            $user->setFirstName($data['firstName']);
            $updated = true;
        }
        
        if (isset($data['lastName']) && $data['lastName'] !== $user->getLastName()) {
            $user->setLastName($data['lastName']);
            $updated = true;
        }
        
        // Actualizar contraseña si se proporciona - con validación de contraseña actual
        if (isset($data['newPassword']) && !empty($data['newPassword']) && isset($data['currentPassword']) && !empty($data['currentPassword'])) {
            $currentPassword = trim($data['currentPassword']);
            $newPassword = trim($data['newPassword']);
            
            // Agregamos info de debug
            $debugInfo['currentPassword_received'] = true;
            $debugInfo['newPassword_received'] = true;
            $debugInfo['newPassword_length'] = strlen($newPassword);
            
            // Validar que la contraseña actual es correcta
            if ($passwordHasher->isPasswordValid($user, $currentPassword)) {
                $debugInfo['current_password_valid'] = true;
                
                if (strlen($newPassword) > 0) {
                    $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
                    $user->setPassword($hashedPassword);
                    $updated = true;
                    $debugInfo['password_updated'] = true;
                }
            } else {
                $debugInfo['current_password_valid'] = false;
                return $this->json([
                    'message' => 'La contraseña actual es incorrecta',
                    'debug' => $debugInfo
                ], Response::HTTP_BAD_REQUEST);
            }
        } else if (isset($data['newPassword']) && !empty($data['newPassword'])) {
            // Se proporcionó nueva contraseña pero no la actual
            $debugInfo['error'] = 'Se requiere la contraseña actual para cambiar la contraseña';
            return $this->json([
                'message' => 'Se requiere la contraseña actual para cambiar la contraseña',
                'debug' => $debugInfo
            ], Response::HTTP_BAD_REQUEST);
        }
        
        // Para compatibilidad, mantener el soporte para el campo 'password' original
        // Solo para flujos legacy, y solo si no se recibió newPassword
        if (!$updated && !isset($data['newPassword']) && isset($data['password']) && !empty($data['password'])) {
            if (isset($data['currentPassword']) && !empty($data['currentPassword'])) {
                // Validar contraseña actual
                if ($passwordHasher->isPasswordValid($user, $data['currentPassword'])) {
                    $password = trim($data['password']);
                    if (strlen($password) > 0) {
                        $hashedPassword = $passwordHasher->hashPassword($user, $password);
                        $user->setPassword($hashedPassword);
                        $updated = true;
                        $debugInfo['legacy_password_updated'] = true;
                    }
                } else {
                    return $this->json([
                        'message' => 'La contraseña actual es incorrecta',
                        'debug' => $debugInfo
                    ], Response::HTTP_BAD_REQUEST);
                }
            } else {
                return $this->json([
                    'message' => 'Se requiere la contraseña actual para cambiar la contraseña',
                    'debug' => $debugInfo
                ], Response::HTTP_BAD_REQUEST);
            }
        }
        
        if ($updated) {
            $entityManager->flush();
            return $this->json([
                'message' => 'Perfil actualizado exitosamente',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'roles' => $user->getRoles()
                ],
                'debug' => $debugInfo
            ], Response::HTTP_OK);
        } else {
            return $this->json([
                'message' => 'No se realizaron cambios en el perfil',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'roles' => $user->getRoles()
                ],
                'debug' => $debugInfo,
                'requestData' => $data
            ], Response::HTTP_OK);
        }
    }
}
