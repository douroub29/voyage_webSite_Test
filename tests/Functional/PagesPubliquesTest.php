<?php

namespace App\Tests\Functional;

class PagesPubliquesTest extends FunctionalTestCase
{
    public function testAccueil(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Pourquoi Choisir DreamTravel');
        self::assertAnySelectorTextContains('h2', 'Voyages à la Une');
        self::assertAnySelectorTextNotContains('h2', 'Nos Destinations');
        self::assertGreaterThan(0, $crawler->filter('img[src^="/uploads/voyages/"]')->count(), 'photos des voyages à la une');
        // Avis publiés uniquement (3 publiés, 1 en attente dans les fixtures)
        self::assertCount(3, $crawler->filter('.avis-card'));
        // Widget chatbot présent pour les visiteurs
        self::assertSelectorExists('#assistant-panneau');
    }

    public function testListeDesVoyagesAvecBoutonVersLeDetail(): void
    {
        $crawler = $this->client->request('GET', '/voyages');
        self::assertResponseIsSuccessful();
        self::assertCount(6, $crawler->filter('.voyage-card'));

        $this->client->click($crawler->selectLink('Voir le détail')->first()->link());
        self::assertResponseIsSuccessful();
        self::assertMatchesRegularExpression('#^/voyages/\d+$#', $this->client->getRequest()->getPathInfo());
        self::assertSelectorTextContains('body', 'Organisé par');
    }

    public function testFiltreParSociete(): void
    {
        $crawler = $this->client->request('GET', '/voyages', ['societe' => 'atlas']);

        $titres = $crawler->filter('.voyage-card h5')->each(fn($n) => $n->text());
        sort($titres);
        self::assertSame(['Dubaï Luxury Experience', 'Escapade Mystique à Marrakech'], $titres);
        self::assertSelectorExists('datalist#societes-list option[value="Atlas Voyages"]');
    }

    public function testVoyageInexistant(): void
    {
        $this->client->request('GET', '/voyages/999999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testVoyagePartiDesactiveAutomatiquementALaVisite(): void
    {
        $rome = $this->voyage('Rome');
        $rome->setDateDepart(new \DateTime('today'));
        static::getContainer()->get('doctrine')->getManager()->flush();
        // Le contrôle automatique a lieu au plus une fois par minute : on lève ce délai pour le test.
        (new \ReflectionProperty(\App\EventSubscriber\VoyageClotureSubscriber::class, 'dernierControle'))->setValue(null, null);

        $crawler = $this->client->request('GET', '/voyages');

        self::assertCount(5, $crawler->filter('.voyage-card'));
        self::assertSame(0, $this->compter("SELECT actif FROM voyage WHERE titre LIKE 'Rome%'"));
    }
}
