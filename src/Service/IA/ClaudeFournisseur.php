<?php

namespace App\Service\IA;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\RateLimitException;

/** Claude (API Anthropic, payante) via le SDK officiel anthropic-ai/sdk. Optionnel. */
class ClaudeFournisseur implements FournisseurIA
{
    public const MODELE = 'claude-opus-5-5';
    private const MAX_ETAPES = 6;

    private ?Client $client = null;

    public function __construct(private readonly ?string $apiKey) {}

    public function nom(): string
    {
        return 'Anthropic Claude (' . self::MODELE . ')';
    }

    public function isDisponible(): bool
    {
        return $this->apiKey !== null && trim($this->apiKey) !== '';
    }

    public function converser(string $systeme, array $historique, string $question, array $outils, callable $executer): string
    {
        $messages   = $historique;
        $messages[] = ['role' => 'user', 'content' => $question];
        $tools = array_map(fn(array $o) => [
            'name'        => $o['name'],
            'description' => $o['description'],
            'inputSchema' => $o['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
        ], $outils);

        for ($etape = 0; $etape < self::MAX_ETAPES; $etape++) {
            $reponse = $this->envoyer([
                'system'       => $systeme,
                'messages'     => $messages,
                'tools'        => $tools,
                'outputConfig' => ['effort' => 'low'],
            ]);

            if ($reponse->stopReason === 'refusal') {
                throw new ErreurIA('Requête refusée par le modèle.', ErreurIA::REFUS);
            }
            if ($reponse->stopReason !== 'tool_use') {
                return self::texte($reponse);
            }

            $resultats = [];
            foreach ($reponse->content as $bloc) {
                if ($bloc->type === 'tool_use') {
                    $resultats[] = [
                        'type'      => 'tool_result',
                        'toolUseID' => $bloc->id,
                        'content'   => $executer($bloc->name, (array) $bloc->input),
                    ];
                }
            }
            $messages[] = ['role' => 'assistant', 'content' => $reponse->content];
            $messages[] = ['role' => 'user', 'content' => $resultats];
        }

        throw new ErreurIA('Trop d\'appels d\'outils.', ErreurIA::TROP_D_ETAPES);
    }

    public function completerJson(string $systeme, string $contenu): ?array
    {
        $reponse = $this->envoyer([
            'system'       => $systeme . "\nRéponds uniquement avec l'objet JSON demandé, sans texte autour.",
            'messages'     => [['role' => 'user', 'content' => $contenu]],
            'outputConfig' => ['effort' => 'low'],
        ]);

        return $reponse->stopReason === 'end_turn' ? ErreurIA::extraireJson(self::texte($reponse)) : null;
    }

    /** @throws ErreurIA */
    private function envoyer(array $parametres): BetaMessage
    {
        if (!$this->isDisponible()) {
            throw new ErreurIA('ANTHROPIC_API_KEY n\'est pas configurée.');
        }
        $this->client ??= new Client(apiKey: $this->apiKey);

        try {
            return $this->client->beta->messages->create(...array_merge([
                'model'     => self::MODELE,
                'maxTokens' => 16000,
                // Repli automatique sur un autre modèle si celui-ci refuse pour raison de sécurité.
                'fallbacks' => 'default',
                'betas'     => ['server-side-fallback-2026-07-01'],
            ], $parametres));
        } catch (RateLimitException $e) {
            throw new ErreurIA('Limite de requêtes Claude atteinte.', ErreurIA::QUOTA, $e);
        } catch (APIException $e) {
            throw new ErreurIA('Erreur API Claude : ' . $e->getMessage(), null, $e);
        }
    }

    private static function texte(BetaMessage $message): string
    {
        $texte = '';
        foreach ($message->content as $bloc) {
            if ($bloc->type === 'text') {
                $texte .= $bloc->text;
            }
        }
        return trim($texte);
    }
}
