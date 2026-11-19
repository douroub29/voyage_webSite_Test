<?php

namespace App\Enum;

enum CompagnieAerienne: string
{
    case Tunisair        = 'tunisair';
    case TunisairExpress = 'tunisair_express';
    case Nouvelair       = 'nouvelair';
    case AirFrance       = 'air_france';
    case Transavia       = 'transavia';
    case RoyalAirMaroc   = 'royal_air_maroc';
    case TurkishAirlines = 'turkish_airlines';
    case Emirates        = 'emirates';
    case QatarAirways    = 'qatar_airways';
    case Lufthansa       = 'lufthansa';
    case ItaAirways      = 'ita_airways';

    public function label(): string
    {
        return match ($this) {
            self::Tunisair        => 'Tunisair',
            self::TunisairExpress => 'Tunisair Express',
            self::Nouvelair       => 'Nouvelair',
            self::AirFrance       => 'Air France',
            self::Transavia       => 'Transavia',
            self::RoyalAirMaroc   => 'Royal Air Maroc',
            self::TurkishAirlines => 'Turkish Airlines',
            self::Emirates        => 'Emirates',
            self::QatarAirways    => 'Qatar Airways',
            self::Lufthansa       => 'Lufthansa',
            self::ItaAirways      => 'ITA Airways',
        };
    }

    /** Code IATA à 2 caractères, utilisé comme préfixe du numéro de vol. */
    public function codeIata(): string
    {
        return match ($this) {
            self::Tunisair        => 'TU',
            self::TunisairExpress => 'UG',
            self::Nouvelair       => 'BJ',
            self::AirFrance       => 'AF',
            self::Transavia       => 'TO',
            self::RoyalAirMaroc   => 'AT',
            self::TurkishAirlines => 'TK',
            self::Emirates        => 'EK',
            self::QatarAirways    => 'QR',
            self::Lufthansa       => 'LH',
            self::ItaAirways      => 'AZ',
        };
    }
}
