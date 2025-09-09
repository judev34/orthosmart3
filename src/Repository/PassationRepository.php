<?php

namespace App\Repository;

use App\Entity\Passation;
use App\Entity\Prescription;
use App\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Passation>
 */
class PassationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Passation::class);
    }

    /**
     * Trouve les passations d'une prescription
     */
    public function findByPrescription(Prescription $prescription): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.prescription = :prescription')
            ->setParameter('prescription', $prescription)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve la dernière passation d'une prescription
     */
    public function findLastByPrescription(Prescription $prescription): ?Passation
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.prescription = :prescription')
            ->setParameter('prescription', $prescription)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les passations en cours (non terminées)
     */
    public function findEnCours(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.statut IN (:statuts)')
            ->setParameter('statuts', [
                Passation::STATUT_DEMARREE,
                Passation::STATUT_EN_COURS,
                Passation::STATUT_SUSPENDUE
            ])
            ->orderBy('s.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les passations terminées d'un patient
     */
    public function findTermineesForPatient(Patient $patient): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.prescription', 'p')
            ->andWhere('p.patient = :patient')
            ->andWhere('s.statut = :statut')
            ->setParameter('patient', $patient)
            ->setParameter('statut', Passation::STATUT_TERMINEE)
            ->orderBy('s.dateFin', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les passations abandonnées depuis plus de X jours
     */
    public function findAbandonneesDepuis(int $jours): array
    {
        $dateLimit = new \DateTime();
        $dateLimit->modify("-{$jours} days");

        return $this->createQueryBuilder('s')
            ->andWhere('s.statut = :statut')
            ->andWhere('s.updatedAt < :dateLimit')
            ->setParameter('statut', Passation::STATUT_ABANDONNEE)
            ->setParameter('dateLimit', $dateLimit)
            ->orderBy('s.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les passations suspendues depuis plus de X jours
     */
    public function findSuspenduesDepuis(int $jours): array
    {
        $dateLimit = new \DateTime();
        $dateLimit->modify("-{$jours} days");

        return $this->createQueryBuilder('s')
            ->andWhere('s.statut = :statut')
            ->andWhere('s.updatedAt < :dateLimit')
            ->setParameter('statut', Passation::STATUT_SUSPENDUE)
            ->setParameter('dateLimit', $dateLimit)
            ->orderBy('s.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des passations par statut
     */
    public function getStatistiquesParStatut(): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.statut, COUNT(s.id) as nombre')
            ->groupBy('s.statut');

        $results = $qb->getQuery()->getResult();
        
        $stats = [];
        foreach ($results as $result) {
            $stats[$result['statut']] = (int) $result['nombre'];
        }
        
        return $stats;
    }

    /**
     * Durée moyenne des passations terminées
     */
    public function getDureeMoyenneTerminees(): ?float
    {
        return $this->createQueryBuilder('s')
            ->select('AVG(s.dureeMinutes)')
            ->andWhere('s.statut = :statut')
            ->andWhere('s.dureeMinutes IS NOT NULL')
            ->setParameter('statut', Passation::STATUT_TERMINEE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les passations avec progression incomplète
     */
    public function findProgressionIncomplete(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.progression < 100')
            ->andWhere('s.statut != :statut')
            ->setParameter('statut', Passation::STATUT_TERMINEE)
            ->orderBy('s.progression', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
