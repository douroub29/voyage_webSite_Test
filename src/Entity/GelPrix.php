<?php

namespace App\Entity;

use App\Enum\MethodePaiement;
use App\Repository\GelPrixRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Gel de prix (« Price Freeze ») : le client paie des frais pour bloquer le prix
 * par personne d'un voyage pendant quelques jours.
 * À la réservation, il paie le plus bas entre le prix gelé et le prix du moment.
 * Les frais ne sont pas remboursables et le gel ne bloque pas de places.
 */
#[ORM\Entity(repositoryClass: GelPrixRepository::class)]
#[ORM\Table(name: 'gel_prix')]
class GelPrix
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Client $client;

    #[ORM\ManyToOne(targetEntity: Voyage::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Voyage $voyage;

    /** Prix par personne bloqué. */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $prixGele;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $frais;

    #[ORM\Column(type: 'string', enumType: MethodePaiement::class)]
    private MethodePaiement $methode;

    #[ORM\Column(length: 100)]
    private string $transactionId;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $expireLe;

    /** Réservation qui a utilisé le gel (null tant qu'il n'est pas utilisé). */
    #[ORM\OneToOne(targetEntity: Reservation::class, mappedBy: 'gelPrix')]
    private ?Reservation $reservation = null;

    public function __construct(Client $client, Voyage $voyage, float $frais, MethodePaiement $methode, int $dureeJours, ?\DateTimeInterface $maintenant = null)
    {
        $maintenant ??= new \DateTime();

        $this->client        = $client;
        $this->voyage        = $voyage;
        $this->prixGele      = (string) $voyage->getPrix();
        $this->frais         = number_format($frais, 2, '.', '');
        $this->methode       = $methode;
        $this->transactionId = 'GEL-' . strtoupper(uniqid());
        $this->createdAt     = \DateTime::createFromInterface($maintenant);
        $this->expireLe      = \DateTime::createFromInterface($maintenant)->modify('+' . $dureeJours . ' days');

        // Le gel ne peut pas durer au-delà du départ.
        if ($voyage->getDateDepart() && $this->expireLe > $voyage->getDateDepart()) {
            $this->expireLe = \DateTime::createFromInterface($voyage->getDateDepart());
        }
    }

    public function getId(): ?int { return $this->id; }
    public function getClient(): Client { return $this->client; }
    public function getVoyage(): Voyage { return $this->voyage; }
    public function getPrixGele(): string { return $this->prixGele; }
    public function getFrais(): string { return $this->frais; }
    public function getMethode(): MethodePaiement { return $this->methode; }
    public function getTransactionId(): string { return $this->transactionId; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getExpireLe(): \DateTimeInterface { return $this->expireLe; }

    public function getReservation(): ?Reservation { return $this->reservation; }
    public function setReservation(?Reservation $reservation): static { $this->reservation = $reservation; return $this; }

    public function isUtilise(): bool { return $this->reservation !== null; }

    public function isExpire(?\DateTimeInterface $maintenant = null): bool
    {
        return ($maintenant ?? new \DateTime()) >= $this->expireLe;
    }

    /** Utilisable pour une nouvelle réservation. */
    public function isActif(?\DateTimeInterface $maintenant = null): bool
    {
        return !$this->isUtilise() && !$this->isExpire($maintenant);
    }

    /** Prix par personne réellement payé : le plus bas entre le prix gelé et le prix actuel. */
    public function prixApplicable(): float
    {
        return min((float) $this->prixGele, (float) $this->voyage->getPrix());
    }

    /** Économie par personne si le prix a monté depuis le gel (0 sinon). */
    public function getEconomieParPersonne(): float
    {
        return max(0.0, round((float) $this->voyage->getPrix() - (float) $this->prixGele, 2));
    }

    public function getJoursRestants(?\DateTimeInterface $maintenant = null): int
    {
        $maintenant ??= new \DateTime();
        if ($this->isExpire($maintenant)) {
            return 0;
        }
        return max(1, (int) ceil(($this->expireLe->getTimestamp() - $maintenant->getTimestamp()) / 86400));
    }
}
