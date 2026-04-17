<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\NotificationRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tbn_notification_unread_count', [$this, 'getUnreadCount']),
        ];
    }

    public function getUnreadCount(): int
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return 0;
        }

        $key = trim((string) ($request->getSession()->get('connect_user_key') ?? ''));
        if ('' === $key) {
            return 0;
        }

        return $this->notificationRepository->countUnreadForRecipient($key);
    }
}
