<?php

namespace App\Tests\Functional;

class ReservationTest extends FunctionalTestCase
{
    public function testFormulaireSansNombreDeNuits(): void
    {
        $this->connecter('lucas@example.com');
        $this->client->request('GET', '/reservation/nouvelle/' . $this->voyage('Rome')->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[name="reservation_form[nombreNuits]"]');
        self::assertSelectorTextContains('body', '8 nuit(s)'); // durée du voyage Rome
    }

    public function testPlusDePersonnesQueDePlacesAfficheUneErreur(): void
    {
        $this->connecter('lucas@example.com');
        $bali    = $this->voyage('Bali');
        $avant   = $this->compter('SELECT COUNT(*) FROM reservation');
        $crawler = $this->client->request('GET', '/reservation/nouvelle/' . $bali->getId());

        $this->client->submit($crawler->selectButton('Continuer vers le récapitulatif')->form([
            'reservation_form[nombrePersonnes]' => 99,
        ]));

        self::assertResponseIsUnprocessable();
        self::assertSelectorTextContains('#erreur-places-serveur', 'supérieur au nombre de places disponibles');
        self::assertSelectorTextContains('#erreur-places-serveur', 'il ne reste que 8 place(s)');
        self::assertSame($avant, $this->compter('SELECT COUNT(*) FROM reservation'));
    }

    public function testReservationPuisPaiement(): void
    {
        $this->connecter('lucas@example.com');
        $rome    = $this->voyage('Rome');
        $crawler = $this->client->request('GET', '/reservation/nouvelle/' . $rome->getId());
        $form    = $crawler->selectButton('Continuer vers le récapitulatif')->form(['reservation_form[nombrePersonnes]' => 2]);
        $form['reservation_form[hotel]']->select($form['reservation_form[hotel]']->availableOptionValues()[1]);
        $this->client->submit($form);

        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertRouteSame('reservation_recap');
        self::assertSelectorTextContains('body', '(8 nuits)'); // nuits = durée du voyage
        self::assertSame(23, $this->compter("SELECT places_disponibles FROM voyage WHERE titre LIKE 'Rome%'"));

        $this->client->submit($crawler->selectButton('Confirmer et payer')->form());
        $this->client->followRedirect();
        self::assertRouteSame('reservation_confirmation');
        // Lucas n'a pas 5 réservations : pas de bouton voucher
        self::assertSelectorNotExists('a[href="/mon-compte/voucher"]');
    }

    public function testVoucherRefuseSousCinqReservations(): void
    {
        $this->connecter('sophie@example.com');
        $this->client->request('GET', '/mon-compte/voucher');

        self::assertResponseRedirects('/mon-compte/reservations');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-warning', '5 réservations confirmées');
        self::assertSame(0, $this->compter('SELECT COUNT(*) FROM voucher'));
    }

    public function testRecommandationsSansIAExcluentLesVoyagesDejaReserves(): void
    {
        $this->connecter('sophie@example.com'); // a réservé Paris (Horizon Évasion)
        $crawler = $this->client->request('GET', '/mon-compte/recommandations');

        self::assertResponseIsSuccessful();
        $titres = $crawler->filter('h6')->each(fn($n) => $n->text());
        self::assertCount(3, $titres);
        self::assertNotContains('Week-end Romantique à Paris', $titres);
        self::assertSame('Rome Éternelle — Circuit Culturel', $titres[0]); // même société que Paris
    }
}
