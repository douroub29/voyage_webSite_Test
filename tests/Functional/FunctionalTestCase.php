<?php

namespace App\Tests\Functional;

use App\Entity\Utilisateur;
use App\Entity\Voyage;
use App\Repository\UtilisateurRepository;
use App\Repository\VoyageRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Outils communs aux tests HTTP (base de test chargée avec les fixtures). */
abstract class FunctionalTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /** Mots de passe des comptes créés par les fixtures (AppFixtures). */
    private const MOTS_DE_PASSE = ['admin@dreamtravel.com' => 'Admin@1234'];

    /**
     * Connecte un utilisateur par le vrai formulaire /connexion
     * (KernelBrowser::loginUser() ne gère pas le contexte de sécurité partagé "dreamtravels").
     */
    protected function connecter(string $email): Utilisateur
    {
        $crawler = $this->client->request('GET', '/connexion');
        $this->client->submit($crawler->selectButton('Se connecter')->form([
            'email'    => $email,
            'password' => self::MOTS_DE_PASSE[$email] ?? 'Client@1234',
        ]));
        self::assertResponseRedirects(message: 'Connexion de test impossible pour ' . $email);

        return static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => $email]);
    }

    protected function voyage(string $debutTitre): Voyage
    {
        return static::getContainer()->get(VoyageRepository::class)
            ->createQueryBuilder('v')->where('v.titre LIKE :t')->setParameter('t', $debutTitre . '%')
            ->getQuery()->getSingleResult();
    }

    protected function compter(string $sql): int
    {
        return (int) static::getContainer()->get('doctrine.dbal.default_connection')->fetchOne($sql);
    }
}
