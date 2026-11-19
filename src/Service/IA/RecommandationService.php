<?php

namespace App\Service\IA;

use App\Entity\Client;
use App\Entity\Reservation;
use App\Entity\Voyage;
use App\Enum\StatutReservation;
use App\Repository\VoyageRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Recommande des voyages à un client selon l'historique de ses réservations.
 * - Le modèle d'IA (Gemini gratuit ou Claude) choisit parmi les voyages réellement réservables (liste fournie) et explique son choix ;
 *   tout id renvoyé hors de cette liste est ignoré.
 * - Sans clé API, sans historique ou en cas d'erreur : calcul par score, sans IA.
 * - Résultat mis en cache 6 h par client (et invalidé si son historique ou le catalogue change).
 */
class RecommandationService
{
    private const NOMBRE = 3;
    private const DUREE_CACHE = 6 * 3600;

    private const CONSIGNE = <<<'TXT'
        Tu es le conseiller voyages de DreamTravel. À partir de l'historique de réservations du client,
        choisis au maximum %d voyages dans la liste des voyages disponibles, du plus pertinent au moins pertinent.
        Critères : goûts révélés par l'historique (type de destination, société appréciée, budget par personne,
        durée, taille du groupe), en évitant de reproposer une destination déjà faite sauf si le client y retourne souvent.
        Pour chaque voyage, écris une raison courte (une phrase, 20 mots maximum, en français, en vouvoyant le client)
        qui s'appuie sur son historique. N'utilise que les "id" de la liste fournie.
        Format de réponse : {"recommandations": [{"voyage_id": 3, "raison": "..."}]}
        TXT;

    public function __construct(
        private readonly VoyageRepository $voyageRepository,
        private readonly FournisseurIA $ia,
        private readonly OutilsCatalogue $outils,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return list<array{voyage: Voyage, raison: string, ia: bool}>
     */
    public function pourClient(Client $client): array
    {
        $historique = $this->historique($client);
        $candidats  = $this->candidats($client);
        if (!$candidats) {
            return [];
        }

        $cle = sprintf('reco_client_%d_%s', $client->getId(), md5(json_encode([
            array_map(fn(Reservation $r) => [$r->getId(), $r->getStatut()->value], $historique),
            array_keys($candidats),
        ])));

        /** @var list<array{id: int, raison: string, ia: bool}> $choix */
        $choix = $this->cache->get($cle, function (ItemInterface $item) use ($historique, $candidats) {
            $item->expiresAfter(self::DUREE_CACHE);
            return ($historique && $this->ia->isDisponible())
                ? ($this->choisirAvecIA($historique, $candidats) ?? $this->choisirParScore($historique, $candidats))
                : $this->choisirParScore($historique, $candidats);
        });

        // Les places ou le statut ont pu changer depuis la mise en cache.
        $resultat = [];
        foreach ($choix as $c) {
            $voyage = $candidats[$c['id']] ?? null;
            if ($voyage && $voyage->isReservable()) {
                $resultat[] = ['voyage' => $voyage, 'raison' => $c['raison'], 'ia' => $c['ia']];
            }
        }
        return $resultat;
    }

    /** @param array<int, Voyage> $candidats */
    private function choisirAvecIA(array $historique, array $candidats): ?array
    {
        $donnees = [
            'historique_du_client' => array_map(fn(Reservation $r) => [
                'voyage'            => $r->getVoyage()->getTitre(),
                'destination'       => $r->getVoyage()->getDestination()->getNom() . ', ' . $r->getVoyage()->getDestination()->getPays(),
                'societe'           => $r->getVoyage()->getSociete()?->getNom(),
                'prix_par_personne' => (float) $r->getVoyage()->getPrix(),
                'duree_nuits'       => $r->getVoyage()->getDureeJours(),
                'personnes'         => $r->getNombrePersonnes(),
                'statut'            => $r->getStatut()->label(),
            ], $historique),
            'voyages_disponibles' => array_values(array_map(
                fn(Voyage $v) => array_diff_key($this->outils->resume($v), ['lien' => true]),
                $candidats
            )),
        ];

        try {
            $json = $this->ia->completerJson(
                sprintf(self::CONSIGNE, self::NOMBRE),
                json_encode($donnees, JSON_UNESCAPED_UNICODE),
            );
        } catch (ErreurIA $e) {
            $this->logger->error(sprintf('Recommandations IA (%s) : %s', $this->ia->nom(), $e->getMessage()));
            return null;
        }

        $choix = [];
        foreach ((array) ($json['recommandations'] ?? []) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $id = (int) ($r['voyage_id'] ?? 0);
            // Garde-fou : uniquement des voyages réellement proposés, sans doublon.
            if (isset($candidats[$id]) && !isset($choix[$id])) {
                $choix[$id] = ['id' => $id, 'raison' => mb_substr(trim((string) ($r['raison'] ?? '')), 0, 200), 'ia' => true];
            }
        }

        return $choix ? array_slice(array_values($choix), 0, self::NOMBRE) : null;
    }

    /**
     * Recommandation sans IA : même société appréciée, budget et durée proches.
     *
     * @param array<int, Voyage> $candidats
     */
    private function choisirParScore(array $historique, array $candidats): array
    {
        if (!$historique) {
            return array_map(fn(Voyage $v) => [
                'id' => $v->getId(), 'raison' => 'Départ prochain, encore des places disponibles.', 'ia' => false,
            ], array_slice(array_values($candidats), 0, self::NOMBRE));
        }

        $societes   = array_count_values(array_filter(array_map(fn(Reservation $r) => $r->getVoyage()->getSociete()?->getNom(), $historique)));
        $prixMoyen  = array_sum(array_map(fn(Reservation $r) => (float) $r->getVoyage()->getPrix(), $historique)) / count($historique);
        $dureeMoy   = array_sum(array_map(fn(Reservation $r) => $r->getVoyage()->getDureeJours(), $historique)) / count($historique);

        $scores = [];
        foreach ($candidats as $id => $v) {
            $score = 0; $raisons = [];
            if ($v->getSociete() && isset($societes[$v->getSociete()->getNom()])) {
                $score += 3; $raisons[] = 'organisé par ' . $v->getSociete()->getNom() . ', société avec qui vous avez déjà voyagé';
            }
            if (abs((float) $v->getPrix() - $prixMoyen) <= 0.4 * $prixMoyen) {
                $score += 2; $raisons[] = 'dans votre budget habituel';
            }
            if (abs($v->getDureeJours() - $dureeMoy) <= 3) {
                $score += 1; $raisons[] = 'une durée proche de vos séjours précédents';
            }
            $scores[$id] = [$score, $raisons ? ucfirst(implode(', ', $raisons)) . '.' : 'Une nouvelle destination à découvrir.'];
        }
        uasort($scores, fn($a, $b) => $b[0] <=> $a[0]);

        return array_map(
            fn($id) => ['id' => $id, 'raison' => $scores[$id][1], 'ia' => false],
            array_slice(array_keys($scores), 0, self::NOMBRE)
        );
    }

    /** @return list<Reservation> réservations non annulées */
    private function historique(Client $client): array
    {
        return array_values($client->getReservations()
            ->filter(fn(Reservation $r) => $r->getStatut() !== StatutReservation::Annulee)
            ->toArray());
    }

    /** @return array<int, Voyage> voyages réservables que le client n'a pas déjà réservés, indexés par id */
    private function candidats(Client $client): array
    {
        $dejaReserves = array_map(fn(Reservation $r) => $r->getVoyage()->getId(), $this->historique($client));

        $candidats = [];
        foreach ($this->voyageRepository->findActifs() as $v) {
            if ($v->isReservable() && !in_array($v->getId(), $dejaReserves, true)) {
                $candidats[$v->getId()] = $v;
            }
        }
        return $candidats;
    }
}
