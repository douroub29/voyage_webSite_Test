<?php

namespace App\Entity;

use App\Enum\CompagnieAerienne;
use App\Enum\StatutReservation;
use App\Exception\ReservationException;
use App\Repository\VoyageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: VoyageRepository::class)]
#[ORM\Table(name: 'voyage')]
class Voyage
{
    /** Capacité maximale admise pour un avion (gros porteur). */
    public const CAPACITE_MAX_AVION = 850;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    private ?string $titre = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $dateDepart = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $dateRetour = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?string $prix = null;

    #[ORM\Column(type: 'string', length: 30, enumType: CompagnieAerienne::class)]
    #[Assert\NotNull(message: 'Veuillez choisir la compagnie aérienne.')]
    private ?CompagnieAerienne $compagnieAerienne = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Regex(
        pattern: '/^[A-Z0-9]{2}\s?\d{1,4}$/',
        message: 'Numéro de vol invalide (format attendu : TU 712).'
    )]
    private ?string $numeroVol = null;

    /** Capacité de l'avion : nombre total de places, saisi par l'admin. */
    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank(message: 'Indiquez le nombre de places de l\'avion.')]
    #[Assert\Range(min: 1, max: self::CAPACITE_MAX_AVION, notInRangeMessage: 'La capacité doit être comprise entre {{ min }} et {{ max }} places.')]
    private ?int $nombrePlaces = null;

    /** Places encore libres : gérées uniquement par le système (réservation / annulation). */
    #[ORM\Column(type: 'integer')]
    private int $placesDisponibles = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Destination::class, inversedBy: 'voyages')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Destination $destination = null;

    #[ORM\ManyToOne(targetEntity: Societe::class, inversedBy: 'voyages')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Societe $societe = null;

    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'voyage')]
    private Collection $reservations;

    public function __construct()
    {
        $this->createdAt    = new \DateTime();
        $this->reservations = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }

    public function getDateDepart(): ?\DateTimeInterface { return $this->dateDepart; }
    public function setDateDepart(\DateTimeInterface $dateDepart): static { $this->dateDepart = $dateDepart; return $this; }

    public function getDateRetour(): ?\DateTimeInterface { return $this->dateRetour; }
    public function setDateRetour(\DateTimeInterface $dateRetour): static { $this->dateRetour = $dateRetour; return $this; }

    public function getPrix(): ?string { return $this->prix; }
    public function setPrix(string $prix): static { $this->prix = $prix; return $this; }

    public function getCompagnieAerienne(): ?CompagnieAerienne { return $this->compagnieAerienne; }
    public function setCompagnieAerienne(?CompagnieAerienne $compagnie): static { $this->compagnieAerienne = $compagnie; return $this; }

    public function getNumeroVol(): ?string { return $this->numeroVol; }
    public function setNumeroVol(?string $numeroVol): static
    {
        $this->numeroVol = $numeroVol !== null && trim($numeroVol) !== '' ? strtoupper(trim($numeroVol)) : null;
        return $this;
    }

    public function getNombrePlaces(): ?int { return $this->nombrePlaces; }

    /**
     * Modifie la capacité de l'avion. Les places déjà réservées sont conservées :
     * seules les places libres sont ajustées de la différence.
     * Si la nouvelle capacité est inférieure aux places réservées, la validation échoue.
     */
    public function setNombrePlaces(?int $nombrePlaces): static
    {
        $nombrePlaces ??= 0;
        $this->placesDisponibles += $nombrePlaces - ($this->nombrePlaces ?? 0);
        $this->nombrePlaces = $nombrePlaces;
        return $this;
    }

    public function getPlacesDisponibles(): int { return $this->placesDisponibles; }

    public function getPhoto(): ?string { return $this->photo; }
    public function setPhoto(?string $photo): static { $this->photo = $photo; return $this; }

    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $actif): static { $this->actif = $actif; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getDestination(): ?Destination { return $this->destination; }
    public function setDestination(?Destination $destination): static { $this->destination = $destination; return $this; }

    public function getSociete(): ?Societe { return $this->societe; }
    public function setSociete(?Societe $societe): static { $this->societe = $societe; return $this; }

    /** URL de la photo : photo du voyage, sinon celle de la destination, sinon null. */
    public function getPhotoUrl(): ?string
    {
        $photo = $this->photo ?? $this->destination?->getPhoto();

        return $photo ? '/uploads/' . $photo : null;
    }

    public function getReservations(): Collection { return $this->reservations; }

    // ── Règles de gestion ────────────────────────────────────────────

    public function getDureeJours(): int
    {
        if (!$this->dateDepart || !$this->dateRetour) return 0;
        return $this->dateDepart->diff($this->dateRetour)->days;
    }

    /** Alias conservé pour les templates existants. */
    public function getPlacesRestantes(): int
    {
        return max(0, $this->placesDisponibles);
    }

    public function getPlacesReservees(): int
    {
        return ($this->nombrePlaces ?? 0) - $this->placesDisponibles;
    }

    public function getTauxRemplissage(): int
    {
        if (!$this->nombrePlaces) return 0;
        return (int) round($this->getPlacesReservees() * 100 / $this->nombrePlaces);
    }

    public function isComplet(): bool
    {
        return $this->placesDisponibles <= 0;
    }

    /**
     * Les réservations ferment le jour du départ : un voyage est "parti"
     * dès que sa date de départ est aujourd'hui ou dans le passé.
     */
    public function isDepartPasse(?\DateTimeInterface $aujourdhui = null): bool
    {
        if (!$this->dateDepart) return false;
        $aujourdhui ??= new \DateTimeImmutable('today');

        return self::jour($this->dateDepart) <= self::jour($aujourdhui);
    }

    public function isReservable(?\DateTimeInterface $aujourdhui = null): bool
    {
        return $this->actif && !$this->isDepartPasse($aujourdhui) && !$this->isComplet();
    }

    /** Raison pour laquelle le voyage n'est pas réservable (null s'il l'est). */
    public function getMotifNonReservable(?\DateTimeInterface $aujourdhui = null): ?string
    {
        return match (true) {
            $this->isDepartPasse($aujourdhui) => 'Réservations fermées : voyage déjà parti',
            !$this->actif                     => 'Voyage indisponible',
            $this->isComplet()                => 'Complet : plus de places disponibles',
            default                           => null,
        };
    }

    /**
     * Décrémente les places libres. À appeler via ReservationManager,
     * qui verrouille la ligne en base pour éviter la survente.
     *
     * @throws ReservationException
     */
    public function reserverPlaces(int $nombre, ?\DateTimeInterface $aujourdhui = null): void
    {
        if ($nombre < 1) {
            throw ReservationException::nombrePersonnesInvalide();
        }
        if ($this->isDepartPasse($aujourdhui)) {
            throw ReservationException::voyageParti();
        }
        if (!$this->actif) {
            throw ReservationException::voyageInactif();
        }
        if ($nombre > $this->placesDisponibles) {
            throw ReservationException::placesInsuffisantes($this->getPlacesRestantes());
        }

        $this->placesDisponibles -= $nombre;
    }

    /** Remet des places en vente (annulation). Ne dépasse jamais la capacité de l'avion. */
    public function libererPlaces(int $nombre): void
    {
        $this->placesDisponibles = min($this->nombrePlaces ?? 0, $this->placesDisponibles + max(0, $nombre));
    }

    /** Places occupées recalculées depuis les réservations (contrôle de cohérence). */
    public function compterPlacesReserveesDepuisReservations(): int
    {
        return array_sum($this->reservations
            ->filter(fn(Reservation $r) => $r->getStatut() !== StatutReservation::Annulee)
            ->map(fn(Reservation $r) => $r->getNombrePersonnes())
            ->toArray());
    }

    #[Assert\Callback]
    public function valider(ExecutionContextInterface $context): void
    {
        if ($this->dateDepart && $this->dateRetour && self::jour($this->dateRetour) <= self::jour($this->dateDepart)) {
            $context->buildViolation('La date de retour doit être postérieure à la date de départ.')
                ->atPath('dateRetour')
                ->addViolation();
        }

        // Un nouveau voyage ne peut pas être créé avec un départ passé.
        if ($this->id === null && $this->isDepartPasse()) {
            $context->buildViolation('La date de départ doit être dans le futur.')
                ->atPath('dateDepart')
                ->addViolation();
        }

        if ($this->nombrePlaces !== null && $this->placesDisponibles < 0) {
            $context->buildViolation('La capacité ne peut pas être inférieure aux {{ n }} place(s) déjà réservée(s).')
                ->setParameter('{{ n }}', (string) $this->getPlacesReservees())
                ->atPath('nombrePlaces')
                ->addViolation();
        }

        if ($this->numeroVol && $this->compagnieAerienne
            && !str_starts_with($this->numeroVol, $this->compagnieAerienne->codeIata())) {
            $context->buildViolation('Le numéro de vol doit commencer par le code de la compagnie ({{ code }}).')
                ->setParameter('{{ code }}', $this->compagnieAerienne->codeIata())
                ->atPath('numeroVol')
                ->addViolation();
        }
    }

    private static function jour(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    public function __toString(): string { return $this->titre ?? ''; }
}
