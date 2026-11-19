<?php

namespace App\Entity;

use App\Repository\ChauffeurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ChauffeurRepository::class)]
#[ORM\Table(name: 'chauffeur')]
class Chauffeur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $prenom = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    private ?string $telephone = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    private ?string $voiture = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?string $prixTransfert = null;

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToMany(targetEntity: DisponibiliteChauffeur::class, mappedBy: 'chauffeur', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $disponibilites;

    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'chauffeur')]
    private Collection $reservations;

    public function __construct()
    {
        $this->createdAt      = new \DateTime();
        $this->disponibilites = new ArrayCollection();
        $this->reservations   = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(string $prenom): static { $this->prenom = $prenom; return $this; }

    public function getNomComplet(): string { return $this->prenom . ' ' . $this->nom; }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(string $telephone): static { $this->telephone = $telephone; return $this; }

    public function getVoiture(): ?string { return $this->voiture; }
    public function setVoiture(string $voiture): static { $this->voiture = $voiture; return $this; }

    public function getPhoto(): ?string { return $this->photo; }
    public function setPhoto(?string $photo): static { $this->photo = $photo; return $this; }

    public function getPrixTransfert(): ?string { return $this->prixTransfert; }
    public function setPrixTransfert(string $prixTransfert): static { $this->prixTransfert = $prixTransfert; return $this; }

    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $actif): static { $this->actif = $actif; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getDisponibilites(): Collection { return $this->disponibilites; }
    public function addDisponibilite(DisponibiliteChauffeur $d): static
    {
        if (!$this->disponibilites->contains($d)) {
            $this->disponibilites->add($d);
            $d->setChauffeur($this);
        }
        return $this;
    }
    public function removeDisponibilite(DisponibiliteChauffeur $d): static
    {
        if ($this->disponibilites->removeElement($d) && $d->getChauffeur() === $this) {
            $d->setChauffeur(null);
        }
        return $this;
    }

    public function getReservations(): Collection { return $this->reservations; }

    public function __toString(): string { return $this->getNomComplet(); }
}
