<?php

namespace App\Controller\Api;

use App\Entity\Voyage;
use App\Repository\HotelRepository;
use App\Repository\SocieteRepository;
use App\Repository\VoyageRepository;
use App\Service\Api\VoyagePresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API JSON publique, en lecture seule, du catalogue DreamTravel.
 * Seuls les voyages actifs et à venir sont listés (comme sur le site).
 */
#[Route('/api', format: 'json')]
class CatalogueApiController extends AbstractController
{
    private const FILTRES = ['destination', 'societe', 'budget', 'date_depart', 'personnes'];

    public function __construct(private readonly VoyagePresenter $presenter) {}

    /**
     * GET /api/voyages?destination=&societe=&budget=&date_depart=AAAA-MM-JJ&personnes=
     */
    #[Route('/voyages', name: 'api_voyages', methods: ['GET'])]
    public function voyages(Request $request, VoyageRepository $repo): JsonResponse
    {
        $q = $request->query;
        $erreurs = [];

        $budget = null;
        if ($q->has('budget') && $q->get('budget') !== '') {
            is_numeric($q->get('budget')) && (float) $q->get('budget') > 0
                ? $budget = (float) $q->get('budget')
                : $erreurs['budget'] = 'Nombre positif attendu (euros par personne).';
        }

        $date = null;
        if ($q->get('date_depart')) {
            $date = \DateTime::createFromFormat('!Y-m-d', (string) $q->get('date_depart'));
            if (!$date || $date->format('Y-m-d') !== $q->get('date_depart')) {
                $erreurs['date_depart'] = 'Format attendu : AAAA-MM-JJ.';
            }
        }

        $personnes = null;
        if ($q->has('personnes') && $q->get('personnes') !== '') {
            ctype_digit((string) $q->get('personnes')) && (int) $q->get('personnes') >= 1
                ? $personnes = (int) $q->get('personnes')
                : $erreurs['personnes'] = 'Entier supérieur ou égal à 1 attendu.';
        }

        if ($erreurs) {
            return $this->erreur('Paramètres invalides.', 400, $erreurs);
        }

        $voyages = $repo->search(
            self::texte($q->get('destination')),
            $date ?: null,
            $budget,
            self::texte($q->get('societe')),
        );
        if ($personnes) {
            $voyages = array_filter($voyages, fn(Voyage $v) => $v->getPlacesRestantes() >= $personnes);
        }
        $voyages = array_values($voyages);

        return $this->json([
            'total'   => count($voyages),
            'filtres' => array_filter(array_intersect_key($q->all(), array_flip(self::FILTRES)), fn($v) => $v !== ''),
            'voyages' => array_map(fn(Voyage $v) => $this->presenter->resume($v), $voyages),
        ]);
    }

    /** GET /api/voyages/{id} : détail d'un voyage, avec les hôtels à destination. */
    #[Route('/voyages/{id}', name: 'api_voyage', methods: ['GET'])]
    public function voyage(string $id, VoyageRepository $repo, HotelRepository $hotels): JsonResponse
    {
        $voyage = ctype_digit($id) ? $repo->find((int) $id) : null;
        if (!$voyage) {
            return $this->erreur(sprintf('Voyage %s introuvable.', $id), 404);
        }

        return $this->json($this->presenter->detail($voyage, $hotels->findByDestination($voyage->getDestination()->getId())));
    }

    /** GET /api/societes : sociétés de voyage et nombre de voyages proposés. */
    #[Route('/societes', name: 'api_societes', methods: ['GET'])]
    public function societes(SocieteRepository $societes, VoyageRepository $voyages): JsonResponse
    {
        $parSociete = [];
        foreach ($voyages->findActifs() as $v) {
            if ($v->getSociete()) {
                $parSociete[$v->getSociete()->getId()] = ($parSociete[$v->getSociete()->getId()] ?? 0) + 1;
            }
        }

        $liste = array_map(fn($s) => $this->presenter->societe($s, $parSociete[$s->getId()] ?? 0), $societes->findAllOrdered());

        return $this->json(['total' => count($liste), 'societes' => $liste]);
    }

    private function erreur(string $message, int $statut, array $details = []): JsonResponse
    {
        return $this->json(array_filter(['erreur' => $message, 'statut' => $statut, 'details' => $details]), $statut);
    }

    private static function texte(mixed $valeur): ?string
    {
        $valeur = is_string($valeur) ? trim(mb_substr($valeur, 0, 100)) : '';
        return $valeur !== '' ? $valeur : null;
    }
}
