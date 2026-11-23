<?php

namespace App\Tests\Unit\Service;

use App\Entity\Destination;
use App\Entity\Hotel;
use App\Entity\Voyage;
use App\Enum\CompagnieAerienne;
use App\Repository\HotelRepository;
use App\Repository\VoyageRepository;
use App\Service\Contexte\ContexteSejourService;
use App\Service\IA\ClaudeFournisseur;
use App\Service\IA\FournisseurIA;
use App\Service\IA\FournisseurIAChoisi;
use App\Service\IA\GeminiFournisseur;
use App\Service\IA\PlanificateurAmbiance;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

class PlanificateurEtContexteTest extends TestCase
{
    private static int $id = 0;

    private static function destination(string $nom, string $description, float $vol): Destination
    {
        $d = (new Destination())->setNom($nom)->setPays('P')->setDescription($description)->setDureeVolHeures($vol);
        (new \ReflectionProperty(Destination::class, 'id'))->setValue($d, ++self::$id);
        return $d;
    }

    private static function voyage(Destination $dest, string $prix, string $depart): Voyage
    {
        $d = new \DateTimeImmutable($depart);
        $v = (new Voyage())->setTitre('Séjour ' . $dest->getNom())->setDestination($dest)
            ->setDateDepart($d)->setDateRetour($d->modify('+7 days'))->setPrix($prix)
            ->setCompagnieAerienne(CompagnieAerienne::Tunisair)->setNombrePlaces(10);
        (new \ReflectionProperty(Voyage::class, 'id'))->setValue($v, ++self::$id);
        return $v;
    }

    private static function hotel(Destination $dest, int $etoiles, string $prixNuit): Hotel
    {
        $h = (new Hotel())->setNom('Hôtel ' . $etoiles . '★')->setDestination($dest)->setEtoiles($etoiles)->setPrixParNuit($prixNuit);
        (new \ReflectionProperty(Hotel::class, 'id'))->setValue($h, ++self::$id);
        return $h;
    }

    private static function sansIA(): FournisseurIA
    {
        return new FournisseurIAChoisi(new GeminiFournisseur(new MockHttpClient(), null, 'm'), new ClaudeFournisseur(null));
    }

    private function planificateur(array $voyages, array $hotels, ?FournisseurIA $ia = null): PlanificateurAmbiance
    {
        $vr = $this->createMock(VoyageRepository::class);
        $vr->method('findActifs')->willReturn($voyages);
        $hr = $this->createMock(HotelRepository::class);
        $hr->method('findActifs')->willReturn($hotels);
        return new PlanificateurAmbiance($vr, $hr, $ia ?? self::sansIA(), new NullLogger());
    }

    public function testExtractionDuBudgetEtDuMois(): void
    {
        self::assertSame(800.0, PlanificateurAmbiance::extraireBudget('calme, budget de 800 €'));
        self::assertSame(2500.0, PlanificateurAmbiance::extraireBudget('pas plus de 2 500 euros'));
        self::assertNull(PlanificateurAmbiance::extraireBudget('à moins de 3h de vol'));
        self::assertSame(10, PlanificateurAmbiance::extraireMois('au bord de la mer en octobre'));
        self::assertNull(PlanificateurAmbiance::extraireMois('bientôt'));

        self::assertFalse(PlanificateurAmbiance::contientMot('souks, riads et palmeraies', 'mer'));
        self::assertTrue(PlanificateurAmbiance::contientMot('au bord de la mer.', 'mer'));
        self::assertTrue(PlanificateurAmbiance::contientMot('des plages de sable', 'plage'));
    }

    public function testSansIAChoisitLaDestinationQuiCorrespondAuxContraintes(): void
    {
        $mer   = self::destination('Plage', 'Une île paradisiaque, plage de sable et sérénité.', 2.0);
        $ville = self::destination('Métropole', 'La ville qui ne dort jamais, capitale du shopping.', 11.0);
        $vMer   = self::voyage($mer, '600', '+3 months');
        $vVille = self::voyage($ville, '600', '+3 months');
        $hotels = [self::hotel($mer, 5, '100'), self::hotel($mer, 3, '20'), self::hotel($ville, 4, '50')];

        $plan = $this->planificateur([$vVille, $vMer], $hotels)
            ->planifier('Un endroit calme au bord de la mer, à moins de 3h de vol, budget 800 €');

        self::assertSame($vMer, $plan['voyage']);
        // L'hôtel 5★ (600 + 7×100 = 1300 €) dépasse le budget : le 3★ (600 + 7×20 = 740 €) est retenu.
        self::assertSame('Hôtel 3★', $plan['hotel']->getNom());
        self::assertSame(740.0, $plan['budget_par_personne']);
        self::assertFalse($plan['ia']);
        self::assertCount(8, $plan['programme']); // 7 nuits → 8 jours
        self::assertSame('Retour', end($plan['programme'])['titre']);
    }

    public function testLIANePeutChoisirQueDesVoyagesEtHotelsDuCatalogue(): void
    {
        $a = self::destination('A', 'desc', 2);
        $b = self::destination('B', 'desc', 2);
        $vA = self::voyage($a, '500', '+2 months');
        $hotelB = self::hotel($b, 4, '80');

        $ia = $this->createMock(FournisseurIA::class);
        $ia->method('isDisponible')->willReturn(true);
        $ia->method('completerJson')->willReturn([
            'voyage_id' => $vA->getId(),
            'hotel_id'  => $hotelB->getId(), // hôtel d'une autre destination : refusé
            'correspondance' => 150,
            'pourquoi'  => '<b>Parfait</b> pour vous.',
            'programme' => array_fill(0, 20, ['titre' => 'Jour', 'activites' => ['a', 'b', 'c', 'd', 'e', 'f', 'g']]),
            'alternatives' => [['voyage_id' => 999, 'raison' => 'inventé']],
        ]);

        $plan = $this->planificateur([$vA], [$hotelB], $ia)->planifier('envie de soleil');

        self::assertSame($vA, $plan['voyage']);
        self::assertNull($plan['hotel']);
        self::assertSame(100, $plan['correspondance']);
        self::assertSame('Parfait pour vous.', $plan['pourquoi']);
        self::assertCount(8, $plan['programme']);
        self::assertCount(5, $plan['programme'][0]['activites']);
        self::assertSame([], $plan['alternatives']);
        self::assertTrue($plan['ia']);
    }

    public function testLeServeurCorrigeUneIAQuiIgnoreLeBudgetEtLeVol(): void
    {
        $loin = self::destination('Loin', 'desc', 11.0);
        $v    = self::voyage($loin, '1000', '+2 months');
        $luxe = self::hotel($loin, 5, '400');
        $eco  = self::hotel($loin, 3, '100');

        $ia = $this->createMock(FournisseurIA::class);
        $ia->method('isDisponible')->willReturn(true);
        $ia->method('completerJson')->willReturn([
            'voyage_id' => $v->getId(), 'hotel_id' => $luxe->getId(), 'correspondance' => 95,
            'pourquoi' => 'Budget parfaitement respecté.', 'compromis' => '',
        ]);

        $plan = $this->planificateur([$v], [$luxe, $eco], $ia)->planifier('Plage, moins de 3h de vol, budget 1800 €');

        // 1000 + 7×400 = 3800 € > 1800 € : hôtel remplacé par le 3★ (1000 + 7×100 = 1700 €).
        self::assertSame($eco, $plan['hotel']);
        self::assertStringContainsString('environ 11,0 h', $plan['compromis']);
        self::assertLessThanOrEqual(60, $plan['correspondance']);
    }

    public function testUneExplicationQuiParleDUneAutreDestinationEstRemplacee(): void
    {
        $paris = self::destination('Paris', 'desc', 2.5);
        $rome  = self::destination('Rome', 'desc', 1.3);
        $vParis = self::voyage($paris, '1000', '+2 months');
        $vRome  = self::voyage($rome, '1000', '+2 months');

        $ia = $this->createMock(FournisseurIA::class);
        $ia->method('isDisponible')->willReturn(true);
        $ia->method('completerJson')->willReturn(['voyage_id' => $vParis->getId(), 'pourquoi' => 'Ce voyage à Rome est parfait.']);

        $plan = $this->planificateur([$vParis, $vRome], [], $ia)->planifier('une envie de voyage');

        self::assertStringStartsWith('Paris', $plan['pourquoi']);
        self::assertStringNotContainsString('Rome', $plan['pourquoi']);
    }

    public function testUnVoyageInventeParLIABasculeSurLeCalculSansIA(): void
    {
        $v = self::voyage(self::destination('A', 'desc', 2), '500', '+2 months');
        $ia = $this->createMock(FournisseurIA::class);
        $ia->method('isDisponible')->willReturn(true);
        $ia->method('completerJson')->willReturn(['voyage_id' => 12345]);

        $plan = $this->planificateur([$v], [], $ia)->planifier('envie de soleil');

        self::assertSame($v, $plan['voyage']);
        self::assertFalse($plan['ia']);
    }

    // ── Contexte du séjour ──────────────────────────────────────

    public function testResumeMeteo(): void
    {
        $m = ContexteSejourService::resumerMeteo([24, 26], [16, 18], [0, 5], 5);

        self::assertSame(25.0, $m['temp_max']);
        self::assertSame(17.0, $m['temp_min']);
        self::assertSame(50, $m['jours_pluie_pct']);
        self::assertSame('fa-cloud-showers-heavy', $m['icone']);
    }

    public function testContexteAvecApisSimulees(): void
    {
        $appels = [];
        $http = new MockHttpClient(function (string $methode, string $url) use (&$appels) {
            $appels[] = $url;
            if (str_contains($url, 'open-meteo')) {
                // Mêmes dates l'an dernier : 2 jours secs à 30 °C.
                $d1 = (new \DateTimeImmutable('2027-08-01'))->modify('-1 year')->format('Y-m-d');
                $d2 = (new \DateTimeImmutable('2027-08-02'))->modify('-1 year')->format('Y-m-d');
                return new JsonMockResponse(['daily' => [
                    'time' => [$d1, $d2], 'temperature_2m_max' => [30, 30], 'temperature_2m_min' => [20, 22], 'precipitation_sum' => [0, 0],
                ]]);
            }
            if (str_contains($url, 'nager')) {
                return new JsonMockResponse([['date' => '2027-08-01', 'localName' => 'Fête nationale', 'name' => 'National Day']]);
            }
            return new MockResponse('', ['http_code' => 404]);
        });

        $dest = (new Destination())->setNom('X')->setPays('Y')->setLatitude(1.0)->setLongitude(2.0)->setCodePays('fr');
        $ctx  = (new ContexteSejourService($http, new ArrayAdapter(), self::sansIA(), new NullLogger()))
            ->pour($dest, new \DateTimeImmutable('2027-08-01'), new \DateTimeImmutable('2027-08-02'));

        self::assertSame(30.0, $ctx['meteo']['temp_max']);
        self::assertSame(0, $ctx['meteo']['jours_pluie_pct']);
        self::assertSame('Fête nationale', $ctx['feries'][0]['nom']);
        self::assertSame('Élevée', $ctx['affluence']['niveau']); // août + jour férié
        self::assertSame([], $ctx['evenements']);
        self::assertFalse($ctx['evenements_ia']);
        self::assertStringContainsString('/2027/FR', $appels[1]);
    }

    public function testContexteSansCoordonneesNiPaysNeFaitAucunAppel(): void
    {
        $http = new MockHttpClient(fn() => throw new \LogicException('Aucun appel attendu'));
        $ctx  = (new ContexteSejourService($http, new ArrayAdapter(), self::sansIA(), new NullLogger()))
            ->pour((new Destination())->setNom('X')->setPays('Y'), new \DateTimeImmutable('2027-01-10'), new \DateTimeImmutable('2027-01-12'));

        self::assertNull($ctx['meteo']);
        self::assertFalse($ctx['feries_disponibles']);
        self::assertSame('Faible', $ctx['affluence']['niveau']);
    }
}
