<?php

namespace App\Entity;

use App\Enum\TypeTransfert;
use App\Repository\DisponibiliteChauffeurRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DisponibiliteChauffeurRepository::class)]
#[ORM\Table(name: 'disponibilite_chauffeur')]
class DisponibiliteChauffeur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'string', enumType: TypeTransfert::class)]
    private ?TypeTransfert $typeTrajet = null;

    #[ORM\Column(type: 'boolean')]
    private bool $estDisponible = true;

    #[ORM\ManyToOne(targetEntity: Chauffeur::class, inversedBy: 'disponibilites')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Chauffeur $chauffeur = null;

    public function getId(): ?int { return $this->id; }

    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function setDate(\DateTimeInterface $date): static { $this->date = $date; return $this; }

    public function getTypeTrajet(): ?TypeTransfert { return $this->typeTrajet; }
    public function setTypeTrajet(TypeTransfert $typeTrajet): static { $this->typeTrajet = $typeTrajet; return $this; }

    public function isEstDisponible(): bool { return $this->estDisponible; }
    public function setEstDisponible(bool $estDisponible): static { $this->estDisponible = $estDisponible; return $this; }

    public function getChauffeur(): ?Chauffeur { return $this->chauffeur; }
    public function setChauffeur(?Chauffeur $chauffeur): static { $this->chauffeur = $chauffeur; return $this; }
}
