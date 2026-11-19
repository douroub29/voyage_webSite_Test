<?php

namespace App\Tests\Unit\Validation;

use App\Entity\Destination;
use App\Entity\Voyage;
use App\Enum\CompagnieAerienne;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class VoyageValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    private static function voyageValide(): Voyage
    {
        $depart = new \DateTimeImmutable('+1 month');

        return (new Voyage())
            ->setTitre('Djerba')
            ->setDescription('Séjour à Djerba')
            ->setDestination(new Destination())
            ->setDateDepart($depart)
            ->setDateRetour($depart->modify('+7 days'))
            ->setPrix('799.00')
            ->setCompagnieAerienne(CompagnieAerienne::Tunisair)
            ->setNumeroVol('TU 712')
            ->setNombrePlaces(150);
    }

    /** @return string[] chemins des propriétés en erreur */
    private function erreurs(Voyage $voyage): array
    {
        $chemins = [];
        foreach ($this->validator->validate($voyage) as $violation) {
            $chemins[] = $violation->getPropertyPath();
        }
        return $chemins;
    }

    public function testUnVoyageCompletEstValide(): void
    {
        self::assertSame([], $this->erreurs(self::voyageValide()));
    }

    public function testLaDateDeRetourDoitEtreApresLeDepart(): void
    {
        $voyage = self::voyageValide();
        $voyage->setDateRetour($voyage->getDateDepart());

        self::assertContains('dateRetour', $this->erreurs($voyage));
    }

    public function testUnNouveauVoyageNePeutPasPartirDansLePasse(): void
    {
        $voyage = self::voyageValide()
            ->setDateDepart(new \DateTimeImmutable('-2 days'))
            ->setDateRetour(new \DateTimeImmutable('+5 days'));

        self::assertContains('dateDepart', $this->erreurs($voyage));
    }

    public function testLaCompagnieEstObligatoire(): void
    {
        $voyage = self::voyageValide()->setCompagnieAerienne(null)->setNumeroVol(null);

        self::assertContains('compagnieAerienne', $this->erreurs($voyage));
    }

    #[DataProvider('capacitesInvalides')]
    public function testCapaciteDeLAvionHorsLimites(int $places): void
    {
        $voyage = self::voyageValide()->setNombrePlaces($places);

        self::assertContains('nombrePlaces', $this->erreurs($voyage));
    }

    public static function capacitesInvalides(): iterable
    {
        yield 'zéro'           => [0];
        yield 'négatif'        => [-10];
        yield 'au-delà du max' => [Voyage::CAPACITE_MAX_AVION + 1];
    }

    public function testCapaciteInferieureAuxPlacesReserveesEstRefusee(): void
    {
        $voyage = self::voyageValide();
        $voyage->reserverPlaces(100);

        $voyage->setNombrePlaces(80);

        self::assertContains('nombrePlaces', $this->erreurs($voyage));
    }

    #[DataProvider('numerosDeVol')]
    public function testFormatDuNumeroDeVol(CompagnieAerienne $compagnie, string $numero, bool $valide): void
    {
        $voyage = self::voyageValide()->setCompagnieAerienne($compagnie)->setNumeroVol($numero);

        self::assertSame($valide, !\in_array('numeroVol', $this->erreurs($voyage), true));
    }

    public static function numerosDeVol(): iterable
    {
        yield 'Tunisair avec espace'      => [CompagnieAerienne::Tunisair, 'TU 712', true];
        yield 'Tunisair sans espace'      => [CompagnieAerienne::Tunisair, 'TU712', true];
        yield 'Nouvelair'                 => [CompagnieAerienne::Nouvelair, 'BJ 302', true];
        yield 'mauvais préfixe compagnie' => [CompagnieAerienne::Tunisair, 'AF 1234', false];
        yield 'trop de chiffres'          => [CompagnieAerienne::Tunisair, 'TU 12345', false];
        yield 'lettres à la place'        => [CompagnieAerienne::Tunisair, 'TU ABC', false];
    }
}
