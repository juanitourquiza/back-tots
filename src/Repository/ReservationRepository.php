<?php

namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 *
 * @method Reservation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Reservation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Reservation[]    findAll()
 * @method Reservation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function save(Reservation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Reservation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Reservation[] Returns an array of Reservation objects for a specific user
     */
    public function findUserReservations(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Reservation[] Returns an array of upcoming Reservation objects for a specific user
     */
    public function findUpcomingUserReservations(User $user): array
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.startTime > :now')
            ->andWhere('r.status != :canceledStatus')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->setParameter('canceledStatus', 'canceled')
            ->orderBy('r.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Reservation[] Returns an array of Reservation objects for a specific space
     */
    public function findSpaceReservations(int $spaceId): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.space', 's')
            ->andWhere('s.id = :spaceId')
            ->setParameter('spaceId', $spaceId)
            ->orderBy('r.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Reservation[] Returns an array of active Reservation objects for a date range
     */
    public function findReservationsInDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.startTime BETWEEN :startDate AND :endDate OR r.endTime BETWEEN :startDate AND :endDate')
            ->andWhere('r.status != :canceledStatus')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('canceledStatus', 'canceled')
            ->orderBy('r.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
