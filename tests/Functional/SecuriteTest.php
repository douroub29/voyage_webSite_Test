<?php

namespace App\Tests\Functional;

class SecuriteTest extends FunctionalTestCase
{
    public function testAdminArriveDirectementSurSonTableauDeBord(): void
    {
        $crawler = $this->client->request('GET', '/connexion');
        $this->client->submit($crawler->selectButton('Se connecter')->form([
            'email'    => 'admin@dreamtravel.com',
            'password' => 'Admin@1234',
        ]));

        self::assertResponseRedirects('/admin');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.sidebar-nav');
    }

    public function testClientArriveSurLAccueil(): void
    {
        $crawler = $this->client->request('GET', '/connexion');
        $this->client->submit($crawler->selectButton('Se connecter')->form([
            'email'    => 'sophie@example.com',
            'password' => 'Client@1234',
        ]));

        self::assertResponseRedirects();
        self::assertStringNotContainsString('/admin', $this->client->getResponse()->headers->get('Location'));
    }

    public function testMauvaisMotDePasse(): void
    {
        $crawler = $this->client->request('GET', '/connexion');
        $this->client->submit($crawler->selectButton('Se connecter')->form([
            'email' => 'sophie@example.com', 'password' => 'faux',
        ]));
        $this->client->followRedirect();

        self::assertRouteSame('app_login');
        self::assertSelectorExists('form');
    }

    public function testUnClientNAccedePasALAdministration(): void
    {
        $this->connecter('sophie@example.com');
        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);
    }

    public function testEspaceClientReserveAuxClientsConnectes(): void
    {
        foreach (['/mon-compte', '/mon-compte/reservations', '/mon-compte/voucher', '/reservation/nouvelle/1'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseRedirects('/connexion', null, $url);
        }
    }
}
