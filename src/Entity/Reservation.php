<?php

namespace App\Entity;

use App\Enum\StatutReservation;
use App\Enum\TypeTransfert;
use App\Repository\ReservationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservation')]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', enumType: StatutReservation::class)]
    private StatutReservation $statut = StatutReservation::EnAttente;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive(message: 'Le nombre de personnes doit être au moins 1.')]
    private int $nombrePersonnes = 1;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $nombreNuits = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $prixTotal = '0.00';

    #[ORM\Column(type: 'string', enumType: TypeTransfert::class, nullable: true)]
    private ?TypeTransfert $typeTransfert = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'string', length: 20, unique: true, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: Voyage::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Voyage $voyage = null;

    #[ORM\ManyToOne(targetEntity: Hotel::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Hotel $hotel = null;

    #[ORM\ManyToOne(targetEntity: Chauffeur::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Chauffeur $chauffeur = null;

    #[ORM\OneToOne(targetEntity: Paiement::class, mappedBy: 'reservation', cascade: ['persist', 'remove'])]
    private ?Paiement $paiement = null;

    /** Voucher de fidélité appliqué automatiquement à cette réservation. */
    #[ORM\OneToOne(targetEntity: Voucher::class, inversedBy: 'reservation')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Voucher $voucher = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->reference = 'DT-' . strtoupper(substr(uniqid(), -8));
    }

    public function getId(): ?int { return $this->id; }

    public function getStatut(): StatutReservation { return $this->statut; }
    public function setStatut(StatutReservation $statut): static { $this->statut = $statut; return $this; }

    public function getNombrePersonnes(): int { return $this->nombrePersonnes; }
    public function setNombrePersonnes(int $nombrePersonnes): static { $this->nombrePersonnes = $nombrePersonnes; return $this; }

    public function getNombreNuits(): ?int { return $this->nombreNuits; }
    public function setNombreNuits(?int $nombreNuits): static { $this->nombreNuits = $nombreNuits; return $this; }

    public function getPrixTotal(): string { return $this->prixTotal; }
    public function setPrixTotal(string $prixTotal): static { $this->prixTotal = $prixTotal; return $this; }

    public function getTypeTransfert(): ?TypeTransfert { return $this->typeTransfert; }
    public function setTypeTransfert(?TypeTransfert $typeTransfert): static { $this->typeTransfert = $typeTransfert; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $reference): static { $this->reference = $reference; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getClient(): ?Client { return $this->client; }
    public function setClient(?Client $client): static { $this->client = $client; return $this; }

    public function getVoyage(): ?Voyage { return $this->voyage; }
    public function setVoyage(?Voyage $voyage): static { $this->voyage = $voyage; return $this; }

    public function getHotel(): ?Hotel { return $this->hotel; }
    public function setHotel(?Hotel $hotel): static { $this->hotel = $hotel; return $this; }

    public function getChauffeur(): ?Chauffeur { return $this->chauffeur; }
    public function setChauffeur(?Chauffeur $chauffeur): static { $this->chauffeur = $chauffeur; return $this; }

    public function getPaiement(): ?Paiement { return $this->paiement; }
    public function setPaiement(?Paiement $paiement): static
    {
        if ($paiement === null && $this->paiement !== null) {
            $this->paiement->setReservation(null);
        }
        if ($paiement !== null && $paiement->getReservation() !== $this) {
            $paiement->setReservation($this);
        }
        $this->paiement = $paiement;
        return $this;
    }

    public function getVoucher(): ?Voucher { return $this->voucher; }

    public function appliquerVoucher(?Voucher $voucher): static
    {
        $this->voucher?->setReservation(null);
        $this->voucher = $voucher;
        $voucher?->setReservation($this);
        return $this;
    }

    /** Annule la réservation ; un voucher utilisé redevient disponible pour le client. */
    public function annuler(): void
    {
        $this->statut = StatutReservation::Annulee;
        $this->appliquerVoucher(null);
    }

    /** Montant de la remise du voucher (0 sans voucher). */
    public function getMontantRemise(): float
    {
        if (!$this->voucher) {
            return 0.0;
        }
        return round($this->calculerPrixBrut() * $this->voucher->getPourcentage() / 100, 2);
    }

    /** Prix final, remise du voucher déduite. */
    public function calculerPrixTotal(): float
    {
        return round($this->calculerPrixBrut() - $this->getMontantRemise(), 2);
    }

    /** Prix avant remise : voyage + hôtel + transfert. */
    public function calculerPrixBrut(): float
    {
        $prix = (float) $this->voyage->getPrix() * $this->nombrePersonnes;

        if ($this->hotel && $this->nombreNuits) {
            $prix += (float) $this->hotel->getPrixParNuit() * $this->nombreNuits;
        }

        if ($this->chauffeur) {
            $multiplicateur = $this->typeTransfert === TypeTransfert::LesDeux ? 2 : 1;
            $prix += (float) $this->chauffeur->getPrixTransfert() * $multiplicateur;
        }

        return $prix;
    }
}
