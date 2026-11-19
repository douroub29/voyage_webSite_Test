<?php

namespace App\Service\Api;

use App\Entity\Hotel;
use App\Entity\Societe;
use App\Entity\Voyage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Représentation JSON publique des voyages (aucune donnée personnelle de client). */
class VoyagePresenter
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator) {}

    public function resume(Voyage $v): array
    {
        return [
            'id'               => $v->getId(),
            'titre'            => $v->getTitre(),
            'destination'      => ['nom' => $v->getDestination()->getNom(), 'pays' => $v->getDestination()->getPays()],
            'societe'          => $v->getSociete()?->getNom(),
            'dateDepart'       => $v->getDateDepart()->format('Y-m-d'),
            'dateRetour'       => $v->getDateRetour()->format('Y-m-d'),
            'dureeNuits'       => $v->getDureeJours(),
            'prixParPersonne'  => (float) $v->getPrix(),
            'devise'           => 'EUR',
            'placesTotal'      => $v->getNombrePlaces(),
            'placesRestantes'  => $v->getPlacesRestantes(),
            'reservable'       => $v->isReservable(),
            'photo'            => $v->getPhotoUrl() ? $this->absolue($v->getPhotoUrl()) : null,
            'url'              => $this->urlGenerator->generate('app_voyage_show', ['id' => $v->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }

    /** @param Hotel[] $hotels */
    public function detail(Voyage $v, array $hotels): array
    {
        return $this->resume($v) + [
            'description'        => $v->getDescription(),
            'compagnieAerienne'  => $v->getCompagnieAerienne()?->label(),
            'numeroVol'          => $v->getNumeroVol(),
            'motifNonReservable' => $v->getMotifNonReservable(),
            'hotels'             => array_map(fn(Hotel $h) => [
                'id'          => $h->getId(),
                'nom'         => $h->getNom(),
                'etoiles'     => $h->getEtoiles(),
                'prixParNuit' => (float) $h->getPrixParNuit(),
            ], $hotels),
        ];
    }

    public function societe(Societe $s, int $voyagesProposes): array
    {
        return ['id' => $s->getId(), 'nom' => $s->getNom(), 'voyagesProposes' => $voyagesProposes];
    }

    private function absolue(string $chemin): string
    {
        // URL absolue de l'accueil (schéma, hôte, port) + chemin de l'image
        return rtrim($this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL), '/') . $chemin;
    }
}
