<?php

namespace App\Tests\Functional;

class ApiTest extends FunctionalTestCase
{
    private function getJson(string $url): array
    {
        $this->client->request('GET', $url);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        return json_decode($this->client->getResponse()->getContent(), true);
    }

    public function testListeDesVoyages(): void
    {
        $data = $this->getJson('/api/voyages');

        self::assertResponseIsSuccessful();
        self::assertSame(6, $data['total']);
        $bali = current(array_filter($data['voyages'], fn($v) => $v['titre'] === 'Bali : L\'Île des Dieux'));
        self::assertSame(['nom' => 'Bali', 'pays' => 'Indonésie'], $bali['destination']);
        self::assertSame('Globe Trotter Agency', $bali['societe']);
        self::assertEquals(2490, $bali['prixParPersonne']);
        self::assertSame(8, $bali['placesRestantes']);
        self::assertSame(14, $bali['dureeNuits']);
        self::assertTrue($bali['reservable']);
        self::assertStringEndsWith('/uploads/voyages/bali.jpg', $bali['photo']);
        self::assertStringEndsWith('/voyages/' . $bali['id'], $bali['url']);
    }

    public function testFiltres(): void
    {
        $titres = fn(array $d) => array_column($d['voyages'], 'titre');

        self::assertEqualsCanonicalizing(
            ['Escapade Mystique à Marrakech', 'Dubaï Luxury Experience'],
            $titres($this->getJson('/api/voyages?societe=atlas'))
        );
        self::assertSame(['Escapade Mystique à Marrakech'], $titres($this->getJson('/api/voyages?societe=atlas&budget=1000')));
        // 9 voyageurs : Bali (8 places) est exclu
        self::assertNotContains('Bali : L\'Île des Dieux', $titres($this->getJson('/api/voyages?personnes=9')));
        self::assertSame(0, $this->getJson('/api/voyages?destination=Japon')['total']);
    }

    public function testParametresInvalides(): void
    {
        $data = $this->getJson('/api/voyages?budget=abc&date_depart=31/12/2026&personnes=0');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['budget', 'date_depart', 'personnes'], array_keys($data['details']));
    }

    public function testDetailDUnVoyage(): void
    {
        $id   = $this->voyage('Week-end Romantique')->getId();
        $data = $this->getJson('/api/voyages/' . $id);

        self::assertResponseIsSuccessful();
        self::assertSame('Tunisair', $data['compagnieAerienne']);
        self::assertSame('TU 712', $data['numeroVol']);
        self::assertSame('Hôtel Lutetia', $data['hotels'][0]['nom']);
        self::assertEquals(450, $data['hotels'][0]['prixParNuit']);
        self::assertNull($data['motifNonReservable']);
    }

    public function testErreursEnJson(): void
    {
        self::assertSame('Voyage 999999 introuvable.', $this->getJson('/api/voyages/999999')['erreur']);
        self::assertResponseStatusCodeSame(404);

        self::assertSame(404, $this->getJson('/api/route-inconnue')['statut']);

        $this->client->request('DELETE', '/api/voyages');
        self::assertResponseStatusCodeSame(405);
        self::assertSame('Méthode HTTP non autorisée.', json_decode($this->client->getResponse()->getContent(), true)['erreur']);
    }

    public function testSocietes(): void
    {
        $data = $this->getJson('/api/societes');

        self::assertSame(3, $data['total']);
        self::assertSame(
            ['Atlas Voyages' => 2, 'Globe Trotter Agency' => 2, 'Horizon Évasion' => 2],
            array_column($data['societes'], 'voyagesProposes', 'nom')
        );
    }

    public function testAucuneDonneePersonnelle(): void
    {
        $this->client->request('GET', '/api/voyages');
        $json = $this->client->getResponse()->getContent();

        self::assertStringNotContainsString('@', str_replace('\/', '/', preg_replace('#https?://[^"]+#', '', $json)));
        self::assertStringNotContainsString('motDePasse', $json);
    }
}
