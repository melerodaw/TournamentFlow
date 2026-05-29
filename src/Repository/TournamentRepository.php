<?php

namespace App\Repository;

use App\Entity\Tournament;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tournament>
 */
class TournamentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tournament::class);
    }

    /**
     * @return Tournament[]
     */
    public function findAllOrderedByCreatedAtDesc(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Tournament[]
     */
    public function findByOrganizerOrderedByCreatedAtDesc(User $organizer): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.organizer = :organizer')
            ->setParameter('organizer', $organizer)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Tournament[]
     */
    public function findForListing(?string $search, ?int $gameId): array
    {
        $qb = $this->createQueryBuilder('t')
            ->addSelect('g')
            ->innerJoin('t.game', 'g')
            ->orderBy('t.createdAt', 'DESC');

        if (null !== $search && '' !== trim($search)) {
            $qb->andWhere('LOWER(t.name) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower(trim($search)).'%');
        }

        if (null !== $gameId) {
            $qb->andWhere('g.id = :gameId')
                ->setParameter('gameId', $gameId);
        }

        return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return Tournament[] Returns an array of Tournament objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Tournament
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
