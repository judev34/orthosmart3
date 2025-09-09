<?php

namespace App\Entity;

use App\Repository\TestIDERepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TestIDERepository::class)]
#[ORM\Table(name: 'test_ide')]
class TestIDE extends Test
{
    /**
     * Domaines d'évaluation IDE
     */
    public const DOMAINES = [
        'SO' => 'Social',
        'AU' => 'Autonomie',
        'MG' => 'Moteur Global',
        'MF' => 'Moteur Fin',
        'LEX' => 'Langage Expressif',
        'LCO' => 'Compréhension du Langage',
        'LE' => 'Apprentissage des Lettres',
        'NBRE' => 'Apprentissage des Nombres',
        'DG' => 'Développement Général'
    ];

    /**
     * Parties du questionnaire IDE
     */
    public const PARTIES = [
        'AP' => 'Partie AP (15-18 mois)',
        'A' => 'Partie A (18-24 mois)',
        'B' => 'Partie B (24-36 mois)',
        'C' => 'Partie C (36-48 mois)',
        'D' => 'Partie D (48-60 mois)',
        'E' => 'Partie E (60-72 mois)'
    ];

    /**
     * Répartition des items DG par partie (selon la grille de calcul)
     */
    public const ITEMS_DG_PAR_PARTIE = [
        'A' => 17,
        'B' => 12,
        'C' => 11,
        'D' => 6,
        'E' => 23
    ];

    /**
     * @var Collection<int, ItemIDE>
     */
    #[ORM\OneToMany(mappedBy: 'testIDE', targetEntity: ItemIDE::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['partie' => 'ASC', 'ordre' => 'ASC'])]
    private Collection $items;

    public function __construct()
    {
        parent::__construct();
        $this->items = new ArrayCollection();
    }

    public function getType(): string
    {
        return 'IDE';
    }

    public function getDomaines(): array
    {
        return self::DOMAINES;
    }

    /**
     * @return Collection<int, ItemIDE>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(ItemIDE $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setTestIDE($this);
        }

        return $this;
    }

    public function removeItem(ItemIDE $item): static
    {
        if ($this->items->removeElement($item)) {
            // set the owning side to null (unless already changed)
            if ($item->getTestIDE() === $this) {
                $item->setTestIDE(null);
            }
        }

        return $this;
    }

    /**
     * Retourne les items d'une partie spécifique
     */
    public function getItemsParPartie(string $partie): Collection
    {
        return $this->items->filter(function (ItemIDE $item) use ($partie) {
            return $item->getPartie() === $partie;
        });
    }

    /**
     * Retourne les items d'un domaine spécifique
     */
    public function getItemsParDomaine(string $domaine): Collection
    {
        return $this->items->filter(function (ItemIDE $item) use ($domaine) {
            return $item->getDomaine() === $domaine;
        });
    }

    /**
     * Retourne les items qui comptent pour le Développement Général (DG)
     */
    public function getItemsDG(): Collection
    {
        return $this->items->filter(function (ItemIDE $item) {
            return $item->isCompteDG();
        });
    }

    /**
     * Calcule les seuils de risque selon l'âge chronologique
     */
    public function calculerSeuilsRisque(int $ageChronologiqueMois): array
    {
        return [
            'haut_risque' => (int) round($ageChronologiqueMois * 0.85),
            'tres_haut_risque' => (int) round($ageChronologiqueMois * 0.70)
        ];
    }

    /**
     * Vérifie si un domaine est valide pour l'âge donné
     */
    public function isDomaineValideAge(string $domaine, int $ageEnMois): bool
    {
        // LE et NBRE sont valides uniquement à partir de 4 ans (48 mois)
        if (in_array($domaine, ['LE', 'NBRE']) && $ageEnMois < 48) {
            return false;
        }
        
        return array_key_exists($domaine, self::DOMAINES);
    }

    /**
     * Retourne le nombre total d'items DG attendus
     */
    public function getNombreTotalItemsDG(): int
    {
        return array_sum(self::ITEMS_DG_PAR_PARTIE);
    }

    /**
     * Retourne les parties ordonnées selon la progression par âge
     */
    public function getPartiesOrdonnes(): array
    {
        return array_keys(self::PARTIES);
    }
}
