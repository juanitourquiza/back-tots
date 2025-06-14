<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Space;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Repository\SpaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/reservations')]
class ReservationController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ReservationRepository $reservationRepository;
    private SpaceRepository $spaceRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        ReservationRepository $reservationRepository,
        SpaceRepository $spaceRepository
    ) {
        $this->entityManager = $entityManager;
        $this->reservationRepository = $reservationRepository;
        $this->spaceRepository = $spaceRepository;
    }

    #[Route('', name: 'reservation_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Los administradores pueden ver todas las reservas
        if ($this->isGranted('ROLE_ADMIN')) {
            $reservations = $this->reservationRepository->findBy([], ['startTime' => 'DESC']);
        } else {
            // Los usuarios normales solo ven sus propias reservas
            $reservations = $this->reservationRepository->findUserReservations($user);
        }
        
        return $this->json($reservations, Response::HTTP_OK, [], ['groups' => 'reservation:read']);
    }

    #[Route('/upcoming', name: 'reservation_upcoming', methods: ['GET'])]
    public function upcoming(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $reservations = $this->reservationRepository->findUpcomingUserReservations($user);
        
        return $this->json($reservations, Response::HTTP_OK, [], ['groups' => 'reservation:read']);
    }

    #[Route('/{id}', name: 'reservation_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $reservation = $this->reservationRepository->find($id);
        
        if (!$reservation) {
            return $this->json(['message' => 'Reserva no encontrada'], Response::HTTP_NOT_FOUND);
        }
        
        // Verificar que el usuario sea el propietario de la reserva o un administrador
        if ($reservation->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['message' => 'No tienes permiso para ver esta reserva'], Response::HTTP_FORBIDDEN);
        }
        
        return $this->json($reservation, Response::HTTP_OK, [], ['groups' => 'reservation:read']);
    }

    #[Route('', name: 'reservation_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Validación básica
        if (!isset($data['spaceId']) || !isset($data['startTime']) || !isset($data['endTime']) || !isset($data['attendees'])) {
            return $this->json(['message' => 'Faltan datos requeridos'], Response::HTTP_BAD_REQUEST);
        }
        
        $space = $this->spaceRepository->find($data['spaceId']);
        
        if (!$space) {
            return $this->json(['message' => 'Espacio no encontrado'], Response::HTTP_NOT_FOUND);
        }
        
        // Verificar si el espacio está activo
        if (!$space->isIsActive()) {
            return $this->json(['message' => 'El espacio no está disponible para reservas'], Response::HTTP_BAD_REQUEST);
        }
        
        $startTime = new \DateTime($data['startTime']);
        $endTime = new \DateTime($data['endTime']);
        $attendees = $data['attendees'];
        
        // Verificar que la fecha de inicio sea anterior a la fecha de fin
        if ($startTime >= $endTime) {
            return $this->json(['message' => 'La hora de inicio debe ser anterior a la hora de fin'], Response::HTTP_BAD_REQUEST);
        }
        
        // Verificar que la fecha de inicio sea futura
        if ($startTime <= new \DateTime()) {
            return $this->json(['message' => 'La reserva debe ser para una fecha futura'], Response::HTTP_BAD_REQUEST);
        }
        
        // Verificar que el número de asistentes no exceda la capacidad
        if ($attendees > $space->getCapacity()) {
            return $this->json([
                'message' => 'El número de asistentes excede la capacidad del espacio',
                'capacity' => $space->getCapacity()
            ], Response::HTTP_BAD_REQUEST);
        }
        
        // Verificar disponibilidad
        $isAvailable = $this->spaceRepository->isAvailable($space->getId(), $startTime, $endTime);
        if (!$isAvailable) {
            return $this->json(['message' => 'El espacio no está disponible en el horario seleccionado'], Response::HTTP_CONFLICT);
        }
        
        // Crear la reserva
        $reservation = new Reservation();
        $reservation->setUser($this->getUser());
        $reservation->setSpace($space);
        $reservation->setStartTime($startTime);
        $reservation->setEndTime($endTime);
        $reservation->setAttendees($attendees);
        $reservation->setStatus('pending'); // Estado inicial: pendiente
        
        // Calcular precio total (precio por hora * número de horas)
        $hours = ($endTime->getTimestamp() - $startTime->getTimestamp()) / 3600;
        $totalPrice = $space->getPrice() * $hours;
        $reservation->setTotalPrice($totalPrice);
        
        // Guardar notas si existen
        if (isset($data['notes'])) {
            $reservation->setNotes($data['notes']);
        }
        
        $this->entityManager->persist($reservation);
        $this->entityManager->flush();
        
        return $this->json($reservation, Response::HTTP_CREATED, [], ['groups' => 'reservation:read']);
    }

    #[Route('/{id}', name: 'reservation_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $reservation = $this->reservationRepository->find($id);
        
        if (!$reservation) {
            return $this->json(['message' => 'Reserva no encontrada'], Response::HTTP_NOT_FOUND);
        }
        
        // Solo el dueño de la reserva o un admin puede actualizarla
        if ($reservation->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['message' => 'No tienes permiso para actualizar esta reserva'], Response::HTTP_FORBIDDEN);
        }
        
        $data = json_decode($request->getContent(), true);
        
        // Los administradores pueden cambiar el estado
        if ($this->isGranted('ROLE_ADMIN') && isset($data['status'])) {
            $reservation->setStatus($data['status']);
            $reservation->setUpdatedAt(new \DateTime());
        }
        
        // Solo permitir actualizar notas para el dueño (y en estado pendiente)
        if ($reservation->getUser() === $this->getUser() && $reservation->getStatus() === 'pending') {
            if (isset($data['notes'])) {
                $reservation->setNotes($data['notes']);
                $reservation->setUpdatedAt(new \DateTime());
            }
            
            // Si se quiere cambiar fechas u otros datos importantes, mejor cancelar y crear nueva
        }
        
        $this->entityManager->flush();
        
        return $this->json($reservation, Response::HTTP_OK, [], ['groups' => 'reservation:read']);
    }

    #[Route('/{id}/cancel', name: 'reservation_cancel', methods: ['PUT'])]
    public function cancel(int $id): JsonResponse
    {
        $reservation = $this->reservationRepository->find($id);
        
        if (!$reservation) {
            return $this->json(['message' => 'Reserva no encontrada'], Response::HTTP_NOT_FOUND);
        }
        
        // Solo el dueño de la reserva o un admin puede cancelarla
        if ($reservation->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['message' => 'No tienes permiso para cancelar esta reserva'], Response::HTTP_FORBIDDEN);
        }
        
        // Verificar si la reserva ya pasó
        if ($reservation->getStartTime() <= new \DateTime()) {
            return $this->json(['message' => 'No se pueden cancelar reservas pasadas'], Response::HTTP_BAD_REQUEST);
        }
        
        $reservation->setStatus('canceled');
        $reservation->setUpdatedAt(new \DateTime());
        
        $this->entityManager->flush();
        
        return $this->json([
            'message' => 'Reserva cancelada correctamente',
            'reservation' => $reservation
        ], Response::HTTP_OK, [], ['groups' => 'reservation:read']);
    }
    
    #[Route('/calendar', name: 'reservation_calendar', methods: ['GET'])]
    public function calendar(Request $request): JsonResponse
    {
        // Solo los administradores pueden ver el calendario completo
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['message' => 'No tienes permiso para ver el calendario completo'], Response::HTTP_FORBIDDEN);
        }
        
        $startDate = $request->query->get('startDate');
        $endDate = $request->query->get('endDate');
        
        if (!$startDate || !$endDate) {
            return $this->json(['message' => 'Se requieren las fechas de inicio y fin'], Response::HTTP_BAD_REQUEST);
        }
        
        $startDateTime = new \DateTime($startDate);
        $endDateTime = new \DateTime($endDate);
        
        $reservations = $this->reservationRepository->findReservationsInDateRange($startDateTime, $endDateTime);
        
        return $this->json($reservations, Response::HTTP_OK, [], ['groups' => 'reservation:read']);
    }
}
