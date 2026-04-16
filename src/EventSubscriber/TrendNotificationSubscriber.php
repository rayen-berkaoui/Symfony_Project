<?php

namespace App\EventSubscriber;

use App\Entity\Etablissement;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;

class TrendNotificationSubscriber implements EventSubscriberInterface
{
    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        // Notification intelligente : "Un nouvel hôtel vient d'ouvrir dans une ville populaire !"
        if ($entity instanceof Etablissement) {
            if (strtolower($entity->getVille()) === 'tunis' && strtolower($entity->getType()) === 'hotel') {
                // Ici, vous pourriez déclencher Symfony Mailer ou Notifier, SMS, etc.
                
                // TEST VISUEL : On arrête le script pour vous prouver que l'événement a bien été intercepté !
                dd("🤖 NOTIFICATION INTELLIGENTE : Un nouveau super hôtel vient d'être créé à Tunis ! (Retirez ce 'dd' en production)");
            }
        }
    }
}
