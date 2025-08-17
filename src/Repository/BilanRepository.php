<?php

namespace App\Repository;

use App\Entity\Bilan;
use App\Entity\Prescription;
use App\Entity\User;
use App\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bilan>
 */
class BilanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bilan::class);
    }

    /**
     * Trouve les bilans d'une prescription
     */
    public function findByPrescription(Prescription $prescription): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.prescription = :prescription')
            ->setParameter('prescription', $prescription)
            ->orderBy('b.version', 'DESC')
            ->addOrderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le dernier bilan d'une prescription
     */
    public function findLastByPrescription(Prescription $prescription): ?Bilan
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.prescription = :prescription')
            ->setParameter('prescription', $prescription)
            ->orderBy('b.version', 'DESC')
            ->addOrderBy('b.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les bilans d'un praticien
     */
    public function findByPraticien(User $praticien): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.prescription', 'p')
            ->andWhere('p.praticien = :praticien')
            ->setParameter('praticien', $praticien)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les bilans d'un patient
     */
    public function findByPatient(Patient $patient): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.prescription', 'p')
            ->andWhere('p.patient = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les bilans par statut
     */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les bilans en attente de validation pour un praticien
     */
    public function findEnAttenteValidation(User $praticien): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.prescription', 'p')
            ->andWhere('p.praticien = :praticien')
            ->andWhere('b.statut = :statut')
            ->setParameter('praticien', $praticien)
            ->setParameter('statut', Bilan::STATUT_GENERE)
            ->orderBy('b.dateGeneration', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les bilans par niveau de risque
     */
    public function findByNiveauRisque(string $niveauRisque): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.niveauRisqueGlobal = :niveau')
            ->setParameter('niveau', $niveauRisque)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les bilans avec haut risque ou très haut risque
     */
    public function findAvecRisqueEleve(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.niveauRisqueGlobal IN (:niveaux)')
            ->setParameter('niveaux', [
                Bilan::RISQUE_HAUT,
                Bilan::RISQUE_TRES_HAUT
            ])
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des bilans par niveau de risque
     */
    public function getStatistiquesRisque(): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select('b.niveauRisqueGlobal, COUNT(b.id) as nombre')
            ->andWhere('b.niveauRisqueGlobal IS NOT NULL')
            ->groupBy('b.niveauRisqueGlobal');

        $results = $qb->getQuery()->getResult();
        
        $stats = [];
        foreach ($results as $result) {
            $stats[$result['niveauRisqueGlobal']] = (int) $result['nombre'];
        }
        
        return $stats;
    }

    /**
     * Trouve les bilans validés récemment
     */
    public function findValidesRecents(int $jours = 30): array
    {
        $dateLimit = new \DateTime();
        $dateLimit->modify("-{$jours} days");

        return $this->createQueryBuilder('b')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('b.dateValidation >= :dateLimit')
            ->setParameter('statuts', [
                Bilan::STATUT_VALIDE,
                Bilan::STATUT_FINALISE
            ])
            ->setParameter('dateLimit', $dateLimit)
            ->orderBy('b.dateValidation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les bilans par statut pour un praticien
     */
    public function countByStatutForPraticien(User $praticien, string $statut): int
    {
        return $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->join('b.prescription', 'p')
            ->andWhere('p.praticien = :praticien')
            ->andWhere('b.statut = :statut')
            ->setParameter('praticien', $praticien)
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Score DG moyen par tranche d'âge
     */
    public function getScoreDGMoyenParAge(): array
    {
        return $this->createQueryBuilder('b')
            ->select('
                CASE 
                    WHEN b.ageDeveloppementMois < 24 THEN "0-2 ans"
                    WHEN b.ageDeveloppementMois < 36 THEN "2-3 ans"
                    WHEN b.ageDeveloppementMois < 48 THEN "3-4 ans"
                    WHEN b.ageDeveloppementMois < 60 THEN "4-5 ans"
                    ELSE "5+ ans"
                END as tranche_age,
                AVG(b.scoreDG) as score_moyen,
                COUNT(b.id) as nombre_bilans
            ')
            ->andWhere('b.scoreDG IS NOT NULL')
            ->andWhere('b.ageDeveloppementMois IS NOT NULL')
            ->groupBy('tranche_age')
            ->orderBy('MIN(b.ageDeveloppementMois)', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
