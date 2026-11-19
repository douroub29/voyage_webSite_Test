<?php

namespace App\Service\IA;

use App\Entity\Client;
use App\Entity\Reservation;
use App\Entity\Voyage;
use App\Repository\DestinationRepository;
use App\Repository\HotelRepository;
use App\Repository\SocieteRepository;
use App\Repository\VoyageRepository;
use App\Service\VoucherManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Outils mis à disposition du chatbot. Ils ne font QUE lire la base de données :
 * c'est la seule source d'information du modèle (pas de voyage ni de prix inventé).
 * Aucune donnée personnelle (email, téléphone, adresse) n'est renvoyée.
 */
class OutilsCatalogue
{
    private const MAX_RESULTATS = 10;

    public function __construct(
        private readonly VoyageRepository $voyageRepository,
        private readonly DestinationRepository $destinationRepository,
        private readonly SocieteRepository $societeRepository,
        private readonly HotelRepository $hotelRepository,
        private readonly VoucherManager $voucherManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    /** Définitions neutres des outils (nom, description, schéma JSON "parameters" optionnel). */
    public function definitions(bool $clientConnecte): array
    {
        $outils = [
            [
                'name'        => 'rechercher_voyages',
                'description' => 'Recherche les voyages actuellement proposés à la réservation (actifs, départ futur). '
                    . 'Tous les critères sont optionnels ; sans critère, renvoie tout le catalogue. '
                    . 'Renvoie pour chaque voyage : id, titre, destination, société, dates, durée, prix par personne, places restantes.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'destination'     => ['type' => 'string', 'description' => 'Ville, pays ou mot du titre (ex. "Maroc", "Paris").'],
                        'societe'         => ['type' => 'string', 'description' => 'Nom (ou partie du nom) de la société de voyage.'],
                        'budget_max'      => ['type' => 'number', 'description' => 'Prix maximum par personne en euros.'],
                        'date_depart_min' => ['type' => 'string', 'description' => 'Départ à partir de cette date, format AAAA-MM-JJ.'],
                        'personnes'       => ['type' => 'integer', 'description' => 'Nombre de voyageurs : ne garde que les voyages ayant assez de places.'],
                    ],
                ],
            ],
            [
                'name'        => 'details_voyage',
                'description' => 'Détails complets d\'un voyage par son id : description, vol, places restantes, '
                    . 'raison s\'il n\'est pas réservable, hôtels disponibles à destination avec leur prix par nuit.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => ['voyage_id' => ['type' => 'integer', 'description' => 'Identifiant du voyage.']],
                    'required'   => ['voyage_id'],
                ],
            ],
            [
                'name'        => 'lister_destinations_et_societes',
                'description' => 'Liste des destinations et des sociétés de voyage présentes dans le catalogue.',
            ],
            [
                'name'        => 'programme_fidelite',
                'description' => 'Règles du programme voucher fidélité (seuil, remise, fonctionnement)'
                    . ($clientConnecte ? ' et situation du client connecté (réservations confirmées, voucher disponible).' : '.'),
            ],
        ];

        if ($clientConnecte) {
            $outils[] = [
                'name'        => 'mes_reservations',
                'description' => 'Réservations du client connecté : référence, voyage, dates, nombre de personnes, statut, prix payé, voucher appliqué.',
            ];
        }

        return $outils;
    }

    /** Exécute un outil et renvoie le résultat en JSON (chaîne). */
    public function executer(string $nom, array $entree, ?Client $client): string
    {
        $resultat = match ($nom) {
            'rechercher_voyages'              => $this->rechercherVoyages($entree),
            'details_voyage'                  => $this->detailsVoyage((int) ($entree['voyage_id'] ?? 0)),
            'lister_destinations_et_societes' => $this->listerDestinationsEtSocietes(),
            'programme_fidelite'              => $this->programmeFidelite($client),
            'mes_reservations'                => $client ? $this->mesReservations($client) : ['erreur' => 'Client non connecté.'],
            default                           => ['erreur' => 'Outil inconnu : ' . $nom],
        };

        return json_encode($resultat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function rechercherVoyages(array $e): array
    {
        $date = null;
        if (!empty($e['date_depart_min']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $e['date_depart_min'])) {
            $date = \DateTime::createFromFormat('!Y-m-d', $e['date_depart_min']) ?: null;
        }
        $budget = isset($e['budget_max']) && is_numeric($e['budget_max']) ? (float) $e['budget_max'] : null;

        $voyages = $this->voyageRepository->search(
            self::texte($e['destination'] ?? null),
            $date,
            $budget,
            self::texte($e['societe'] ?? null),
        );

        $personnes = isset($e['personnes']) ? max(1, (int) $e['personnes']) : null;
        if ($personnes) {
            $voyages = array_filter($voyages, fn(Voyage $v) => $v->getPlacesRestantes() >= $personnes);
        }

        $voyages = array_values($voyages);
        return [
            'nombre_total' => count($voyages),
            'voyages'      => array_map(fn(Voyage $v) => $this->resume($v), array_slice($voyages, 0, self::MAX_RESULTATS)),
        ];
    }

    private function detailsVoyage(int $id): array
    {
        $voyage = $this->voyageRepository->find($id);
        if (!$voyage) {
            return ['erreur' => 'Aucun voyage avec l\'id ' . $id . '.'];
        }

        return $this->resume($voyage) + [
            'description'          => $voyage->getDescription(),
            'compagnie_aerienne'   => $voyage->getCompagnieAerienne()?->label(),
            'numero_vol'           => $voyage->getNumeroVol(),
            'reservable'           => $voyage->isReservable(),
            'motif_non_reservable' => $voyage->getMotifNonReservable(),
            'hotels_a_destination' => array_map(fn($h) => [
                'nom'           => $h->getNom(),
                'etoiles'       => $h->getEtoiles(),
                'prix_par_nuit' => (float) $h->getPrixParNuit(),
            ], $this->hotelRepository->findByDestination($voyage->getDestination()->getId())),
            'regles' => 'Le nombre de nuits d\'hôtel est égal à la durée du voyage ; le client choisit seulement le nombre de personnes, l\'hôtel et le transfert (optionnels).',
        ];
    }

    private function listerDestinationsEtSocietes(): array
    {
        return [
            'destinations' => array_map(fn($d) => $d->getNom() . ' (' . $d->getPays() . ')', $this->destinationRepository->findAll()),
            'societes'     => array_map(fn($s) => $s->getNom(), $this->societeRepository->findAllOrdered()),
        ];
    }

    private function programmeFidelite(?Client $client): array
    {
        $regles = [
            'seuil'       => sprintf('1 voucher gagné toutes les %d réservations confirmées (payées).', VoucherManager::SEUIL_RESERVATIONS),
            'remise'      => sprintf('-%d %% sur le prix total de la réservation suivante.', VoucherManager::POURCENTAGE_REMISE),
            'obtention'   => 'Bouton "Télécharger voucher" dans Mon compte / Mes réservations, visible seulement une fois le seuil atteint.',
            'application' => 'Automatique à la prochaine réservation, rien à saisir. Si cette réservation est annulée, le voucher redevient disponible.',
        ];

        if (!$client) {
            return ['regles' => $regles, 'client' => 'Visiteur non connecté : il doit se connecter pour voir sa situation.'];
        }

        $disponible = $this->voucherManager->getVoucherDisponible($client);
        return [
            'regles' => $regles,
            'situation_du_client' => [
                'reservations_confirmees'          => $client->getNombreReservationsConfirmees(),
                'eligible'                         => $this->voucherManager->isEligible($client),
                'reservations_manquantes_prochain' => $this->voucherManager->getReservationsManquantes($client),
                'voucher_disponible'               => $disponible ? ['code' => $disponible->getCode(), 'remise' => $disponible->getPourcentage() . ' %'] : null,
            ],
        ];
    }

    private function mesReservations(Client $client): array
    {
        return array_map(fn(Reservation $r) => [
            'reference' => $r->getReference(),
            'voyage'    => $r->getVoyage()->getTitre(),
            'depart'    => $r->getVoyage()->getDateDepart()->format('Y-m-d'),
            'retour'    => $r->getVoyage()->getDateRetour()->format('Y-m-d'),
            'personnes' => $r->getNombrePersonnes(),
            'statut'    => $r->getStatut()->label(),
            'prix_total'=> (float) $r->getPrixTotal(),
            'voucher'   => $r->getVoucher() ? '-' . $r->getVoucher()->getPourcentage() . ' %' : null,
        ], $client->getReservations()->toArray());
    }

    /** Résumé d'un voyage commun aux outils (et aux recommandations). */
    public function resume(Voyage $v): array
    {
        return [
            'id'                 => $v->getId(),
            'titre'              => $v->getTitre(),
            'destination'        => $v->getDestination()->getNom() . ', ' . $v->getDestination()->getPays(),
            'societe'            => $v->getSociete()?->getNom(),
            'depart'             => $v->getDateDepart()->format('Y-m-d'),
            'retour'             => $v->getDateRetour()->format('Y-m-d'),
            'duree_nuits'        => $v->getDureeJours(),
            'prix_par_personne'  => (float) $v->getPrix(),
            'places_restantes'   => $v->getPlacesRestantes(),
            'lien'               => $this->urlGenerator->generate('app_voyage_show', ['id' => $v->getId()]),
        ];
    }

    private static function texte(mixed $valeur): ?string
    {
        $valeur = is_string($valeur) ? trim(mb_substr($valeur, 0, 100)) : '';
        return $valeur !== '' ? $valeur : null;
    }
}
