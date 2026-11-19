<?php

namespace App\Tests\Unit\Service;

use App\Service\IA\ErreurIA;
use App\Service\IA\GeminiFournisseur;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GeminiFournisseurTest extends TestCase
{
    public function testBoucleAppelOutilPuisReponseTexte(): void
    {
        $requetes = [];
        $reponses = [
            // 1) le modèle demande un outil (avec une thought signature à renvoyer telle quelle)
            json_encode(['candidates' => [['content' => ['role' => 'model', 'parts' => [[
                'functionCall'     => ['id' => 'c1', 'name' => 'rechercher_voyages', 'args' => ['destination' => 'Bali']],
                'thoughtSignature' => 'SIG123',
            ]]]]]]),
            // 2) réponse finale (la part de réflexion "thought" est ignorée)
            json_encode(['candidates' => [['content' => ['role' => 'model', 'parts' => [
                ['text' => 'réflexion interne', 'thought' => true],
                ['text' => 'Bali : 2490 € par personne.'],
            ]]]]]),
        ];
        $http = new MockHttpClient(function (string $methode, string $url, array $options) use (&$requetes, &$reponses) {
            $requetes[] = ['url' => $url, 'headers' => $options['headers'], 'corps' => json_decode($options['body'], true)];
            return new MockResponse(array_shift($reponses));
        });

        $outilsAppeles = [];
        $texte = (new GeminiFournisseur($http, 'AIza-test', 'gemini-test'))->converser(
            'Système',
            [['role' => 'user', 'content' => 'Bonjour'], ['role' => 'assistant', 'content' => 'Bonjour !']],
            'Prix pour Bali ?',
            [['name' => 'rechercher_voyages', 'description' => 'd', 'parameters' => ['type' => 'object', 'properties' => ['destination' => ['type' => 'string']]]],
             ['name' => 'programme_fidelite', 'description' => 'sans paramètre']],
            function (string $nom, array $entree) use (&$outilsAppeles) {
                $outilsAppeles[] = [$nom, $entree];
                return json_encode(['voyages' => [['titre' => 'Bali', 'prix_par_personne' => 2490]]]);
            },
        );

        self::assertSame('Bali : 2490 € par personne.', $texte);
        self::assertSame([['rechercher_voyages', ['destination' => 'Bali']]], $outilsAppeles);

        // Clé envoyée en en-tête (jamais dans l'URL), modèle dans l'URL.
        self::assertStringContainsString('/models/gemini-test:generateContent', $requetes[0]['url']);
        self::assertStringNotContainsString('AIza-test', $requetes[0]['url']);
        self::assertContains('x-goog-api-key: AIza-test', $requetes[0]['headers']);

        $premier = $requetes[0]['corps'];
        self::assertSame('model', $premier['contents'][1]['role']); // "assistant" -> "model"
        self::assertArrayNotHasKey('parameters', $premier['tools'][0]['functionDeclarations'][1]);

        // 2e requête : contenu du modèle renvoyé intact + functionResponse avec le même id.
        $contents = $requetes[1]['corps']['contents'];
        self::assertSame('SIG123', $contents[3]['parts'][0]['thoughtSignature']);
        $retour = $contents[4];
        self::assertSame('user', $retour['role']);
        self::assertSame('c1', $retour['parts'][0]['functionResponse']['id']);
        self::assertSame(2490, $retour['parts'][0]['functionResponse']['response']['result']['voyages'][0]['prix_par_personne']);
    }

    public function testQuotaGratuitAtteint(): void
    {
        $http = new MockHttpClient(new MockResponse('{"error":{"code":429}}', ['http_code' => 429]));

        try {
            (new GeminiFournisseur($http, 'AIza-test', 'm'))->completerJson('s', 'c');
            self::fail('ErreurIA attendue');
        } catch (ErreurIA $e) {
            self::assertSame(ErreurIA::QUOTA, $e->raison);
        }
    }

    public function testCompleterJsonToleraLeBlocMarkdown(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode(['candidates' => [['content' => ['parts' => [
            ['text' => "```json\n{\"recommandations\": [{\"voyage_id\": 3, \"raison\": \"ok\"}]}\n```"],
        ]]]]])));

        $json = (new GeminiFournisseur($http, 'AIza-test', 'm'))->completerJson('s', 'c');
        self::assertSame(3, $json['recommandations'][0]['voyage_id']);
    }
}
