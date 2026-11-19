<?php

namespace App\Service\IA;

use App\Entity\Client;
use Psr\Log\LoggerInterface;

/**
 * Chatbot client DreamTravel. Le modèle répond uniquement à partir des outils
 * d'OutilsCatalogue (lecture de la base) : il ne peut pas inventer de voyage ni de prix.
 */
class AssistantVoyage
{
    private const SYSTEME = <<<'TXT'
        Tu es l'assistant en ligne de DreamTravel, une agence de voyages. Tu aides les visiteurs et les clients
        à trouver un voyage, à connaître les places restantes, les prix, les hôtels à destination, et à comprendre
        le programme de fidélité (voucher).

        Règles impératives :
        - Toute information sur les voyages, prix, dates, places, hôtels, sociétés ou le programme voucher doit venir
          des outils. Appelle l'outil adapté avant de répondre ; n'utilise jamais tes connaissances générales pour ces sujets.
        - Si les outils ne renvoient rien, dis-le simplement et propose d'élargir la recherche. N'invente jamais un voyage,
          un prix, une date, une place ou une promotion.
        - Les places restantes changent en temps réel : redonne le chiffre de l'outil et précise qu'il est valable maintenant.
        - Tu ne peux pas réserver, annuler ni payer : indique le lien du voyage (champ "lien") pour réserver sur le site.
        - Pour une question sans rapport avec DreamTravel et le voyage, réponds en une phrase que tu ne peux aider que sur ces sujets.
        - Ne demande jamais d'email, de téléphone, de mot de passe ou de coordonnées bancaires.

        Style : réponds dans la langue du client (français par défaut), de façon courte et chaleureuse, en texte simple
        sans markdown. Pour une liste de voyages, une ligne par voyage :
        titre — destination — dates — prix par personne — places restantes — lien.
        TXT;

    public function __construct(
        private readonly FournisseurIA $ia,
        private readonly OutilsCatalogue $outils,
        private readonly LoggerInterface $logger,
    ) {}

    public function isDisponible(): bool
    {
        return $this->ia->isDisponible();
    }

    /**
     * @param list<array{role: 'user'|'assistant', content: string}> $historique tours précédents (texte seulement)
     */
    public function repondre(array $historique, string $question, ?Client $client): string
    {
        try {
            $reponse = $this->ia->converser(
                self::SYSTEME,
                $historique,
                sprintf("[Date du jour : %s]\n%s", (new \DateTimeImmutable())->format('Y-m-d'), $question),
                $this->outils->definitions($client !== null),
                fn(string $nom, array $entree) => $this->outils->executer($nom, $entree, $client),
            );
            return $reponse !== '' ? $reponse : 'Je n\'ai pas trouvé de réponse. Pouvez-vous reformuler votre question ?';
        } catch (ErreurIA $e) {
            $this->logger->error(sprintf('Chatbot IA (%s) : %s', $this->ia->nom(), $e->getMessage()));

            return match ($e->raison) {
                ErreurIA::QUOTA         => 'L\'assistant a atteint sa limite de questions pour le moment. Réessayez dans une minute.',
                ErreurIA::REFUS         => 'Je ne peux pas répondre à cette demande. Je peux en revanche vous aider à trouver un voyage DreamTravel.',
                ErreurIA::TROP_D_ETAPES => 'Votre question demande trop de recherches. Pouvez-vous la préciser (destination, budget, dates) ?',
                default                 => 'L\'assistant est momentanément indisponible. Vous pouvez consulter nos voyages sur la page Voyages.',
            };
        }
    }
}
