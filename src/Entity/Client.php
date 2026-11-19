<?php

namespace App\Entity;

use App\Enum\StatutReservation;
use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table(name: 'client')]
class Client extends Utilisateur
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateNaissance = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'client', cascade: ['persist'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $reservations;

    #[ORM\OneToMany(targetEntity: Avis::class, mappedBy: 'client', cascade: ['persist'])]
    private Collection $avis;

    #[ORM\OneToMany(targetEntity: Voucher::class, mappedBy: 'client')]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $vouchers;

    public function __construct()
    {
        parent::__construct();
        $this->setRoles(['ROLE_CLIENT']);
        $this->reservations = new ArrayCollection();
        $this->avis         = new ArrayCollection();
        $this->vouchers     = new ArrayCollection();
    }

    public function getAdresse(): ?string { return $this->adresse; }
    public function setAdresse(?string $adresse): static { $this->adresse = $adresse; return $this; }

    public function getDateNaissance(): ?\DateTimeInterface { return $this->dateNaissance; }
    public function setDateNaissance(?\DateTimeInterface $dateNaissance): static { $this->dateNaissance = $dateNaissance; return $this; }

    public function getPhoto(): ?string { return $this->photo; }
    public function setPhoto(?string $photo): static { $this->photo = $photo; return $this; }

    public function getReservations(): Collection { return $this->reservations; }
    public function addReservation(Reservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setClient($this);
        }
        return $this;
    }
    public function removeReservation(Reservation $reservation): static
    {
        $this->reservations->removeElement($reservation);
        return $this;
    }

    public function getAvis(): Collection { return $this->avis; }
    public function addAvi(Avis $avi): static
    {
        if (!$this->avis->contains($avi)) {
            $this->avis->add($avi);
            $avi->setClient($this);
        }
        return $this;
    }
    public function removeAvi(Avis $avi): static
    {
        $this->avis->removeElement($avi);
        return $this;
    }

    public function getVouchers(): Collection { return $this->vouchers; }

    /** Réservations confirmées (payées) : elles comptent pour la fidélité. */
    public function getNombreReservationsConfirmees(): int
    {
        return $this->reservations
            ->filter(fn(Reservation $r) => $r->getStatut() === StatutReservation::Confirmee)
            ->count();
    }
}
