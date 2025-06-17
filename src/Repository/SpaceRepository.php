<?php

namespace App\Repository;

use App\Entity\Space;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Space>
 *
 * @method Space|null find($id, $lockMode = null, $lockVersion = null)
 * @method Space|null findOneBy(array $criteria, array $orderBy = null)
 * @method Space[]    findAll()
 * @method Space[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SpaceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Space::class);
    }

    public function save(Space $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Space $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Space[] Returns an array of active Space objects
     */
    public function findActiveSpaces(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.isActive = :val')
            ->setParameter('val', true)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if a space is available on a specific date and time
     */
    public function isAvailable(int $spaceId, \DateTimeInterface $startTime, \DateTimeInterface $endTime): bool
    {
        $count = $this->createQueryBuilder('s')
            ->select('COUNT(r.id)')
            ->join('s.reservations', 'r')
            ->andWhere('s.id = :spaceId')
            ->andWhere('r.status != :canceledStatus')
            ->andWhere('r.endTime > :startTime')
            ->andWhere('r.startTime < :endTime')
            ->setParameter('spaceId', $spaceId)
            ->setParameter('canceledStatus', 'canceled')
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime)
            ->getQuery()
            ->getSingleScalarResult();
        
        return $count == 0;
    }
    
    /**
     * Check if a space is available on a specific date and time, excluding the given reservation
     * This is useful for updating a reservation without it conflicting with itself
     */
    public function isAvailableExcludingReservation(int $spaceId, \DateTimeInterface $startTime, \DateTimeInterface $endTime, int $excludeReservationId): bool
    {
        $count = $this->createQueryBuilder('s')
            ->select('COUNT(r.id)')
            ->join('s.reservations', 'r')
            ->andWhere('s.id = :spaceId')
            ->andWhere('r.id != :excludeId')
            ->andWhere('r.status != :canceledStatus')
            ->andWhere('r.endTime > :startTime')
            ->andWhere('r.startTime < :endTime')
            ->setParameter('spaceId', $spaceId)
            ->setParameter('excludeId', $excludeReservationId)
            ->setParameter('canceledStatus', 'canceled')
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime)
            ->getQuery()
            ->getSingleScalarResult();
        
        return $count == 0;
    }
}
