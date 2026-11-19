<?php

namespace App\Service\IA;

/**
 * Modèle d'IA utilisé par le chatbot et les recommandations.
 * Implémentations : GeminiFournisseur (gratuit), ClaudeFournisseur (payant).
 * FournisseurIAChoisi sélectionne celui dont la clé est configurée.
 */
interface FournisseurIA
{
    public function nom(): string;

    public function isDisponible(): bool;

    /**
     * Conversation avec appels d'outils.
     *
     * @param list<array{role: 'user'|'assistant', content: string}> $historique tours précédents (texte)
     * @param list<array{name: string, description: string, parameters?: array}> $outils schémas JSON des outils
     * @param callable(string $nom, array $entree): string $executer exécute un outil, renvoie du JSON
     *
     * @throws ErreurIA
     */
    public function converser(string $systeme, array $historique, string $question, array $outils, callable $executer): string;

    /**
     * Réponse JSON (objet) à une consigne ; null si la réponse n'est pas du JSON valide.
     *
     * @throws ErreurIA
     */
    public function completerJson(string $systeme, string $contenu): ?array;
}
