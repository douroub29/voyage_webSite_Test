<?php

namespace App\Entity;

use App\Repository\SocieteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/** Société (agence / tour-opérateur) qui organise des voyages. */
#[ORM\Entity(repositoryClass: SocieteRepository::class)]
#[ORM\Table(name: 'societe')]
#[UniqueEntity(fields: ['nom'], message: 'Une société porte déjà ce nom.')]
class Societe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private ?string $nom = null;

    #[ORM\OneToMany(targetEntity: Voyage::class, mappedBy: 'societe')]
    private Collection $voyages;

    public function __construct()
    {
        $this->voyages = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = trim($nom); return $this; }

    public function getVoyages(): Collection { return $this->voyages; }

    public function __toString(): string { return $this->nom ?? ''; }
}
