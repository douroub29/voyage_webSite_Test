<?php

namespace App\Entity;

use App\Enum\MethodePaiement;
use App\Enum\StatutPaiement;
use App\Repository\PaiementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaiementRepository::class)]
#[ORM\Table(name: 'paiement')]
class Paiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $montant = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $datePaiement = null;

    #[ORM\Column(type: 'string', enumType: MethodePaiement::class)]
    private ?MethodePaiement $methode = null;

    #[ORM\Column(type: 'string', enumType: StatutPaiement::class)]
    private StatutPaiement $statut = StatutPaiement::EnAttente;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $transactionId = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToOne(targetEntity: Reservation::class, inversedBy: 'paiement')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Reservation $reservation = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getMontant(): ?string { return $this->montant; }
    public function setMontant(string $montant): static { $this->montant = $montant; return $this; }

    public function getDatePaiement(): ?\DateTimeInterface { return $this->datePaiement; }
    public function setDatePaiement(?\DateTimeInterface $datePaiement): static { $this->datePaiement = $datePaiement; return $this; }

    public function getMethode(): ?MethodePaiement { return $this->methode; }
    public function setMethode(MethodePaiement $methode): static { $this->methode = $methode; return $this; }

    public function getStatut(): StatutPaiement { return $this->statut; }
    public function setStatut(StatutPaiement $statut): static { $this->statut = $statut; return $this; }

    public function getTransactionId(): ?string { return $this->transactionId; }
    public function setTransactionId(?string $transactionId): static { $this->transactionId = $transactionId; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getReservation(): ?Reservation { return $this->reservation; }
    public function setReservation(?Reservation $reservation): static { $this->reservation = $reservation; return $this; }
}
