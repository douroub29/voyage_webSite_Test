<?php

namespace App\DataFixtures;

use App\Entity\Admin;
use App\Entity\Avis;
use App\Entity\Chauffeur;
use App\Entity\Client;
use App\Entity\Destination;
use App\Entity\Hotel;
use App\Entity\Reservation;
use App\Entity\Societe;
use App\Entity\Voyage;
use App\Enum\CompagnieAerienne;
use App\Enum\StatutAvis;
use App\Enum\StatutReservation;
use App\Enum\TypeTransfert;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // ── ADMIN ──────────────────────────────────────────────────────
        $admin = new Admin();
        $admin->setNom('Dupont')
              ->setPrenom('Marie')
              ->setEmail('admin@dreamtravel.com')
              ->setPoste('Directrice')
              ->setIsVerified(true)
              ->setMotDePasse($this->hasher->hashPassword($admin, 'Admin@1234'));
        $manager->persist($admin);

        // ── DESTINATIONS ───────────────────────────────────────────────
        $destinations = [];
        foreach ([
            ['Paris',      'France',   'La ville lumière, capitale de l\'amour et de la gastronomie. De la Tour Eiffel aux Champs-Élysées, Paris enchante tous ses visiteurs.'],
            ['Marrakech',  'Maroc',    'La ville rouge, aux mille et une couleurs et saveurs. Souks, riads et palmeraies vous attendent dans cette cité magique.'],
            ['Bali',       'Indonésie','L\'île des dieux, paradis tropical de sérénité. Rizières en terrasses, temples hindous et plages de sable volcanique.'],
            ['New York',   'États-Unis','La grande pomme, ville qui ne dort jamais. Manhattan, Central Park et la Statue de la Liberté vous accueillent.'],
            ['Dubaï',      'Émirats arabes','La ville du futur, luxe et architecture époustouflante. Le Burj Khalifa et les souks dorés définissent cette métropole unique.'],
            ['Rome',       'Italie',   'La ville éternelle, berceau de la civilisation occidentale. Le Colisée, la Fontaine de Trevi et la gastronomie italienne vous attendent.'],
        ] as [$nom, $pays, $desc]) {
            $dest = new Destination();
            $dest->setNom($nom)->setPays($pays)->setDescription($desc);
            $manager->persist($dest);
            $destinations[] = $dest;
        }

        // ── HÔTELS ─────────────────────────────────────────────────────
        $hotels = [];
        foreach ([
            ['Hôtel Lutetia',         5, 450.00, 0, 'Palace emblématique de Saint-Germain-des-Prés, symbole de l\'élégance parisienne depuis 1910.'],
            ['Riad La Rose',          4, 180.00, 1, 'Riad traditionnel au cœur de la médina, patio fleuri et cuisine marocaine authentique.'],
            ['Ubud Cliff Resort',     5, 320.00, 2, 'Resort perché sur les falaises de la forêt d\'Ubud, piscines à débordement et vue spectaculaire.'],
            ['The New Yorker Hotel',  4, 290.00, 3, 'Hôtel art déco iconique au cœur de Manhattan, à deux pas de Times Square.'],
            ['Jumeirah Al Qasr',      5, 950.00, 4, 'Resort de luxe sur la plage de Dubaï, architecture majestueuse inspirée des palais arabes.'],
            ['Hotel de Russie',       5, 520.00, 5, 'Splendide hôtel situé entre la Piazza del Popolo et la Via Veneto, jardin secret exceptionnel.'],
        ] as [$nom, $etoiles, $prix, $idx, $desc]) {
            $hotel = new Hotel();
            $hotel->setNom($nom)
                  ->setEtoiles($etoiles)
                  ->setPrixParNuit((string) $prix)
                  ->setDestination($destinations[$idx])
                  ->setAdresse('Centre-ville, ' . $destinations[$idx]->getNom())
                  ->setDescription($desc);
            $manager->persist($hotel);
            $hotels[] = $hotel;
        }

        // ── SOCIÉTÉS DE VOYAGE ─────────────────────────────────────────
        $societes = [];
        foreach (['Horizon Évasion', 'Atlas Voyages', 'Globe Trotter Agency'] as $nomSociete) {
            $societe = (new Societe())->setNom($nomSociete);
            $manager->persist($societe);
            $societes[] = $societe;
        }

        // ── VOYAGES ────────────────────────────────────────────────────
        $voyages = [];
        $premierDepart = new \DateTimeImmutable('first day of next month');
        foreach ([
            ['Week-end Romantique à Paris',       0, 0, 7, 1290.00, 20, CompagnieAerienne::Tunisair,        'TU 712',  0, 'paris'],
            ['Escapade Mystique à Marrakech',     1, 1, 7,  890.00, 15, CompagnieAerienne::RoyalAirMaroc,   'AT 573',  1, 'marrakech'],
            ['Bali : L\'Île des Dieux',           2, 2, 14, 2490.00, 12, CompagnieAerienne::QatarAirways,    'QR 1104', 2, 'bali'],
            ['New York City — The Big Apple',     3, 3, 10, 3200.00, 18, CompagnieAerienne::AirFrance,       'AF 006',  2, 'new-york'],
            ['Dubaï Luxury Experience',           4, 4, 7, 2800.00, 10, CompagnieAerienne::Emirates,        'EK 748',  1, 'dubai'],
            ['Rome Éternelle — Circuit Culturel', 5, 5, 8, 1450.00, 25, CompagnieAerienne::Nouvelair,       'BJ 302',  0, 'rome'],
        ] as [$titre, $destIdx, $moisApresDepart, $duree, $prix, $places, $compagnie, $numeroVol, $societeIdx, $photo]) {
            $depart = $premierDepart->modify(sprintf('+%d months', $moisApresDepart));
            $voyage = new Voyage();
            $voyage->setTitre($titre)
                   ->setDestination($destinations[$destIdx])
                   ->setDescription('Un voyage inoubliable à ' . $destinations[$destIdx]->getNom() . '. Découvrez les merveilles de cette destination avec nos experts. Hébergement de luxe, visites guidées exclusives et expériences gastronomiques inclus dans ce programme soigneusement conçu pour vous.')
                   // Les colonnes "date" de Doctrine attendent un \DateTime (mutable).
                   ->setDateDepart(\DateTime::createFromImmutable($depart))
                   ->setDateRetour(\DateTime::createFromImmutable($depart->modify(sprintf('+%d days', $duree))))
                   ->setPrix((string) $prix)
                   ->setNombrePlaces($places)
                   ->setCompagnieAerienne($compagnie)
                   ->setNumeroVol($numeroVol)
                   ->setSociete($societes[$societeIdx])
                   ->setPhoto('voyages/' . $photo . '.jpg')
                   ->setActif(true);
            $manager->persist($voyage);
            $voyages[] = $voyage;
        }

        // ── CHAUFFEURS ─────────────────────────────────────────────────
        $chauffeurs = [];
        foreach ([
            ['Karim',   'Benali',  '0612345678', 'Mercedes Classe E — Noir',   80.00],
            ['Thomas',  'Moreau',  '0623456789', 'BMW Série 5 — Gris Anthracite', 90.00],
            ['Rachid',  'Alaoui',  '0634567890', 'Audi A6 — Argent',           85.00],
            ['Antoine', 'Leblanc', '0645678901', 'Tesla Model S — Blanc',     100.00],
        ] as [$prenom, $nom, $tel, $voiture, $prix]) {
            $c = new Chauffeur();
            $c->setPrenom($prenom)->setNom($nom)->setTelephone($tel)
              ->setVoiture($voiture)->setPrixTransfert((string) $prix)->setActif(true);
            $manager->persist($c);
            $chauffeurs[] = $c;
        }

        // ── CLIENTS ────────────────────────────────────────────────────
        $clients = [];
        foreach ([
            ['Sophie',  'Martin',  'sophie@example.com',  '0611111111'],
            ['Lucas',   'Bernard', 'lucas@example.com',   '0622222222'],
            ['Camille', 'Leroy',   'camille@example.com', '0633333333'],
            ['Emma',    'Petit',   'emma@example.com',    '0644444444'],
        ] as [$prenom, $nom, $email, $tel]) {
            $client = new Client();
            $client->setPrenom($prenom)->setNom($nom)->setEmail($email)->setTelephone($tel)
                   ->setIsVerified(true)
                   ->setMotDePasse($this->hasher->hashPassword($client, 'Client@1234'));
            $manager->persist($client);
            $clients[] = $client;
        }

        $manager->flush();

        // ── RÉSERVATIONS ───────────────────────────────────────────────
        foreach ([
            [$clients[0], $voyages[0], $hotels[0], $chauffeurs[0], 2,  3, TypeTransfert::AeroportHotel, StatutReservation::Confirmee],
            [$clients[1], $voyages[1], $hotels[1], null,           1,  7, null,                          StatutReservation::EnAttente],
            [$clients[2], $voyages[2], null,        $chauffeurs[1], 4, null, TypeTransfert::LesDeux,     StatutReservation::Confirmee],
            [$clients[3], $voyages[3], $hotels[3], $chauffeurs[2], 2, 10, TypeTransfert::HotelAeroport, StatutReservation::EnAttente],
            [$clients[0], $voyages[4], $hotels[4], $chauffeurs[3], 2,  7, TypeTransfert::LesDeux,       StatutReservation::Annulee],
        ] as [$client, $voyage, $hotel, $chauffeur, $nbPers, $nbNuits, $typeTransfert, $statut]) {
            $r = new Reservation();
            $r->setClient($client)->setVoyage($voyage)->setNombrePersonnes($nbPers)->setStatut($statut);
            if ($hotel) $r->setHotel($hotel)->setNombreNuits($nbNuits);
            if ($chauffeur) $r->setChauffeur($chauffeur)->setTypeTransfert($typeTransfert);
            $r->setPrixTotal((string) $r->calculerPrixTotal());
            if ($statut !== StatutReservation::Annulee) {
                $voyage->reserverPlaces($nbPers);
            }
            $manager->persist($r);
        }

        // ── AVIS ───────────────────────────────────────────────────────
        foreach ([
            [$clients[0], $hotels[0], null,          5, 'Séjour exceptionnel ! Le personnel est aux petits soins. Je recommande vivement.', StatutAvis::Publie],
            [$clients[1], $hotels[1], null,          4, 'Riad magnifique, le petit-déjeuner sur la terrasse est un moment magique.', StatutAvis::Publie],
            [$clients[2], null,       $chauffeurs[0],5, 'Chauffeur très professionnel et ponctuel. Voiture impeccable.', StatutAvis::Publie],
            [$clients[3], $hotels[3], null,          4, 'Hôtel bien situé à Manhattan. Vue imprenable sur la skyline.', StatutAvis::EnAttente],
        ] as [$client, $hotel, $chauffeur, $note, $commentaire, $statut]) {
            $avis = new Avis();
            $avis->setClient($client)->setNote($note)->setCommentaire($commentaire)->setStatut($statut);
            if ($hotel) $avis->setHotel($hotel);
            if ($chauffeur) $avis->setChauffeur($chauffeur);
            $manager->persist($avis);
        }

        $manager->flush();
    }
}
