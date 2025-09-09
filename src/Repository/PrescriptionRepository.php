<?php

namespace App\Repository;

use App\Entity\Prescription;
use App\Entity\User;
use App\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Prescription>
 */
class PrescriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prescription::class);
    }

    /**
     * Trouve les prescriptions d'un praticien
     */
    public function findByPraticien(User $praticien): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.praticien = :praticien')
            ->setParameter('praticien', $praticien)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prescriptions d'un patient
     */
    public function findByPatient(Patient $patient): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.patient = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prescriptions par statut
     */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prescriptions en attente de validation pour un praticien
     */
    public function findEnAttenteValidation(User $praticien): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.praticien = :praticien')
            ->andWhere('p.statut = :statut')
            ->setParameter('praticien', $praticien)
            ->setParameter('statut', Prescription::STATUT_TERMINE)
            ->orderBy('p.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prescriptions en retard
     */
    public function findEnRetard(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.dateLimite < :now')
            ->andWhere('p.statut NOT IN (:statutsTermines)')
            ->setParameter('now', new \DateTime())
            ->setParameter('statutsTermines', [
                Prescription::STATUT_TERMINE,
                Prescription::STATUT_VALIDE,
                Prescription::STATUT_ANNULE
            ])
            ->orderBy('p.dateLimite', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prescriptions actives d'un patient
     */
    public function findActivesForPatient(Patient $patient): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.patient = :patient')
            ->andWhere('p.statut != :statut')
            ->setParameter('patient', $patient)
            ->setParameter('statut', Prescription::STATUT_ANNULE)
            ->orderBy('p.priorite', 'ASC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les prescriptions par statut pour un praticien
     */
    public function countByStatutForPraticien(User $praticien, string $statut): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.praticien = :praticien')
            ->andWhere('p.statut = :statut')
            ->setParameter('praticien', $praticien)
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les prescriptions avec consentement RGPD manquant
     */
    public function findSansConsentementRGPD(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.consentementRGPD = false')
            ->andWhere('p.statut != :statut')
            ->setParameter('statut', Prescription::STATUT_ANNULE)
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des prescriptions pour un praticien
     */
    public function getStatistiquesPraticien(User $praticien): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.statut, COUNT(p.id) as nombre')
            ->andWhere('p.praticien = :praticien')
            ->setParameter('praticien', $praticien)
            ->groupBy('p.statut');

        $results = $qb->getQuery()->getResult();
        
        $stats = [];
        foreach ($results as $result) {
            $stats[$result['statut']] = (int) $result['nombre'];
        }
        
        return $stats;
    }
}
