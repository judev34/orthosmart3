<?php

namespace App\Repository;

use App\Entity\ItemIDE;
use App\Entity\TestIDE;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemIDE>
 */
class ItemIDERepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemIDE::class);
    }

    /**
     * Trouve les items d'un test IDE par partie
     */
    public function findByTestAndPartie(TestIDE $test, string $partie): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.testIDE = :test')
            ->andWhere('i.partie = :partie')
            ->andWhere('i.actif = true')
            ->setParameter('test', $test)
            ->setParameter('partie', $partie)
            ->orderBy('i.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les items d'un test IDE par domaine
     */
    public function findByTestAndDomaine(TestIDE $test, string $domaine): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.testIDE = :test')
            ->andWhere('i.domaine = :domaine')
            ->andWhere('i.actif = true')
            ->setParameter('test', $test)
            ->setParameter('domaine', $domaine)
            ->orderBy('i.partie', 'ASC')
            ->addOrderBy('i.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les items DG (Développement Général) d'un test
     */
    public function findItemsDG(TestIDE $test): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.testIDE = :test')
            ->andWhere('i.compteDG = true')
            ->andWhere('i.actif = true')
            ->setParameter('test', $test)
            ->orderBy('i.partie', 'ASC')
            ->addOrderBy('i.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les items applicables pour un âge donné
     */
    public function findByTestAndAge(TestIDE $test, int $ageEnMois): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.testIDE = :test')
            ->andWhere('i.actif = true')
            ->andWhere('(i.ageMinMois IS NULL OR i.ageMinMois <= :age)')
            ->andWhere('(i.ageMaxMois IS NULL OR i.ageMaxMois >= :age)')
            ->setParameter('test', $test)
            ->setParameter('age', $ageEnMois)
            ->orderBy('i.partie', 'ASC')
            ->addOrderBy('i.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les items par domaine pour un test
     */
    public function countByTestAndDomaine(TestIDE $test, string $domaine): int
    {
        return $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.testIDE = :test')
            ->andWhere('i.domaine = :domaine')
            ->andWhere('i.actif = true')
            ->setParameter('test', $test)
            ->setParameter('domaine', $domaine)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les items DG par partie pour un test
     */
    public function countItemsDGByPartie(TestIDE $test, string $partie): int
    {
        return $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.testIDE = :test')
            ->andWhere('i.partie = :partie')
            ->andWhere('i.compteDG = true')
            ->andWhere('i.actif = true')
            ->setParameter('test', $test)
            ->setParameter('partie', $partie)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
