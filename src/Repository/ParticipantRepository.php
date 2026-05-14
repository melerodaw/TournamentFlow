<?php

namespace App\Repository;

use App\Entity\Participant;
use App\Entity\Tournament;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Participant>
 */
class ParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participant::class);
    }

    public function findOneByTournamentAndUser(Tournament $tournament, User $user): ?Participant
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.tournament = :tournament')
            ->andWhere('p.user = :user')
            ->setParameter('tournament', $tournament)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Participant[]
     */
    public function findByTournamentOrderedByRegisteredAtAsc(Tournament $tournament): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('u')
            ->innerJoin('p.user', 'u')
            ->andWhere('p.tournament = :tournament')
            ->setParameter('tournament', $tournament)
            ->orderBy('p.registeredAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Participant[]
     */
    public function findByUserOrderedByRegisteredAtDesc(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('t')
            ->addSelect('o')
            ->innerJoin('p.tournament', 't')
            ->innerJoin('t.organizer', 'o')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.registeredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByTournament(Tournament $tournament): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.tournament = :tournament')
            ->setParameter('tournament', $tournament)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param int[] $tournamentIds
     *
     * @return array<int, int>
     */
    public function countByTournamentIds(array $tournamentIds): array
    {
        if ([] === $tournamentIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('p')
            ->select('IDENTITY(p.tournament) AS tournamentId')
            ->addSelect('COUNT(p.id) AS participantCount')
            ->andWhere('p.tournament IN (:ids)')
            ->setParameter('ids', $tournamentIds)
            ->groupBy('p.tournament')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['tournamentId']] = (int) $row['participantCount'];
        }

        return $counts;
    }

    //    /**
    //     * @return Participant[] Returns an array of Participant objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Participant
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
