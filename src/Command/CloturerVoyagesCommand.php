<?php

namespace App\Command;

use App\Service\VoyageClotureService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Clôture les voyages dont la date de départ est atteinte.
 * Cette clôture est déjà faite automatiquement à chaque visite du site
 * (VoyageClotureSubscriber) ; la commande reste utile en tâche planifiée :
 *   php bin/console app:voyages:cloturer
 */
#[AsCommand(name: 'app:voyages:cloturer', description: 'Désactive les voyages partis et annule leurs réservations non payées')]
class CloturerVoyagesCommand extends Command
{
    public function __construct(private readonly VoyageClotureService $clotureService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait fait sans rien modifier');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $resultat = $this->clotureService->cloturer(dryRun: $dryRun);
        if (!$resultat) {
            $io->success('Aucun voyage à clôturer.');
            return Command::SUCCESS;
        }

        $lignes = array_map(fn(array $l) => [
            $l['voyage']->getId(),
            $l['voyage']->getTitre(),
            $l['voyage']->getDateDepart()->format('d/m/Y'),
            $l['annulees'],
        ], $resultat);
        $io->table(['ID', 'Voyage', 'Départ', 'Résa. en attente annulées'], $lignes);

        if ($dryRun) {
            $io->note('Mode --dry-run : aucune modification enregistrée.');
            return Command::SUCCESS;
        }

        $io->success(sprintf(
            '%d voyage(s) clôturé(s), %d réservation(s) en attente annulée(s).',
            count($resultat),
            array_sum(array_column($resultat, 'annulees'))
        ));

        return Command::SUCCESS;
    }
}
