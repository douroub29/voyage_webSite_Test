<?php

namespace App\Service\IA;

/** Échec d'un appel au modèle d'IA (réseau, clé, quota, refus...). */
class ErreurIA extends \RuntimeException
{
    public const QUOTA = 'quota';
    public const REFUS = 'refus';
    public const TROP_D_ETAPES = 'etapes';

    public function __construct(string $message, public readonly ?string $raison = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /** Extrait le premier objet JSON d'un texte (tolère un bloc ```json ... ```). */
    public static function extraireJson(string $texte): ?array
    {
        $debut = strpos($texte, '{');
        $fin   = strrpos($texte, '}');
        if ($debut === false || $fin === false || $fin < $debut) {
            return null;
        }
        $json = json_decode(substr($texte, $debut, $fin - $debut + 1), true);
        return is_array($json) ? $json : null;
    }
}
