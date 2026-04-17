<?php

namespace App\Validation;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Limites et contraintes partagées (contrôle de saisie unique pour formulaires + entités).
 */
final class ValidationLimits
{
    public const CONTENT_MAX = 65_535;

    public const USER_KEY_MAX = 255;

    /** Pseudo / identifiant : lettres (toutes langues), chiffres, _, -, . */
    public const USER_KEY_PATTERN = '/^[\p{L}\p{N}_.\-]{1,255}$/u';

    public const HASHTAGS_INPUT_MAX = 2000;

    public const IMAGE_FILES_MAX = 12;

    public const IMAGE_MAX_SIZE = '5M';

    public const VIDEO_MAX_SIZE = '40M';

    /** Plateformes acceptées pour le journal de partage (alignées sur PostController::shareLog). */
    public const SHARE_PLATFORMS = [
        'whatsapp',
        'facebook',
        'twitter',
        'linkedin',
        'telegram',
        'email',
        'copie_lien',
        'native',
    ];

    /** @return list<Image|Count|All> */
    public static function postImageFilesConstraints(): array
    {
        return [
            new Count([
                'max' => self::IMAGE_FILES_MAX,
                'maxMessage' => 'Vous ne pouvez pas envoyer plus de {{ limit }} image(s) à la fois.',
            ]),
            new All([
                'constraints' => [
                    new Image([
                        'maxSize' => self::IMAGE_MAX_SIZE,
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Chaque fichier doit être une image JPEG, PNG, GIF ou WebP.',
                    ]),
                ],
            ]),
        ];
    }

    public static function postVideoFileConstraint(): File
    {
        return new File([
            'maxSize' => self::VIDEO_MAX_SIZE,
            'mimeTypes' => [
                'video/mp4',
                'video/webm',
                'video/quicktime',
            ],
            'mimeTypesMessage' => 'Choisissez une vidéo MP4, WebM ou MOV ({{ limit }} max).',
            'maxSizeMessage' => 'La vidéo doit faire au plus {{ limit }} {{ suffix }}.',
        ]);
    }

    public static function hashtagsFieldConstraints(): array
    {
        return [
            new Length([
                'max' => self::HASHTAGS_INPUT_MAX,
                'maxMessage' => 'Le champ hashtags ne peut pas dépasser {{ limit }} caractères.',
            ]),
        ];
    }

    /** @return list<NotBlank|Length|Regex> */
    public static function userKeyValueConstraints(): array
    {
        return [
            new NotBlank(message: 'L’identifiant est obligatoire.'),
            new Length(max: self::USER_KEY_MAX, maxMessage: 'L’identifiant ne peut pas dépasser {{ limit }} caractères.'),
            new Regex(
                pattern: self::USER_KEY_PATTERN,
                message: 'L’identifiant ne doit contenir que des lettres, chiffres, _, - ou .'
            ),
        ];
    }
}
