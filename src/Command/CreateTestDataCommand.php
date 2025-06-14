<?php

namespace App\Command;

use App\Entity\Reservation;
use App\Entity\Space;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-test-data',
    description: 'Crear datos de prueba para la aplicación',
)]
class CreateTestDataCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Creando datos de prueba para la aplicación de reserva de espacios');

        // Limpiar las tablas
        $io->section('Limpiando tablas existentes...');
        $this->cleanTables();

        // Crear usuarios
        $io->section('Creando usuarios...');
        $admin = $this->createAdminUser();
        $users = $this->createUsers();
        $io->success('Usuarios creados correctamente.');

        // Crear espacios
        $io->section('Creando espacios...');
        $spaces = $this->createSpaces();
        $io->success('Espacios creados correctamente.');

        // Crear reservas
        $io->section('Creando reservas...');
        $this->createReservations($users, $spaces);
        $io->success('Reservas creadas correctamente.');

        $io->success('¡Todos los datos de prueba han sido creados correctamente!');

        return Command::SUCCESS;
    }

    private function cleanTables(): void
    {
        // Eliminar primero las reservas para evitar restricciones de integridad referencial
        $this->entityManager->createQuery('DELETE FROM App\Entity\Reservation')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Space')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    private function createAdminUser(): User
    {
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setFirstName('Admin');
        $admin->setLastName('Usuario');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        return $admin;
    }

    private function createUsers(): array
    {
        $users = [];

        $userDetails = [
            ['juan@example.com', 'Juan', 'Pérez', 'password123'],
            ['maria@example.com', 'María', 'González', 'password123'],
            ['carlos@example.com', 'Carlos', 'Rodríguez', 'password123'],
        ];

        foreach ($userDetails as $detail) {
            $user = new User();
            $user->setEmail($detail[0]);
            $user->setFirstName($detail[1]);
            $user->setLastName($detail[2]);
            $user->setRoles(['ROLE_USER']);
            $user->setPassword($this->passwordHasher->hashPassword($user, $detail[3]));

            $this->entityManager->persist($user);
            $users[] = $user;
        }

        $this->entityManager->flush();

        return $users;
    }

    private function createSpaces(): array
    {
        $spaces = [];

        $spaceDetails = [
            [
                'name' => 'Sala de Conferencias A',
                'description' => 'Amplia sala para conferencias con proyector y sistema de audio',
                'price' => 100.00,
                'capacity' => 30,
                'location' => 'Planta 1, Ala Este',
                'amenities' => ['proyector', 'wifi', 'sistema de audio', 'aire acondicionado'],
                'imageUrl' => 'https://images.unsplash.com/photo-1517457373958-b7bdd4587205?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80'
            ],
            [
                'name' => 'Sala de Reuniones B',
                'description' => 'Sala para reuniones pequeñas con pizarra y conexión para videoconferencias',
                'price' => 50.00,
                'capacity' => 10,
                'location' => 'Planta 2, Ala Norte',
                'amenities' => ['pizarra', 'wifi', 'videoconferencia'],
                'imageUrl' => 'https://images.unsplash.com/photo-1431540015161-0bf868a2d407?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80'
            ],
            [
                'name' => 'Auditorio Principal',
                'description' => 'Gran auditorio para eventos de gran escala con tecnología de última generación',
                'price' => 300.00,
                'capacity' => 100,
                'location' => 'Planta 0, Centro',
                'amenities' => ['proyector', 'wifi', 'sistema de audio avanzado', 'iluminación programable', 'escenario'],
                'imageUrl' => 'https://images.unsplash.com/photo-1505373877841-8d25f7d46678?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80'
            ],
            [
                'name' => 'Sala de Coworking',
                'description' => 'Espacio abierto para trabajo colaborativo con estaciones de trabajo individuales',
                'price' => 20.00,
                'capacity' => 25,
                'location' => 'Planta 3, Ala Oeste',
                'amenities' => ['wifi', 'cafetería', 'impresora', 'lockers'],
                'imageUrl' => 'https://images.unsplash.com/photo-1556761175-4b46a572b786?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80'
            ],
            [
                'name' => 'Salón de Eventos',
                'description' => 'Espacio amplio para eventos sociales y corporativos',
                'price' => 250.00,
                'capacity' => 80,
                'location' => 'Planta 0, Ala Sur',
                'amenities' => ['wifi', 'cocina', 'sistema de audio', 'iluminación ambiental'],
                'imageUrl' => 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80'
            ]
        ];

        foreach ($spaceDetails as $detail) {
            $space = new Space();
            $space->setName($detail['name']);
            $space->setDescription($detail['description']);
            $space->setPrice($detail['price']);
            $space->setCapacity($detail['capacity']);
            $space->setLocation($detail['location']);
            $space->setAmenities($detail['amenities']);
            $space->setImageUrl($detail['imageUrl']);
            $space->setIsActive(true);

            $this->entityManager->persist($space);
            $spaces[] = $space;
        }

        $this->entityManager->flush();

        return $spaces;
    }

    private function createReservations(array $users, array $spaces): void
    {
        $statuses = ['pending', 'approved', 'rejected', 'canceled'];

        // Crear reservas para fechas futuras
        for ($i = 0; $i < 15; $i++) {
            $reservation = new Reservation();
            
            // Asignar usuario aleatorio
            $user = $users[array_rand($users)];
            $reservation->setUser($user);
            
            // Asignar espacio aleatorio
            $space = $spaces[array_rand($spaces)];
            $reservation->setSpace($space);
            
            // Generar fechas de inicio y fin (desde hoy hasta +30 días)
            $daysToAdd = rand(1, 30);
            $hoursToAdd = rand(8, 18); // Entre 8 AM y 6 PM
            $durationHours = rand(1, 4); // Entre 1 y 4 horas
            
            $startTime = new \DateTime();
            $startTime->modify("+{$daysToAdd} days");
            $startTime->setTime($hoursToAdd, 0, 0);
            
            $endTime = clone $startTime;
            $endTime->modify("+{$durationHours} hours");
            
            $reservation->setStartTime($startTime);
            $reservation->setEndTime($endTime);
            
            // Configurar detalles de la reserva
            $reservation->setAttendees(rand(1, $space->getCapacity()));
            $reservation->setStatus($statuses[array_rand([0, 1])]);  // Solo pending o approved para futuras
            
            // Calcular precio total
            $hours = ($endTime->getTimestamp() - $startTime->getTimestamp()) / 3600;
            $totalPrice = $space->getPrice() * $hours;
            $reservation->setTotalPrice($totalPrice);
            
            $notes = ['Necesito preparación especial para presentación', 
                      'Solicito agua y café para los asistentes', 
                      'Evento corporativo importante', 
                      'Reunión de equipo mensual',
                      ''];
            $reservation->setNotes($notes[array_rand($notes)]);
            
            $reservation->setCreatedAt(new \DateTime());
            
            $this->entityManager->persist($reservation);
        }
        
        // Crear algunas reservas pasadas
        for ($i = 0; $i < 10; $i++) {
            $reservation = new Reservation();
            
            // Asignar usuario aleatorio
            $user = $users[array_rand($users)];
            $reservation->setUser($user);
            
            // Asignar espacio aleatorio
            $space = $spaces[array_rand($spaces)];
            $reservation->setSpace($space);
            
            // Generar fechas pasadas (desde hace 30 días hasta ayer)
            $daysToSubtract = rand(1, 30);
            $hoursToAdd = rand(8, 18); 
            $durationHours = rand(1, 4); 
            
            $startTime = new \DateTime();
            $startTime->modify("-{$daysToSubtract} days");
            $startTime->setTime($hoursToAdd, 0, 0);
            
            $endTime = clone $startTime;
            $endTime->modify("+{$durationHours} hours");
            
            $reservation->setStartTime($startTime);
            $reservation->setEndTime($endTime);
            
            // Configurar detalles de la reserva
            $reservation->setAttendees(rand(1, $space->getCapacity()));
            $reservation->setStatus($statuses[array_rand([0, 1, 2, 3])]);  // Cualquier estado para pasadas
            
            // Calcular precio total
            $hours = ($endTime->getTimestamp() - $startTime->getTimestamp()) / 3600;
            $totalPrice = $space->getPrice() * $hours;
            $reservation->setTotalPrice($totalPrice);
            
            $notes = ['Necesito preparación especial para presentación', 
                      'Solicito agua y café para los asistentes', 
                      'Evento corporativo importante', 
                      'Reunión de equipo mensual',
                      ''];
            $reservation->setNotes($notes[array_rand($notes)]);
            
            $reservation->setCreatedAt(new \DateTime());
            $reservation->setCreatedAt(clone $startTime);
            $reservation->setCreatedAt((new \DateTime())->modify("-" . ($daysToSubtract + rand(1, 5)) . " days"));
            
            $this->entityManager->persist($reservation);
        }
        
        $this->entityManager->flush();
    }
}
