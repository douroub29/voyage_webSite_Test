<?php

namespace App\Tests\Functional;

/** Planificateur par ambiance (sans IA en test) et gel de prix de bout en bout. */
class PlanificateurEtGelPrixTest extends FunctionalTestCase
{
    public function testLePlanificateurComposeUnVoyageAvecProgrammeModifiable(): void
    {
        $crawler = $this->client->request('GET', '/planificateur');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->selectButton('Composer mon voyage')->form([
            'envie' => 'Une escapade culturelle et romantique en mai, à moins de 2h de vol',
        ]));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#resultat', 'Rome'); // seul départ en mai, vol ~1,3 h
        self::assertSelectorExists('#programmeInitial');
        $programme = json_decode($this->client->getCrawler()->filter('#programmeInitial')->text(), true);
        self::assertCount(9, $programme); // 8 nuits → 9 jours
        self::assertSelectorExists('.contexte-sejour[data-url]');
    }

    public function testUneEnvieTropCourteEstRefusee(): void
    {
        $crawler = $this->client->request('GET', '/planificateur');
        $this->client->submit($crawler->selectButton('Composer mon voyage')->form(['envie' => 'mer']));

        self::assertResponseIsUnprocessable();
        self::assertSelectorNotExists('#resultat');
    }

    public function testGelDePrixPuisReservationAuPrixGele(): void
    {
        $this->connecter('lucas@example.com');
        $rome = $this->voyage('Rome');
        $id   = $rome->getId();

        $crawler = $this->client->request('GET', '/voyages/' . $id);
        self::assertSelectorTextContains('body', 'Prévision à 7 jours');
        $this->client->submit($crawler->selectButton('Payer 10,00 € et geler')->form(['methode' => 'carte']));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.gel-actif', 'Prix gelé : 1 450 €');
        self::assertSame(1, $this->compter("SELECT COUNT(*) FROM gel_prix WHERE voyage_id = $id"));

        // Le prix monte après le gel : le client paie toujours l'ancien prix.
        static::getContainer()->get('doctrine.dbal.default_connection')->executeStatement("UPDATE voyage SET prix = 1600 WHERE id = $id");

        $crawler = $this->client->request('GET', '/reservation/nouvelle/' . $id);
        self::assertSelectorTextContains('body', 'Votre prix gelé sera appliqué');
        $this->client->submit($crawler->selectButton('Continuer vers le récapitulatif')->form(['reservation_form[nombrePersonnes]' => 2]));
        $this->client->followRedirect();

        self::assertSelectorTextContains('body', 'Prix gelé appliqué');
        self::assertSame(2900, $this->compter("SELECT prix_total FROM reservation ORDER BY id DESC LIMIT 1"));
        self::assertSame(1, $this->compter("SELECT COUNT(*) FROM reservation WHERE gel_prix_id IS NOT NULL"));
    }

    public function testUnVisiteurNePeutPasGelerUnPrix(): void
    {
        $this->client->request('POST', '/voyages/' . $this->voyage('Rome')->getId() . '/geler-prix');

        self::assertResponseRedirects();
        self::assertStringContainsString('/connexion', $this->client->getResponse()->headers->get('Location'));
        self::assertSame(0, $this->compter('SELECT COUNT(*) FROM gel_prix'));
    }
}
