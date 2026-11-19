<?php

namespace App\Entity;

use App\Enum\StatutAvis;
use App\Repository\AvisRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AvisRepository::class)]
#[ORM\Table(name: 'avis')]
class Avis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(min: 1, max: 5)]
    private int $note = 5;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Veuillez rédiger votre avis.')]
    #[Assert\Length(min: 10, max: 1000, minMessage: 'Votre avis doit contenir au moins {{ limit }} caractères.', maxMessage: 'Votre avis ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $commentaire = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateAvis = null;

    #[ORM\Column(type: 'string', enumType: StatutAvis::class)]
    private StatutAvis $statut = StatutAvis::EnAttente;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'avis')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: Hotel::class, inversedBy: 'avis')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Hotel $hotel = null;

    #[ORM\ManyToOne(targetEntity: Chauffeur::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Chauffeur $chauffeur = null;

    public function __construct()
    {
        $this->dateAvis = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getNote(): int { return $this->note; }
    public function setNote(int $note): static { $this->note = $note; return $this; }

    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(string $commentaire): static { $this->commentaire = $commentaire; return $this; }

    public function getDateAvis(): ?\DateTimeInterface { return $this->dateAvis; }
    public function setDateAvis(\DateTimeInterface $dateAvis): static { $this->dateAvis = $dateAvis; return $this; }

    public function getStatut(): StatutAvis { return $this->statut; }
    public function setStatut(StatutAvis $statut): static { $this->statut = $statut; return $this; }

    public function getClient(): ?Client { return $this->client; }
    public function setClient(?Client $client): static { $this->client = $client; return $this; }

    public function getHotel(): ?Hotel { return $this->hotel; }
    public function setHotel(?Hotel $hotel): static { $this->hotel = $hotel; return $this; }

    public function getChauffeur(): ?Chauffeur { return $this->chauffeur; }
    public function setChauffeur(?Chauffeur $chauffeur): static { $this->chauffeur = $chauffeur; return $this; }
}
