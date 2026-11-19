<?php

namespace App\Service;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/** Génère le jeton d'activation d'un compte et envoie l'email correspondant. */
class ActivationMailer
{
    private const EXPEDITEUR = 'haddaddouroub@gmail.com';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
        private readonly EntityManagerInterface $em,
    ) {}

    public function envoyer(Utilisateur $user): void
    {
        $token = bin2hex(random_bytes(32));
        $user->setVerificationToken($token);
        $user->setIsVerified(false);
        $this->em->flush();

        $lienActivation = $this->urlGenerator->generate(
            'app_verify_email',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new Email())
            ->from(new Address(self::EXPEDITEUR, 'DreamTravel'))
            ->replyTo(self::EXPEDITEUR)
            ->to($user->getEmail())
            ->priority(Email::PRIORITY_HIGH)
            ->subject('Activez votre compte DreamTravel')
            ->html($this->twig->render('emails/verification.html.twig', [
                'client'         => $user,
                'lienActivation' => $lienActivation,
            ]));

        $this->mailer->send($email);
    }

    /** Active le compte correspondant au jeton. Renvoie false si le jeton est invalide. */
    public function activer(?Utilisateur $user): bool
    {
        if (!$user) {
            return false;
        }
        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $this->em->flush();

        return true;
    }
}
