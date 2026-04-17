<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Service\BadWordGuard;
use App\Service\NotificationService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class NoBadWordsValidator extends ConstraintValidator
{
    public function __construct(
        private readonly BadWordGuard $badWordGuard,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoBadWords) {
            throw new UnexpectedTypeException($constraint, NoBadWords::class);
        }

        if (null === $value || '' === trim((string) $value)) {
            return;
        }

        $match = $this->badWordGuard->findFirstMatch((string) $value);
        if (null === $match) {
            return;
        }

        $object = $this->context->getObject();
        $source = \is_object($object) ? (new \ReflectionClass($object))->getShortName() : 'content';
        $actorKey = $this->extractActorKey($object);
        $this->notificationService->notifyBadWordDetected($actorKey, $match, $source);

        $this->context
            ->buildViolation($constraint->message)
            ->setParameter('{{ word }}', $match)
            ->addViolation();
    }

    private function extractActorKey(mixed $object): ?string
    {
        if (!\is_object($object)) {
            return null;
        }

        if (method_exists($object, 'getUserKey')) {
            return (string) $object->getUserKey();
        }

        if (method_exists($object, 'getAuthorKey')) {
            return (string) $object->getAuthorKey();
        }

        return null;
    }
}
