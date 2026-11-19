<?php

namespace App\Tests\Functional;

use App\Service\IA\FournisseurIA;
use App\Service\IA\FournisseurIAChoisi;

class AvisEtAssistantTest extends FunctionalTestCase
{
    public function testClientPublieUnAvisVisibleImmediatement(): void
    {
        $this->connecter('lucas@example.com');
        $crawler = $this->client->request('GET', '/');

        $this->client->submit($crawler->selectButton('Publier mon avis')->form([
            'avis_form[note]'        => 4,
            'avis_form[commentaire]' => 'Test fonctionnel : très bonne agence !',
        ]));
        $crawler = $this->client->followRedirect();

        self::assertSelectorTextContains('.flash-success', 'publié');
        self::assertCount(4, $crawler->filter('.avis-card'));
        self::assertSelectorTextContains('.avis-card', 'Test fonctionnel : très bonne agence !');
    }

    public function testAvisTropCourtRefuse(): void
    {
        $this->connecter('lucas@example.com');
        $crawler = $this->client->request('GET', '/');
        $this->client->submit($crawler->selectButton('Publier mon avis')->form(['avis_form[commentaire]' => 'court']));
        $crawler = $this->client->followRedirect();

        self::assertSelectorTextContains('.flash-danger', 'au moins 10 caractères');
        self::assertCount(3, $crawler->filter('.avis-card'));
    }

    public function testVisiteurNePeutPasPublierDAvis(): void
    {
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('form[name="avis_form"]');
        $this->client->request('POST', '/avis/nouveau');
        self::assertResponseRedirects('/connexion');
    }

    private function poserQuestion(string $question, ?string $jeton = null): array
    {
        $html = $this->client->request('GET', '/')->html();
        preg_match("/const csrf = '([^']+)'/", $html, $m);

        $this->client->request('POST', '/assistant/message', server: [
            'CONTENT_TYPE'      => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $jeton ?? $m[1],
        ], content: json_encode(['message' => $question]));

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    public function testChatbotProtegeParCsrf(): void
    {
        $reponse = $this->poserQuestion('Bonjour', 'jeton-invalide');
        self::assertResponseStatusCodeSame(403);
        self::assertArrayHasKey('erreur', $reponse);
    }

    public function testChatbotSansCleIA(): void
    {
        self::assertStringContainsString('pas encore activé', $this->poserQuestion('Bonjour')['reponse']);
    }

    public function testChatbotUtiliseLesDonneesDeLaBase(): void
    {
        $this->client->disableReboot();

        // Faux modèle d'IA : il appelle l'outil de recherche et répond avec son résultat.
        $faux = new class implements FournisseurIA {
            public function nom(): string { return 'faux'; }
            public function isDisponible(): bool { return true; }
            public function completerJson(string $s, string $c): ?array { return null; }
            public function converser(string $systeme, array $historique, string $question, array $outils, callable $executer): string
            {
                $r = json_decode($executer('rechercher_voyages', ['destination' => 'Bali']), true);
                $v = $r['voyages'][0];
                return sprintf('%s : %d places, %d €', $v['titre'], $v['places_restantes'], $v['prix_par_personne']);
            }
        };
        static::getContainer()->set(FournisseurIAChoisi::class, $faux);

        self::assertSame('Bali : L\'Île des Dieux : 8 places, 2490 €', $this->poserQuestion('Places pour Bali ?')['reponse']);
    }

    public function testChatbotRefuseUneQuestionTropLongue(): void
    {
        $this->poserQuestion(str_repeat('a', 501));
        self::assertResponseStatusCodeSame(422);
    }
}
