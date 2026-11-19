<?php

namespace App\Service\IA;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Google Gemini (API generateContent) — utilisable gratuitement avec une clé Google AI Studio.
 * Attention : en version gratuite, Google peut utiliser les contenus envoyés pour améliorer
 * ses produits ; aucune donnée personnelle de client n'est donc envoyée (voir OutilsCatalogue).
 */
class GeminiFournisseur implements FournisseurIA
{
    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
    private const MAX_ETAPES = 6;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $apiKey,
        private readonly string $modele,
    ) {}

    public function nom(): string
    {
        return 'Google Gemini (' . $this->modele . ')';
    }

    public function isDisponible(): bool
    {
        return $this->apiKey !== null && trim($this->apiKey) !== '';
    }

    public function converser(string $systeme, array $historique, string $question, array $outils, callable $executer): string
    {
        $contents = [];
        foreach ($historique as $tour) {
            $contents[] = [
                'role'  => $tour['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $tour['content']]],
            ];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $question]]];

        $declarations = array_map(fn(array $o) => array_filter([
            'name'        => $o['name'],
            'description' => $o['description'],
            'parameters'  => $o['parameters'] ?? null,
        ]), $outils);

        for ($etape = 0; $etape < self::MAX_ETAPES; $etape++) {
            $reponse = $this->appeler([
                'systemInstruction' => ['parts' => [['text' => $systeme]]],
                'contents'          => $contents,
                'tools'             => [['functionDeclarations' => $declarations]],
            ]);

            $candidat = $reponse->candidates[0] ?? null;
            if (!$candidat || !isset($candidat->content->parts)) {
                throw new ErreurIA('Réponse Gemini vide (' . ($candidat->finishReason ?? $reponse->promptFeedback->blockReason ?? 'inconnue') . ').', ErreurIA::REFUS);
            }

            $appels = array_filter($candidat->content->parts, fn($p) => isset($p->functionCall));
            if (!$appels) {
                return self::texte($candidat->content->parts);
            }

            // Le contenu du modèle est renvoyé tel quel (il contient les "thought signatures" obligatoires).
            $contents[] = $candidat->content;

            $resultats = [];
            foreach ($appels as $part) {
                $appel  = $part->functionCall;
                $entree = json_decode(json_encode($appel->args ?? new \stdClass()), true) ?: [];
                $sortie = json_decode($executer($appel->name, $entree), true);

                $resultats[] = ['functionResponse' => array_filter([
                    'id'       => $appel->id ?? null,
                    'name'     => $appel->name,
                    'response' => ['result' => $sortie],
                ], fn($v) => $v !== null)];
            }
            $contents[] = ['role' => 'user', 'parts' => $resultats];
        }

        throw new ErreurIA('Trop d\'appels d\'outils.', ErreurIA::TROP_D_ETAPES);
    }

    public function completerJson(string $systeme, string $contenu): ?array
    {
        $reponse = $this->appeler([
            'systemInstruction' => ['parts' => [['text' => $systeme . "\nRéponds uniquement avec l'objet JSON demandé, sans texte autour."]]],
            'contents'          => [['role' => 'user', 'parts' => [['text' => $contenu]]]],
        ]);

        $parts = $reponse->candidates[0]->content->parts ?? [];
        return ErreurIA::extraireJson(self::texte($parts));
    }

    /** @throws ErreurIA */
    private function appeler(array $corps): object
    {
        if (!$this->isDisponible()) {
            throw new ErreurIA('GEMINI_API_KEY n\'est pas configurée.');
        }

        try {
            $reponse = $this->httpClient->request('POST', sprintf(self::URL, rawurlencode($this->modele)), [
                'headers' => ['x-goog-api-key' => $this->apiKey, 'Content-Type' => 'application/json'],
                'body'    => json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'timeout' => 30,
            ]);
            $statut = $reponse->getStatusCode();
            $brut   = $reponse->getContent(false);
        } catch (ExceptionInterface $e) {
            throw new ErreurIA('Connexion à Gemini impossible : ' . $e->getMessage(), null, $e);
        }

        if ($statut === 429) {
            throw new ErreurIA('Quota gratuit Gemini atteint.', ErreurIA::QUOTA);
        }
        if ($statut !== 200) {
            throw new ErreurIA(sprintf('Gemini HTTP %d : %s', $statut, mb_substr($brut, 0, 500)));
        }

        // Objets (et non tableaux) pour renvoyer le contenu du modèle à l'identique.
        $json = json_decode($brut);
        if (!is_object($json)) {
            throw new ErreurIA('Réponse Gemini illisible.');
        }
        return $json;
    }

    /** Texte des parts, sans les parts de réflexion interne. */
    private static function texte(array $parts): string
    {
        $texte = '';
        foreach ($parts as $p) {
            if (isset($p->text) && empty($p->thought)) {
                $texte .= $p->text;
            }
        }
        return trim($texte);
    }
}
