<?php

namespace App\Entity;

use App\Repository\DestinationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DestinationRepository::class)]
#[ORM\Table(name: 'destination')]
class Destination
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $pays = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToMany(targetEntity: Voyage::class, mappedBy: 'destination', cascade: ['persist'])]
    private Collection $voyages;

    #[ORM\OneToMany(targetEntity: Hotel::class, mappedBy: 'destination', cascade: ['persist'])]
    private Collection $hotels;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->voyages   = new ArrayCollection();
        $this->hotels    = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getPays(): ?string { return $this->pays; }
    public function setPays(string $pays): static { $this->pays = $pays; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }

    public function getPhoto(): ?string { return $this->photo; }
    public function setPhoto(?string $photo): static { $this->photo = $photo; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getVoyages(): Collection { return $this->voyages; }
    public function addVoyage(Voyage $voyage): static
    {
        if (!$this->voyages->contains($voyage)) {
            $this->voyages->add($voyage);
            $voyage->setDestination($this);
        }
        return $this;
    }
    public function removeVoyage(Voyage $voyage): static
    {
        if ($this->voyages->removeElement($voyage) && $voyage->getDestination() === $this) {
            $voyage->setDestination(null);
        }
        return $this;
    }

    public function getHotels(): Collection { return $this->hotels; }
    public function addHotel(Hotel $hotel): static
    {
        if (!$this->hotels->contains($hotel)) {
            $this->hotels->add($hotel);
            $hotel->setDestination($this);
        }
        return $this;
    }
    public function removeHotel(Hotel $hotel): static
    {
        if ($this->hotels->removeElement($hotel) && $hotel->getDestination() === $this) {
            $hotel->setDestination(null);
        }
        return $this;
    }

    public function __toString(): string { return $this->nom ?? ''; }
}
