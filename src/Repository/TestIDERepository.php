<?php

namespace App\Repository;

use App\Entity\TestIDE;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TestIDE>
 */
class TestIDERepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestIDE::class);
    }

    /**
     * Trouve les tests IDE compatibles avec l'âge donné
     */
    public function findCompatiblesAvecAge(int $ageEnMois): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.ageMinMois <= :age')
            ->andWhere('t.ageMaxMois >= :age')
            ->andWhere('t.actif = true')
            ->setParameter('age', $ageEnMois)
            ->orderBy('t.version', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le test IDE le plus récent
     */
    public function findDernierTest(): ?TestIDE
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.actif = true')
            ->orderBy('t.version', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les tests IDE par version
     */
    public function findByVersion(string $version): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.version = :version')
            ->andWhere('t.actif = true')
            ->setParameter('version', $version)
            ->orderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre total de tests IDE actifs
     */
    public function countActifs(): int
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.actif = true')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
