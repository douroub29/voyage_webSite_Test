<?php

namespace App\Service\IA;

use App\Entity\Hotel;
use App\Entity\Voyage;
use App\Repository\HotelRepository;
use App\Repository\VoyageRepository;
use Psr\Log\LoggerInterface;

/**
 * Planificateur par « ambiance » : le client décrit l'envie de voyage en langage naturel
 * (« calme, bord de mer, octobre, bonne cuisine, moins de 3 h de vol, 800 € »).
 * Le modèle d'IA choisit le voyage (vol inclus) et l'hôtel du catalogue les plus adaptés,
 * puis rédige un programme jour par jour que le client réorganise ensuite par glisser-déposer.
 *
 * Garde-fous : seuls des voyages réservables et des hôtels de la même destination peuvent être choisis ;
 * sans IA (pas de clé, quota, erreur), un calcul par mots-clés prend le relais avec un programme type.
 */
class PlanificateurAmbiance
{
    public const LONGUEUR_MAX   = 500;
    private const JOURS_MAX     = 15;
    private const ACTIVITES_MAX = 5;

    private const CONSIGNE = <<<'TXT'
        Tu es le concepteur de voyages de DreamTravel. Les vols partent de Tunis.
        Le client décrit l'ambiance du voyage qu'il souhaite. Choisis LE voyage du catalogue fourni qui correspond le mieux
        (dates, durée de vol "vol_depuis_tunis_h", budget par personne, style de destination), et l'hôtel de cette destination
        le plus adapté (ou null si aucun ne convient ou si le budget ne le permet pas).
        Budget estimé par personne = prix_par_personne du voyage + prix_par_nuit de l'hôtel × duree_nuits.
        "contraintes_detectees" reprend le budget maximum par personne, le mois et la durée de vol maximale demandés :
        respecte-les en priorité (choisis un hôtel moins cher, ou aucun hôtel, pour tenir le budget ; "budget_par_personne"
        est déjà calculé pour chaque hôtel). Une destination sans mer ne convient pas à une envie de mer ou de plage.
        Sois honnête : n'affirme jamais qu'une contrainte est respectée si elle ne l'est pas, et explique tout écart dans "compromis".
        Rédige ensuite un programme jour par jour pour toute la durée du séjour (jour 1 = arrivée, dernier jour = retour),
        avec 2 à 4 activités concrètes et réalistes par jour, adaptées à l'ambiance demandée (chaque activité : 12 mots maximum).
        Propose aussi jusqu'à 2 voyages alternatifs du catalogue.
        Tout en français, en vouvoyant le client. N'utilise que les "id" fournis.
        Format :
        {"voyage_id": 1, "hotel_id": 2, "correspondance": 85, "pourquoi": "2 phrases maximum", "compromis": "ou chaîne vide",
         "programme": [{"jour": 1, "titre": "...", "activites": ["...", "..."]}],
         "alternatives": [{"voyage_id": 3, "raison": "une phrase"}]}
        TXT;

    /** Mots-clés de l'envie → mots à chercher dans la description de la destination (plan B sans IA). */
    private const AMBIANCES = [
        'mer'          => ['plage', 'mer', 'île', 'sable', 'océan', 'lagon'],
        'plage'        => ['plage', 'mer', 'île', 'sable'],
        'calme'        => ['sérénité', 'calme', 'paradis', 'riad', 'palmeraie', 'île'],
        'détente'      => ['sérénité', 'paradis', 'riad', 'spa'],
        'gastronomie'  => ['gastronomie', 'saveurs', 'cuisine'],
        'culture'      => ['civilisation', 'temples', 'colisée', 'musée', 'culturel', 'souks'],
        'histoire'     => ['civilisation', 'éternelle', 'colisée', 'temples'],
        'romantique'   => ['amour', 'romantique', 'lumière'],
        'luxe'         => ['luxe', 'palace', 'dorés'],
        'shopping'     => ['souks', 'champs-élysées', 'manhattan', 'dorés'],
        'nature'       => ['rizières', 'palmeraies', 'park', 'tropical'],
        'ville'        => ['ville', 'métropole', 'manhattan', 'capitale'],
        'exotique'     => ['tropical', 'île', 'mille et une', 'temples'],
    ];

    private const MOIS = [
        'janvier' => 1, 'février' => 2, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6,
        'juillet' => 7, 'août' => 8, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'décembre' => 12, 'decembre' => 12,
    ];

    public function __construct(
        private readonly VoyageRepository $voyageRepository,
        private readonly HotelRepository $hotelRepository,
        private readonly FournisseurIA $ia,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return ?array{
     *     voyage: Voyage, hotel: ?Hotel, correspondance: int, pourquoi: string, compromis: string,
     *     programme: list<array{jour: int, titre: string, activites: list<string>}>,
     *     alternatives: list<array{voyage: Voyage, raison: string}>,
     *     budget_par_personne: float, ia: bool
     * } null si aucun voyage n'est réservable
     */
    public function planifier(string $envie): ?array
    {
        $envie     = mb_substr(trim($envie), 0, self::LONGUEUR_MAX);
        $candidats = $this->candidats();
        if (!$candidats) {
            return null;
        }
        $hotels = $this->hotelsParDestination();

        $plan = null;
        if ($this->ia->isDisponible()) {
            $plan = $this->planifierAvecIA($envie, $candidats, $hotels);
        }
        $plan ??= $this->planifierSansIA($envie, $candidats, $hotels);

        $plan['budget_par_personne'] = self::budget($plan['voyage'], $plan['hotel']);
        return $plan;
    }

    /**
     * @param array<int, Voyage> $candidats
     * @param array<int, list<Hotel>> $hotels par id de destination
     */
    private function planifierAvecIA(string $envie, array $candidats, array $hotels): ?array
    {
        $contraintes = self::contraintes($envie);

        // L'IA ne voit que les voyages compatibles avec les contraintes chiffrées (budget sans hôtel, mois, vol),
        // sauf si aucun ne l'est : elle choisit alors le meilleur compromis dans tout le catalogue.
        $tousCandidats = $candidats;
        $compatibles   = array_filter($candidats, fn(Voyage $v) => !self::ecarts($v, null, $contraintes));
        if ($compatibles) {
            $candidats = $compatibles;
        }

        $catalogue = array_values(array_map(fn(Voyage $v) => [
            'id'                => $v->getId(),
            'titre'             => $v->getTitre(),
            'destination'       => $v->getDestination()->getNom() . ', ' . $v->getDestination()->getPays(),
            'description'       => mb_substr((string) $v->getDestination()->getDescription(), 0, 300),
            'depart'            => $v->getDateDepart()->format('Y-m-d'),
            'retour'            => $v->getDateRetour()->format('Y-m-d'),
            'duree_nuits'       => $v->getDureeJours(),
            'prix_par_personne' => (float) $v->getPrix(),
            'compagnie'         => $v->getCompagnieAerienne()?->label(),
            'vol_depuis_tunis_h'=> $v->getDestination()->getDureeVolHeures(),
            'places_restantes'  => $v->getPlacesRestantes(),
            'budget_sans_hotel' => self::budget($v, null),
            'hotels'            => array_map(fn(Hotel $h) => [
                'id'                  => $h->getId(),
                'nom'                 => $h->getNom(),
                'etoiles'             => $h->getEtoiles(),
                'prix_par_nuit'       => (float) $h->getPrixParNuit(),
                'budget_par_personne' => self::budget($v, $h),
                'description'         => mb_substr((string) $h->getDescription(), 0, 150),
            ], $hotels[$v->getDestination()->getId()] ?? []),
        ], $candidats));

        try {
            $json = $this->ia->completerJson(self::CONSIGNE, json_encode([
                'envie_du_client' => $envie,
                'contraintes_detectees' => array_filter([
                    'budget_max_par_personne' => $contraintes['budget'],
                    'mois'                    => $contraintes['mois'] ? self::nomMois($contraintes['mois']) : null,
                    'vol_max_heures'          => $contraintes['vol_max'],
                ], fn($c) => $c !== null),
                'date_du_jour'    => (new \DateTimeImmutable())->format('Y-m-d'),
                'catalogue'       => $catalogue,
            ], JSON_UNESCAPED_UNICODE));
        } catch (ErreurIA $e) {
            $this->logger->error(sprintf('Planificateur IA (%s) : %s', $this->ia->nom(), $e->getMessage()));
            return null;
        }

        $voyage = $candidats[(int) ($json['voyage_id'] ?? 0)] ?? null;
        if (!$json || !$voyage) {
            return null;
        }

        // L'hôtel doit appartenir à la destination du voyage.
        $hotel = null;
        foreach ($hotels[$voyage->getDestination()->getId()] ?? [] as $h) {
            if ($h->getId() === (int) ($json['hotel_id'] ?? 0)) {
                $hotel = $h;
            }
        }

        $alternatives = [];
        foreach ((array) ($json['alternatives'] ?? []) as $a) {
            $alt = is_array($a) ? ($candidats[(int) ($a['voyage_id'] ?? 0)] ?? null) : null;
            if ($alt && $alt !== $voyage && !isset($alternatives[$alt->getId()])) {
                $alternatives[$alt->getId()] = ['voyage' => $alt, 'raison' => self::texte($a['raison'] ?? '', 200)];
            }
        }

        $programme = $this->nettoyerProgramme((array) ($json['programme'] ?? []), $voyage);

        // Vérification des contraintes chiffrées : l'IA ne peut pas affirmer un budget ou un vol qui ne tiennent pas.
        $budgetMax = $contraintes['budget'];
        if ($budgetMax !== null && self::budget($voyage, $hotel) > $budgetMax) {
            $hotel = self::hotelDansBudget($hotels[$voyage->getDestination()->getId()] ?? [], $voyage, $budgetMax);
        }
        $ecarts = self::ecarts($voyage, $hotel, $contraintes);
        $compromis = self::texte($json['compromis'] ?? '', 300);
        if ($ecarts) {
            $compromis = trim(ucfirst(implode(' ; ', $ecarts)) . '. ' . $compromis);
        }

        // Explication incohérente (elle parle d'une autre destination du catalogue) : remplacée par un texte exact.
        $pourquoi = self::texte($json['pourquoi'] ?? '', 400);
        $autres   = array_unique(array_filter(array_map(
            fn(Voyage $v) => $v->getDestination() !== $voyage->getDestination() ? $v->getDestination()->getNom() : null,
            $tousCandidats
        )));
        foreach ($autres as $nom) {
            if (mb_stripos($pourquoi, $nom) !== false) {
                $pourquoi = '';
                break;
            }
        }
        if ($pourquoi === '') {
            $pourquoi = $ecarts
                ? sprintf('%s est la proposition la plus proche de votre envie dans notre catalogue actuel.', $voyage->getDestination()->getNom())
                : sprintf('%s correspond à votre envie et respecte vos critères.', $voyage->getDestination()->getNom());
        }

        return [
            'voyage'         => $voyage,
            'hotel'          => $hotel,
            'correspondance' => min($ecarts ? 60 : 100, max(0, (int) ($json['correspondance'] ?? 0))),
            'pourquoi'       => $pourquoi,
            'compromis'      => mb_substr($compromis, 0, 400),
            'programme'      => $programme ?: $this->programmeType($voyage, $hotel),
            'alternatives'   => array_slice(array_values($alternatives), 0, 2),
            'ia'             => true,
        ];
    }

    /** Programme renvoyé par l'IA, limité à la durée du séjour et débarrassé des entrées invalides. */
    private function nettoyerProgramme(array $brut, Voyage $voyage): array
    {
        $nbJours   = min(self::JOURS_MAX, $voyage->getDureeJours() + 1);
        $programme = [];
        foreach ($brut as $jour) {
            if (!is_array($jour) || count($programme) >= $nbJours) {
                continue;
            }
            $activites = array_values(array_filter(array_map(
                fn($a) => self::texte(is_string($a) ? $a : '', 120),
                array_slice((array) ($jour['activites'] ?? []), 0, self::ACTIVITES_MAX)
            )));
            $programme[] = [
                'jour'      => count($programme) + 1,
                'titre'     => self::texte($jour['titre'] ?? '', 80) ?: 'Jour ' . (count($programme) + 1),
                'activites' => $activites,
            ];
        }
        return $programme;
    }

    /**
     * Plan B sans IA : score par mots-clés (ambiance), mois, budget et durée de vol.
     *
     * @param array<int, Voyage> $candidats
     * @param array<int, list<Hotel>> $hotels
     */
    public function planifierSansIA(string $envie, array $candidats, array $hotels): array
    {
        $texte   = mb_strtolower($envie);
        ['budget' => $budget, 'mois' => $mois, 'vol_max' => $volMax] = self::contraintes($envie);

        $scores = [];
        foreach ($candidats as $id => $v) {
            $score   = 0;
            $raisons = [];
            $compromis = [];
            $description = mb_strtolower($v->getDestination()->getDescription() . ' ' . $v->getTitre());

            foreach (self::AMBIANCES as $cle => $mots) {
                if (self::contientMot($texte, $cle)) {
                    foreach ($mots as $mot) {
                        if (self::contientMot($description, $mot)) {
                            $score += 2; $raisons[] = $cle; break;
                        }
                    }
                }
            }
            if ($mois !== null) {
                if ((int) $v->getDateDepart()->format('n') === $mois) {
                    $score += 3; $raisons[] = 'le mois souhaité';
                } else {
                    $compromis[] = 'départ en ' . self::nomMois((int) $v->getDateDepart()->format('n')) . ' (au lieu du mois demandé)';
                }
            }
            $hotel = self::hotelDansBudget($hotels[$v->getDestination()->getId()] ?? [], $v, $budget);
            if ($budget !== null) {
                if (self::budget($v, $hotel) <= $budget) {
                    $score += 3; $raisons[] = 'votre budget';
                } else {
                    $score -= 2; $compromis[] = sprintf('budget dépassé (%s € par personne)', number_format(self::budget($v, $hotel), 0, ',', ' '));
                }
            }
            $vol = $v->getDestination()->getDureeVolHeures();
            if ($volMax !== null && $vol !== null) {
                if ($vol <= $volMax) {
                    $score += 2; $raisons[] = sprintf('un vol court (~%s h)', number_format($vol, 1, ',', ''));
                } else {
                    $score -= 2; $compromis[] = sprintf('vol plus long (~%s h)', number_format($vol, 1, ',', ''));
                }
            }
            $scores[$id] = [$score, array_unique($raisons), $compromis, $hotel];
        }
        uasort($scores, fn($a, $b) => $b[0] <=> $a[0]);

        $ids    = array_keys($scores);
        $voyage = $candidats[$ids[0]];
        [$score, $raisons, $compromis, $hotel] = $scores[$ids[0]];

        return [
            'voyage'         => $voyage,
            'hotel'          => $hotel,
            'correspondance' => max(20, min(90, 40 + $score * 6)),
            'pourquoi'       => $raisons
                ? sprintf('%s correspond à votre envie : %s.', $voyage->getDestination()->getNom(), implode(', ', $raisons))
                : sprintf('%s est notre prochain départ disponible.', $voyage->getDestination()->getNom()),
            'compromis'      => $compromis ? ucfirst(implode(' ; ', $compromis)) . '.' : '',
            'programme'      => $this->programmeType($voyage, $hotel),
            'alternatives'   => array_map(fn($id) => [
                'voyage' => $candidats[$id],
                'raison' => $scores[$id][1] ? 'Aussi adapté pour : ' . implode(', ', $scores[$id][1]) . '.' : 'Autre départ disponible.',
            ], array_slice($ids, 1, 2)),
            'ia'             => false,
        ];
    }

    /** Programme générique modifiable (utilisé sans IA). */
    private function programmeType(Voyage $voyage, ?Hotel $hotel): array
    {
        $dest    = $voyage->getDestination()->getNom();
        $nbJours = min(self::JOURS_MAX, $voyage->getDureeJours() + 1);
        $idees   = [
            ['Visite guidée du centre historique', 'Déjeuner dans une adresse locale', 'Coucher de soleil sur un point de vue'],
            ['Marché local et dégustation de spécialités', 'Musée ou site emblématique', 'Dîner typique'],
            ['Matinée libre et détente', 'Balade dans un quartier authentique', 'Cours de cuisine locale'],
            ['Excursion à la journée dans les environs', 'Pique-nique', 'Soirée libre'],
            ['Shopping et artisanat', 'Pause café en terrasse', 'Spectacle ou sortie culturelle'],
        ];

        $programme = [[
            'jour'      => 1,
            'titre'     => 'Arrivée à ' . $dest,
            'activites' => array_values(array_filter([
                'Vol ' . ($voyage->getCompagnieAerienne()?->label() ?? '') . ($voyage->getNumeroVol() ? ' ' . $voyage->getNumeroVol() : '') . ' depuis Tunis',
                $hotel ? 'Installation à l\'hôtel ' . $hotel->getNom() : 'Installation à l\'hébergement',
                'Promenade de découverte et dîner de bienvenue',
            ])),
        ]];
        for ($j = 2; $j < $nbJours; $j++) {
            $programme[] = ['jour' => $j, 'titre' => 'Découverte de ' . $dest, 'activites' => $idees[($j - 2) % count($idees)]];
        }
        if ($nbJours > 1) {
            $programme[] = ['jour' => $nbJours, 'titre' => 'Retour', 'activites' => ['Derniers achats et souvenirs', 'Transfert vers l\'aéroport', 'Vol retour vers Tunis']];
        }
        return $programme;
    }

    /** @return array<int, Voyage> voyages réservables, indexés par id */
    private function candidats(): array
    {
        $candidats = [];
        foreach ($this->voyageRepository->findActifs() as $v) {
            if ($v->isReservable()) {
                $candidats[$v->getId()] = $v;
            }
        }
        return $candidats;
    }

    /** @return array<int, list<Hotel>> */
    private function hotelsParDestination(): array
    {
        $parDest = [];
        foreach ($this->hotelRepository->findActifs() as $h) {
            $parDest[$h->getDestination()->getId()][] = $h;
        }
        return $parDest;
    }

    /** Budget par personne : vol + séjour + hôtel (chambre) sur toute la durée. */
    public static function budget(Voyage $voyage, ?Hotel $hotel): float
    {
        return (float) $voyage->getPrix() + ($hotel ? (float) $hotel->getPrixParNuit() * $voyage->getDureeJours() : 0.0);
    }

    /** Meilleur hôtel (le plus étoilé) qui rentre dans le budget ; sans budget, le plus étoilé. */
    private static function hotelDansBudget(array $hotels, Voyage $voyage, ?float $budget): ?Hotel
    {
        usort($hotels, fn(Hotel $a, Hotel $b) => $b->getEtoiles() <=> $a->getEtoiles());
        foreach ($hotels as $h) {
            if ($budget === null || self::budget($voyage, $h) <= $budget) {
                return $h;
            }
        }
        return null;
    }

    /** @return array{budget: ?float, mois: ?int, vol_max: ?float} contraintes chiffrées lues dans l'envie */
    public static function contraintes(string $envie): array
    {
        $texte = mb_strtolower($envie);
        return [
            'budget'  => self::extraireBudget($texte),
            'mois'    => self::extraireMois($texte),
            'vol_max' => preg_match('/(\d+(?:[.,]\d+)?)\s*h(?:eures?)?\s*(?:de\s*)?vol/u', $texte, $m) ? (float) str_replace(',', '.', $m[1]) : null,
        ];
    }

    /** @return list<string> contraintes non respectées par la proposition */
    public static function ecarts(Voyage $voyage, ?Hotel $hotel, array $contraintes): array
    {
        $ecarts = [];
        if ($contraintes['budget'] !== null && ($b = self::budget($voyage, $hotel)) > $contraintes['budget']) {
            $ecarts[] = sprintf('budget dépassé : %s € par personne pour %s € demandés', number_format($b, 0, ',', ' '), number_format($contraintes['budget'], 0, ',', ' '));
        }
        if ($contraintes['mois'] !== null && (int) $voyage->getDateDepart()->format('n') !== $contraintes['mois']) {
            $ecarts[] = sprintf('départ en %s et non en %s', self::nomMois((int) $voyage->getDateDepart()->format('n')), self::nomMois($contraintes['mois']));
        }
        $vol = $voyage->getDestination()->getDureeVolHeures();
        if ($contraintes['vol_max'] !== null && $vol !== null && $vol > $contraintes['vol_max']) {
            $ecarts[] = sprintf("vol d'environ %s h (au-delà des %s h souhaitées)", number_format($vol, 1, ',', ''), number_format($contraintes['vol_max'], 0, ',', ''));
        }
        return $ecarts;
    }

    public static function extraireBudget(string $texte): ?float
    {
        if (preg_match('/(\d[\d\s.]*)\s*(?:€|euros?|eur\b)/u', $texte, $m)
            || preg_match('/budget[^\d]{0,20}(\d[\d\s.]*)/u', $texte, $m)) {
            $valeur = (float) preg_replace('/[\s.]/u', '', $m[1]);
            return $valeur > 0 ? $valeur : null;
        }
        return null;
    }

    public static function extraireMois(string $texte): ?int
    {
        foreach (self::MOIS as $nom => $numero) {
            if (preg_match('/\b' . $nom . '\b/u', $texte)) {
                return $numero;
            }
        }
        return null;
    }

    /** Mot entier (pluriel en -s/-x accepté) : « mer » ne doit pas être trouvé dans « palmeraies ». */
    public static function contientMot(string $texte, string $mot): bool
    {
        return (bool) preg_match('/(?<!\p{L})' . preg_quote($mot, '/') . '[sx]?(?!\p{L})/u', $texte);
    }

    private static function nomMois(int $numero): string
    {
        return array_search($numero, self::MOIS, true) ?: (string) $numero;
    }

    private static function texte(mixed $valeur, int $max): string
    {
        return is_string($valeur) ? trim(mb_substr(strip_tags($valeur), 0, $max)) : '';
    }
}
