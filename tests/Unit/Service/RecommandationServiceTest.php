<?php

namespace App\Tests\Unit\Service;

use App\Entity\Client;
use App\Entity\Destination;
use App\Entity\Reservation;
use App\Entity\Societe;
use App\Entity\Voyage;
use App\Enum\CompagnieAerienne;
use App\Enum\StatutReservation;
use App\Repository\VoyageRepository;
use App\Service\IA\ClaudeFournisseur;
use App\Service\IA\FournisseurIAChoisi;
use App\Service\IA\GeminiFournisseur;
use App\Service\IA\OutilsCatalogue;
use App\Service\IA\RecommandationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class RecommandationServiceTest extends TestCase
{
    private static int $id = 0;

    private static function voyage(string $prix, int $duree, ?Societe $societe = null, string $titre = 'V'): Voyage
    {
        $depart = new \DateTimeImmutable('+1 month');
        $voyage = (new Voyage())
            ->setTitre($titre)
            ->setDestination((new Destination())->setNom('X')->setPays('Y'))
            ->setDateDepart($depart)
            ->setDateRetour($depart->modify("+$duree days"))
            ->setPrix($prix)
            ->setCompagnieAerienne(CompagnieAerienne::Tunisair)
            ->setNombrePlaces(10)
            ->setSociete($societe);
        (new \ReflectionProperty(Voyage::class, 'id'))->setValue($voyage, ++self::$id);
        return $voyage;
    }

    /** Service sans clé API : le calcul par score est utilisé. */
    private function service(array $catalogue): RecommandationService
    {
        $repo = $this->createMock(VoyageRepository::class);
        $repo->method('findActifs')->willReturn($catalogue);

        return new RecommandationService(
            $repo,
            new FournisseurIAChoisi(new GeminiFournisseur(new MockHttpClient(), null, "m"), new ClaudeFournisseur(null)),
            $this->createMock(OutilsCatalogue::class),
            new ArrayAdapter(),
            new NullLogger(),
        );
    }

    private static function client(Voyage ...$dejaReserves): Client
    {
        $client = new Client();
        (new \ReflectionProperty(\App\Entity\Utilisateur::class, 'id'))->setValue($client, 99);
        foreach ($dejaReserves as $v) {
            $client->addReservation((new Reservation())->setVoyage($v)->setStatut(StatutReservation::Confirmee));
        }
        return $client;
    }

    public function testNeReproposePasUnVoyageDejaReserveNiUnVoyageComplet(): void
    {
        $dejaFait = self::voyage('1000', 7, titre: 'Déjà fait');
        $complet  = self::voyage('1000', 7, titre: 'Complet');
        $complet->reserverPlaces(10);
        $libre    = self::voyage('1000', 7, titre: 'Libre');

        $recos = $this->service([$dejaFait, $complet, $libre])->pourClient(self::client($dejaFait));

        self::assertSame(['Libre'], array_map(fn($r) => $r['voyage']->getTitre(), $recos));
        self::assertFalse($recos[0]['ia']);
    }

    public function testSansIAPrivilegieLaSocieteEtLeBudgetHabituels(): void
    {
        $atlas = (new Societe())->setNom('Atlas Voyages');
        $passe = self::voyage('900', 7, $atlas);

        $memeSociete = self::voyage('950', 7, $atlas, 'Même société');
        $tropCher    = self::voyage('5000', 15, null, 'Trop cher');
        $memeBudget  = self::voyage('1000', 6, null, 'Même budget');

        $recos = $this->service([$tropCher, $memeBudget, $memeSociete])->pourClient(self::client($passe));

        self::assertSame(['Même société', 'Même budget', 'Trop cher'], array_map(fn($r) => $r['voyage']->getTitre(), $recos));
        self::assertStringContainsString('Atlas Voyages', $recos[0]['raison']);
    }

    public function testAucuneClePasDAppelIA(): void
    {
        $gemini = fn(?string $cle) => new GeminiFournisseur(new MockHttpClient(), $cle, 'm');

        self::assertFalse((new FournisseurIAChoisi($gemini(null), new ClaudeFournisseur(null)))->isDisponible());
        self::assertFalse((new FournisseurIAChoisi($gemini('  '), new ClaudeFournisseur('')))->isDisponible());
        // Gemini (gratuit) est prioritaire quand les deux clés sont présentes.
        self::assertStringStartsWith('Google Gemini', (new FournisseurIAChoisi($gemini('AIza-x'), new ClaudeFournisseur('sk-ant-x')))->nom());
        self::assertStringStartsWith('Anthropic', (new FournisseurIAChoisi($gemini(null), new ClaudeFournisseur('sk-ant-x')))->nom());
    }
}
