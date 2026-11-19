<?php

namespace App\Entity;

use App\Repository\PhotoHotelRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PhotoHotelRepository::class)]
#[ORM\Table(name: 'photo_hotel')]
class PhotoHotel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $chemin = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $legende = null;

    #[ORM\ManyToOne(targetEntity: Hotel::class, inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Hotel $hotel = null;

    public function getId(): ?int { return $this->id; }

    public function getChemin(): ?string { return $this->chemin; }
    public function setChemin(string $chemin): static { $this->chemin = $chemin; return $this; }

    public function getLegende(): ?string { return $this->legende; }
    public function setLegende(?string $legende): static { $this->legende = $legende; return $this; }

    public function getHotel(): ?Hotel { return $this->hotel; }
    public function setHotel(?Hotel $hotel): static { $this->hotel = $hotel; return $this; }
}
