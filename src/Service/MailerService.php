<?php

namespace App\Service;

use App\Entity\Utilisateur;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class MailerService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger
    ) {
    }

    public function sendWelcomeEmail(Utilisateur $utilisateur): void
    {
        try {
            $email = (new TemplatedEmail())
                ->from('dragona.berkaoui@gmail.com')
                ->to($utilisateur->getEmail())
                ->subject('Welcome to Tabaany!')
                ->htmlTemplate('email/welcome.html.twig')
                ->context([
                    'utilisateur' => $utilisateur,
                    'prenom' => $utilisateur->getPrenom() ?? $utilisateur->getNom() ?? 'User',
                ]);

            $this->mailer->send($email);
            $this->logger->info('Welcome email sent successfully to ' . $utilisateur->getEmail());
        } catch (\Exception $e) {
            $this->logger->error('Failed to send welcome email to ' . $utilisateur->getEmail() . ': ' . $e->getMessage());
            throw $e;
        }
    }
}
