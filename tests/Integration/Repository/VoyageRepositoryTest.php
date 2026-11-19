<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Voyage;
use App\Repository\VoyageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Requêtes DQL réelles sur la base de test (fixtures).
 * Chaque test est annulé à la fin (DAMA DoctrineTestBundle).
 */
class VoyageRepositoryTest extends KernelTestCase
{
    private VoyageRepository $repo;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em   = self::getContainer()->get(EntityManagerInterface::class);
        $this->repo = self::getContainer()->get(VoyageRepository::class);
    }

    private static function titres(array $voyages): array
    {
        $titres = array_map(fn(Voyage $v) => $v->getTitre(), $voyages);
        sort($titres);
        return $titres;
    }

    public function testRechercheParNomDeSociete(): void
    {
        self::assertSame(
            ['Dubaï Luxury Experience', 'Escapade Mystique à Marrakech'],
            self::titres($this->repo->search(null, null, null, 'atlas')) // insensible à la casse, nom partiel
        );
        self::assertSame([], $this->repo->search(null, null, null, 'Société inexistante'));
    }

    public function testRechercheCombineDestinationBudgetEtSociete(): void
    {
        self::assertSame(['Bali : L\'Île des Dieux'], self::titres($this->repo->search('Bali', null, null, 'Globe')));
        self::assertSame([], $this->repo->search('Bali', null, null, 'Atlas'));

        $petitBudget = $this->repo->search(null, null, 1300.0);
        self::assertSame(['Escapade Mystique à Marrakech', 'Week-end Romantique à Paris'], self::titres($petitBudget));
    }

    public function testLesVoyagesInactifsOuPartisNeSontPasProposes(): void
    {
        $paris = $this->repo->findOneBy(['titre' => 'Week-end Romantique à Paris']);
        $paris->setActif(false);

        $rome = $this->repo->findOneBy(['titre' => 'Rome Éternelle — Circuit Culturel']);
        $rome->setDateDepart(new \DateTime('yesterday'));
        $this->em->flush();

        $titres = self::titres($this->repo->search(null, null, null));
        self::assertNotContains('Week-end Romantique à Paris', $titres);
        self::assertNotContains('Rome Éternelle — Circuit Culturel', $titres);
        self::assertCount(4, $titres);

        self::assertSame(
            ['Rome Éternelle — Circuit Culturel'],
            self::titres($this->repo->findActifsDepartAtteint(new \DateTimeImmutable('today')))
        );
    }
}
