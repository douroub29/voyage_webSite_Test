<?php

namespace App\Enum;

enum TypeTransfert: string
{
    case AeroportHotel = 'aeroport_hotel';
    case HotelAeroport = 'hotel_aeroport';
    case LesDeux       = 'les_deux';

    public function label(): string
    {
        return match($this) {
            self::AeroportHotel => 'Aéroport → Hôtel',
            self::HotelAeroport => 'Hôtel → Aéroport',
            self::LesDeux       => 'Aéroport ⇄ Hôtel (Aller-Retour)',
        };
    }
}
