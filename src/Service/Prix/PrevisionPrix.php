<?php

namespace App\Service\Prix;

use App\Entity\Voyage;
use App\Enum\StatutReservation;

/**
 * Prévision de l'évolution du prix d'un voyage sur les 7 prochains jours.
 *
 * Modèle de « yield management » simple et explicable (pas d'historique de prix en base) :
 *  - le remplissage projeté à 7 jours (places vendues + rythme des 14 derniers jours) pousse le prix à la hausse ;
 *  - l'approche du départ pousse aussi à la hausse… sauf si l'avion reste peu rempli :
 *    on anticipe alors une promotion de dernière minute (baisse).
 * Le résultat reste une estimation, affichée comme telle.
 */
class PrevisionPrix
{
    public const HORIZON_JOURS = 7;
    private const FENETRE_RYTHME = 14;

    /**
     * @return array{
     *     tendance: 'hausse'|'stable'|'baisse',
     *     variation_pct: float,
     *     prix_estime: float,
     *     probabilite_hausse: int,
     *     jours_avant_depart: int,
     *     remplissage_actuel: int,
     *     remplissage_projete: int,
     *     facteurs: list<string>,
     *     conseil: string
     * }
     */
    public function prevoir(Voyage $voyage, ?\DateTimeInterface $aujourdhui = null): array
    {
        $aujourdhui = \DateTimeImmutable::createFromInterface($aujourdhui ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $depart     = \DateTimeImmutable::createFromInterface($voyage->getDateDepart())->setTime(0, 0);
        $jours      = max(0, (int) $aujourdhui->diff($depart)->format('%r%a'));

        $total    = max(1, (int) $voyage->getNombrePlaces());
        $vendues  = $voyage->getPlacesReservees();
        $rythme   = $this->placesVenduesDepuis($voyage, $aujourdhui->modify('-' . self::FENETRE_RYTHME . ' days')) / self::FENETRE_RYTHME;

        $remplissage = min(1.0, $vendues / $total);
        $projete     = min(1.0, ($vendues + $rythme * min(self::HORIZON_JOURS, $jours)) / $total);
        $urgence     = max(0.0, min(1.0, 1 - $jours / 90));

        $facteurs = [];
        if ($jours <= 30 && $projete < 0.4) {
            // Avion peu rempli à l'approche du départ : promotions probables.
            $variation  = -(0.4 - $projete) * 20;
            $facteurs[] = sprintf('Départ dans %d jours avec seulement %d %% des places vendues : une promotion de dernière minute est possible.', $jours, round($projete * 100));
        } else {
            // Loin du départ, une demande faible ne fait pas baisser le prix : il reste stable.
            $variation = max(-1.0, ($projete * 0.7 + $urgence * 0.3 - 0.3) * 25);
            if ($projete >= 0.6) {
                $facteurs[] = sprintf('Forte demande : %d %% des places devraient être vendues d\'ici %d jours.', round($projete * 100), self::HORIZON_JOURS);
            }
            if ($urgence >= 0.5) {
                $facteurs[] = sprintf('Le départ approche (J-%d) : les prix montent habituellement sur cette période.', $jours);
            }
            if ($rythme >= 0.5) {
                $facteurs[] = sprintf('Réservations récentes soutenues (%s place(s) par jour en moyenne).', number_format($rythme, 1, ',', ' '));
            }
        }

        $variation = round(max(-10.0, min(15.0, $variation)), 1);
        $tendance  = $variation > 1.5 ? 'hausse' : ($variation < -1.5 ? 'baisse' : 'stable');
        if (!$facteurs) {
            $facteurs[] = 'Demande régulière, sans signe de tension sur les prix.';
        }

        return [
            'tendance'            => $tendance,
            'variation_pct'       => $variation,
            'prix_estime'         => round((float) $voyage->getPrix() * (1 + $variation / 100)),
            'probabilite_hausse'  => (int) round(100 / (1 + exp(-$variation / 3))),
            'jours_avant_depart'  => $jours,
            'remplissage_actuel'  => (int) round($remplissage * 100),
            'remplissage_projete' => (int) round($projete * 100),
            'facteurs'            => $facteurs,
            'conseil'             => match ($tendance) {
                'hausse' => 'Le prix risque de monter : geler le tarif actuel peut vous faire économiser.',
                'baisse' => 'Le prix pourrait baisser : inutile de geler, mais surveillez les places restantes.',
                default  => 'Prix plutôt stable : un gel vous laisse le temps de vous organiser sereinement.',
            },
        ];
    }

    private function placesVenduesDepuis(Voyage $voyage, \DateTimeInterface $depuis): int
    {
        $places = 0;
        foreach ($voyage->getReservations() as $r) {
            if ($r->getStatut() !== StatutReservation::Annulee && $r->getCreatedAt() >= $depuis) {
                $places += $r->getNombrePersonnes();
            }
        }
        return $places;
    }
}
