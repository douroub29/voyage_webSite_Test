<?php

namespace App\Entity;

use App\Repository\HotelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HotelRepository::class)]
#[ORM\Table(name: 'hotel')]
class Hotel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $adresse = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?string $prixParNuit = null;

    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $etoiles = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photoPrincipale = null;

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Destination::class, inversedBy: 'hotels')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Destination $destination = null;

    #[ORM\OneToMany(targetEntity: PhotoHotel::class, mappedBy: 'hotel', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $photos;

    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'hotel')]
    private Collection $reservations;

    #[ORM\OneToMany(targetEntity: Avis::class, mappedBy: 'hotel')]
    private Collection $avis;

    public function __construct()
    {
        $this->createdAt  = new \DateTime();
        $this->photos     = new ArrayCollection();
        $this->reservations = new ArrayCollection();
        $this->avis       = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getAdresse(): ?string { return $this->adresse; }
    public function setAdresse(string $adresse): static { $this->adresse = $adresse; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }

    public function getPrixParNuit(): ?string { return $this->prixParNuit; }
    public function setPrixParNuit(string $prixParNuit): static { $this->prixParNuit = $prixParNuit; return $this; }

    public function getEtoiles(): ?int { return $this->etoiles; }
    public function setEtoiles(int $etoiles): static { $this->etoiles = $etoiles; return $this; }

    public function getPhotoPrincipale(): ?string { return $this->photoPrincipale; }
    public function setPhotoPrincipale(?string $photoPrincipale): static { $this->photoPrincipale = $photoPrincipale; return $this; }

    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $actif): static { $this->actif = $actif; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getDestination(): ?Destination { return $this->destination; }
    public function setDestination(?Destination $destination): static { $this->destination = $destination; return $this; }

    public function getPhotos(): Collection { return $this->photos; }
    public function addPhoto(PhotoHotel $photo): static
    {
        if (!$this->photos->contains($photo)) {
            $this->photos->add($photo);
            $photo->setHotel($this);
        }
        return $this;
    }
    public function removePhoto(PhotoHotel $photo): static
    {
        if ($this->photos->removeElement($photo) && $photo->getHotel() === $this) {
            $photo->setHotel(null);
        }
        return $this;
    }

    public function getReservations(): Collection { return $this->reservations; }

    public function getAvis(): Collection { return $this->avis; }

    public function getNoteMoyenne(): ?float
    {
        $publie = $this->avis->filter(fn(Avis $a) => $a->getStatut()->value === 'publie');
        if ($publie->isEmpty()) return null;
        $total = $publie->reduce(fn(float $carry, Avis $a) => $carry + $a->getNote(), 0);
        return round($total / $publie->count(), 1);
    }

    public function __toString(): string { return $this->nom ?? ''; }
}
