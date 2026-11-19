<?php

namespace App\Service\IA;

/**
 * Fournisseur réellement utilisé : Gemini (gratuit) si GEMINI_API_KEY est configurée,
 * sinon Claude si ANTHROPIC_API_KEY l'est, sinon aucun (le site fonctionne alors sans IA).
 */
class FournisseurIAChoisi implements FournisseurIA
{
    public function __construct(
        private readonly GeminiFournisseur $gemini,
        private readonly ClaudeFournisseur $claude,
    ) {}

    private function actif(): ?FournisseurIA
    {
        foreach ([$this->gemini, $this->claude] as $fournisseur) {
            if ($fournisseur->isDisponible()) {
                return $fournisseur;
            }
        }
        return null;
    }

    public function nom(): string
    {
        return $this->actif()?->nom() ?? 'aucun';
    }

    public function isDisponible(): bool
    {
        return $this->actif() !== null;
    }

    public function converser(string $systeme, array $historique, string $question, array $outils, callable $executer): string
    {
        return ($this->actif() ?? throw new ErreurIA('Aucune clé d\'IA configurée.'))
            ->converser($systeme, $historique, $question, $outils, $executer);
    }

    public function completerJson(string $systeme, string $contenu): ?array
    {
        return ($this->actif() ?? throw new ErreurIA('Aucune clé d\'IA configurée.'))
            ->completerJson($systeme, $contenu);
    }
}
