<?php

namespace App\Entity;

use App\Repository\VoucherRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ticket de fidélité : remise sur la prochaine réservation.
 * Gagné toutes les VoucherManager::SEUIL_RESERVATIONS réservations confirmées,
 * stocké sur le compte du client et appliqué automatiquement à sa prochaine réservation.
 */
#[ORM\Entity(repositoryClass: VoucherRepository::class)]
#[ORM\Table(name: 'voucher')]
class Voucher
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private string $code;

    /** Pourcentage de remise (ex. 10 = -10 %). */
    #[ORM\Column(type: 'smallint')]
    private int $pourcentage;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'vouchers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Client $client;

    /** Réservation sur laquelle la remise a été appliquée (null = encore disponible). */
    #[ORM\OneToOne(targetEntity: Reservation::class, mappedBy: 'voucher')]
    private ?Reservation $reservation = null;

    public function __construct(Client $client, int $pourcentage)
    {
        $this->client      = $client;
        $this->pourcentage = $pourcentage;
        $this->createdAt   = new \DateTime();
        $this->code        = 'VCH-' . strtoupper(bin2hex(random_bytes(4)));
    }

    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getPourcentage(): int { return $this->pourcentage; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getClient(): Client { return $this->client; }

    public function getReservation(): ?Reservation { return $this->reservation; }
    public function setReservation(?Reservation $reservation): static { $this->reservation = $reservation; return $this; }

    public function isUtilise(): bool { return $this->reservation !== null; }
}
