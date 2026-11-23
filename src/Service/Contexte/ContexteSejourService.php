<?php

namespace App\Service\Contexte;

use App\Entity\Destination;
use App\Service\IA\ErreurIA;
use App\Service\IA\FournisseurIA;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ce qui attend le voyageur sur place pendant ses dates :
 *  - météo historique : moyennes des ANNEES_METEO dernières années aux mêmes dates (Open-Meteo, gratuit, sans clé) ;
 *  - jours fériés du pays (Nager.Date, gratuit, sans clé) ;
 *  - affluence touristique estimée (saison + jours fériés) ;
 *  - événements locaux récurrents proposés par l'IA (à vérifier), si une clé d'IA est configurée.
 * Chaque bloc est facultatif : une source indisponible n'empêche pas d'afficher les autres.
 * Résultats mis en cache (les données historiques changent peu).
 */
class ContexteSejourService
{
    private const URL_METEO  = 'https://archive-api.open-meteo.com/v1/archive';
    private const URL_FERIES = 'https://date.nager.at/api/v3/PublicHolidays/%d/%s';
    private const ANNEES_METEO = 5;
    private const DUREE_CACHE  = 30 * 86400;

    /** Indice de fréquentation touristique mondiale par mois (1 = pic estival). */
    private const SAISONNALITE = [1 => .40, .45, .55, .65, .70, .85, 1.0, 1.0, .70, .60, .40, .75];

    private const CONSIGNE_EVENEMENTS = <<<'TXT'
        Tu es un expert du tourisme. Liste au maximum 4 événements locaux récurrents et connus (festivals, fêtes
        religieuses ou traditionnelles, grandes manifestations culturelles ou sportives) qui ont lieu habituellement
        à la destination indiquée pendant la période donnée. N'invente rien : si tu n'es pas sûr, renvoie une liste vide.
        Pour chacun : nom, période habituelle (ex. "mi-décembre"), et une phrase d'impact pour le voyageur
        (ambiance, foule, fermetures), en français, 20 mots maximum.
        Format : {"evenements": [{"nom": "...", "periode": "...", "impact": "..."}]}
        TXT;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly FournisseurIA $ia,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return array{
     *     meteo: ?array{temp_max: float, temp_min: float, jours_pluie_pct: int, pluie_mm_jour: float, annees: int, verdict: string, icone: string},
     *     feries: list<array{date: \DateTimeImmutable, nom: string}>,
     *     feries_disponibles: bool,
     *     affluence: array{niveau: string, score: int, explication: string},
     *     evenements: list<array{nom: string, periode: string, impact: string}>,
     *     evenements_ia: bool
     * }
     */
    public function pour(Destination $destination, \DateTimeInterface $debut, \DateTimeInterface $fin): array
    {
        $debut = \DateTimeImmutable::createFromInterface($debut)->setTime(0, 0);
        $fin   = \DateTimeImmutable::createFromInterface($fin)->setTime(0, 0);
        if ($fin < $debut) {
            [$debut, $fin] = [$fin, $debut];
        }

        $meteo  = $destination->hasCoordonnees() ? $this->meteo($destination->getLatitude(), $destination->getLongitude(), $debut, $fin) : null;
        $feries = $destination->getCodePays() ? $this->feries($destination->getCodePays(), $debut, $fin) : null;
        $evenements = $this->evenements($destination, $debut, $fin);

        return [
            'meteo'              => $meteo,
            'feries'             => $feries ?? [],
            'feries_disponibles' => $feries !== null,
            'affluence'          => $this->affluence($debut, $fin, $feries ?? [], $meteo),
            'evenements'         => $evenements ?? [],
            'evenements_ia'      => $evenements !== null,
        ];
    }

    /** Moyennes météo des mêmes dates sur les dernières années. */
    private function meteo(float $lat, float $lon, \DateTimeImmutable $debut, \DateTimeImmutable $fin): ?array
    {
        $cle = sprintf('meteo_%s_%s_%s_%s', round($lat, 2), round($lon, 2), $debut->format('md'), $fin->format('md'));

        return $this->cache->get(str_replace(['.', '-'], ['_', 'm'], $cle), function (ItemInterface $item) use ($lat, $lon, $debut, $fin) {
            // Fenêtres [début, fin] décalées de 1 à N années dans le passé (archive disponible avec quelques jours de retard).
            $fenetres = [];
            $limite   = new \DateTimeImmutable('-7 days');
            for ($a = 1, $n = 0; $n < self::ANNEES_METEO && $a <= self::ANNEES_METEO + 2; $a++) {
                $d = $debut->modify("-$a years");
                $f = $fin->modify("-$a years");
                if ($f <= $limite) {
                    $fenetres[] = [$d->format('Y-m-d'), $f->format('Y-m-d')];
                    $n++;
                }
            }
            if (!$fenetres) {
                $item->expiresAfter(3600);
                return null;
            }

            try {
                $donnees = $this->httpClient->request('GET', self::URL_METEO, [
                    'query' => [
                        'latitude'   => $lat,
                        'longitude'  => $lon,
                        'start_date' => end($fenetres)[0],
                        'end_date'   => $fenetres[0][1],
                        'daily'      => 'temperature_2m_max,temperature_2m_min,precipitation_sum',
                        'timezone'   => 'auto',
                    ],
                    'timeout' => 15,
                ])->toArray();
            } catch (ExceptionInterface $e) {
                $this->logger->warning('Météo Open-Meteo indisponible : ' . $e->getMessage());
                $item->expiresAfter(600);
                return null;
            }

            $jours = $donnees['daily']['time'] ?? [];
            $tMax = $tMin = $pluie = [];
            foreach ($jours as $i => $jour) {
                $dansFenetre = false;
                foreach ($fenetres as [$d, $f]) {
                    if ($jour >= $d && $jour <= $f) { $dansFenetre = true; break; }
                }
                $max = $donnees['daily']['temperature_2m_max'][$i] ?? null;
                $min = $donnees['daily']['temperature_2m_min'][$i] ?? null;
                $pr  = $donnees['daily']['precipitation_sum'][$i] ?? null;
                if ($dansFenetre && $max !== null && $min !== null && $pr !== null) {
                    $tMax[] = $max; $tMin[] = $min; $pluie[] = $pr;
                }
            }
            if (!$tMax) {
                $item->expiresAfter(3600);
                return null;
            }

            $item->expiresAfter(self::DUREE_CACHE);
            return self::resumerMeteo($tMax, $tMin, $pluie, count($fenetres));
        });
    }

    /**
     * @param list<float> $tMax
     * @param list<float> $tMin
     * @param list<float> $pluie
     */
    public static function resumerMeteo(array $tMax, array $tMin, array $pluie, int $annees): array
    {
        $max        = round(array_sum($tMax) / count($tMax), 1);
        $min        = round(array_sum($tMin) / count($tMin), 1);
        $joursPluie = (int) round(100 * count(array_filter($pluie, fn($p) => $p >= 1.0)) / count($pluie));

        [$verdict, $icone] = match (true) {
            $joursPluie >= 50 => ['Saison humide : prévoyez des activités à l\'abri', 'fa-cloud-showers-heavy'],
            $max >= 33        => ['Très chaud : privilégiez les sorties tôt le matin', 'fa-temperature-high'],
            $max < 10         => ['Froid : tenue d\'hiver indispensable', 'fa-snowflake'],
            $max < 17         => ['Frais : prévoyez une veste', 'fa-cloud-sun'],
            $joursPluie >= 30 => ['Agréable, avec quelques averses', 'fa-cloud-sun-rain'],
            default           => ['Conditions idéales', 'fa-sun'],
        };

        return [
            'temp_max'        => $max,
            'temp_min'        => $min,
            'jours_pluie_pct' => $joursPluie,
            'pluie_mm_jour'   => round(array_sum($pluie) / count($pluie), 1),
            'annees'          => $annees,
            'verdict'         => $verdict,
            'icone'           => $icone,
        ];
    }

    /** @return ?list<array{date: \DateTimeImmutable, nom: string}> null si le pays n'est pas couvert */
    private function feries(string $codePays, \DateTimeImmutable $debut, \DateTimeImmutable $fin): ?array
    {
        $resultat = [];
        for ($annee = (int) $debut->format('Y'); $annee <= (int) $fin->format('Y'); $annee++) {
            $liste = $this->cache->get(sprintf('feries_%s_%d', $codePays, $annee), function (ItemInterface $item) use ($codePays, $annee) {
                try {
                    $reponse = $this->httpClient->request('GET', sprintf(self::URL_FERIES, $annee, $codePays), ['timeout' => 10]);
                    if ($reponse->getStatusCode() !== 200) {
                        $item->expiresAfter(self::DUREE_CACHE);
                        return false; // pays non couvert
                    }
                    $item->expiresAfter(self::DUREE_CACHE);
                    return array_map(fn(array $f) => ['date' => $f['date'], 'nom' => $f['localName'] ?: $f['name']], $reponse->toArray());
                } catch (ExceptionInterface $e) {
                    $this->logger->warning('Jours fériés Nager.Date indisponibles : ' . $e->getMessage());
                    $item->expiresAfter(600);
                    return false;
                }
            });
            if ($liste === false) {
                return null;
            }
            foreach ($liste as $f) {
                $date = new \DateTimeImmutable($f['date']);
                if ($date >= $debut && $date <= $fin) {
                    $resultat[] = ['date' => $date, 'nom' => $f['nom']];
                }
            }
        }
        return $resultat;
    }

    /**
     * Affluence estimée : saisonnalité du mois + jours fériés (tourisme local) – fortes pluies.
     *
     * @param list<array{date: \DateTimeImmutable, nom: string}> $feries
     */
    public function affluence(\DateTimeImmutable $debut, \DateTimeImmutable $fin, array $feries, ?array $meteo): array
    {
        $indices = [];
        for ($j = $debut; $j <= $fin; $j = $j->modify('+1 day')) {
            $indices[] = self::SAISONNALITE[(int) $j->format('n')];
        }
        $score   = array_sum($indices) / count($indices);
        $raisons = [$score >= .8 ? 'haute saison touristique' : ($score >= .6 ? 'moyenne saison' : 'basse saison')];

        if ($feries) {
            $score    += min(.2, .1 * count($feries));
            $raisons[] = count($feries) . ' jour(s) férié(s) local(aux)';
        }
        if ($meteo && $meteo['jours_pluie_pct'] >= 50) {
            $score    -= .1;
            $raisons[] = 'saison des pluies';
        }

        $score  = (int) round(max(0, min(1, $score)) * 100);
        $niveau = $score >= 75 ? 'Élevée' : ($score >= 50 ? 'Modérée' : 'Faible');

        return ['niveau' => $niveau, 'score' => $score, 'explication' => ucfirst(implode(', ', $raisons)) . '.'];
    }

    /** @return ?list<array{nom: string, periode: string, impact: string}> null sans IA disponible */
    private function evenements(Destination $destination, \DateTimeImmutable $debut, \DateTimeImmutable $fin): ?array
    {
        if (!$this->ia->isDisponible()) {
            return null;
        }

        $cle = sprintf('evenements_%d_%s_%s', $destination->getId() ?? 0, $debut->format('md'), $fin->format('md'));
        return $this->cache->get($cle, function (ItemInterface $item) use ($destination, $debut, $fin) {
            try {
                $json = $this->ia->completerJson(self::CONSIGNE_EVENEMENTS, json_encode([
                    'destination' => $destination->getNom() . ', ' . $destination->getPays(),
                    'du'          => $debut->format('j F'),
                    'au'          => $fin->format('j F'),
                ], JSON_UNESCAPED_UNICODE));
            } catch (ErreurIA $e) {
                $this->logger->error('Événements IA : ' . $e->getMessage());
                $item->expiresAfter(600);
                return null;
            }

            $item->expiresAfter(self::DUREE_CACHE);
            $liste = [];
            foreach (array_slice((array) ($json['evenements'] ?? []), 0, 4) as $e) {
                if (is_array($e) && trim((string) ($e['nom'] ?? '')) !== '') {
                    $liste[] = [
                        'nom'     => mb_substr(trim((string) $e['nom']), 0, 80),
                        'periode' => mb_substr(trim((string) ($e['periode'] ?? '')), 0, 50),
                        'impact'  => mb_substr(trim((string) ($e['impact'] ?? '')), 0, 160),
                    ];
                }
            }
            return $liste;
        });
    }
}
