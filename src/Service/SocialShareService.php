<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ChatBan;
use App\Entity\Post;
use App\Entity\Share;
use App\Repository\ChatBanRepository;
use App\Validation\ValidationLimits;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Partage réseaux sociaux : texte court + journalisation (table shares) — utilisé par le site et l’API v1.
 */
final class SocialShareService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ChatBanRepository $chatBanRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Données pour boutons de partage (même logique que l’affichage publication).
     *
     * @return array{
     *   post_id: int,
     *   post_public_url: string,
     *   share_plain: string,
     *   wa_text: string,
     *   mail_subject: string,
     *   mail_body: string
     * }
     */
    public function buildSharePageData(Post $post, string $postPublicUrl): array
    {
        $sharePlain = $this->extractSharePlain($post);

        return [
            'post_id' => $post->getId(),
            'post_public_url' => $postPublicUrl,
            'share_plain' => $sharePlain,
            'wa_text' => $sharePlain.' '.$postPublicUrl,
            'mail_subject' => 'Tabaani Connect — Publication #'.$post->getId(),
            'mail_body' => $sharePlain."\n\n".$postPublicUrl,
        ];
    }

    /**
     * Manifeste API : structure pour clients / documentation.
     *
     * @return array<string, mixed>
     */
    public function buildApiShareManifest(Post $post, UrlGeneratorInterface $urlGenerator): array
    {
        $postPublicUrl = $urlGenerator->generate('app_post_show', ['id' => $post->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $base = $this->buildSharePageData($post, $postPublicUrl);

        return [
            'post_id' => $base['post_id'],
            'canonical_url' => $base['post_public_url'],
            'text' => [
                'plain' => $base['share_plain'],
                'note' => 'Texte dérivé du contenu (HTML retiré), tronqué à 160 caractères pour les aperçus.',
            ],
            'presets' => [
                'whatsapp' => ['text' => $base['wa_text']],
                'email' => [
                    'subject' => $base['mail_subject'],
                    'body' => $base['mail_body'],
                ],
            ],
            'platforms_allowed' => ValidationLimits::SHARE_PLATFORMS,
            'log_endpoint' => [
                'method' => 'POST',
                'path_template' => '/api/v1/posts/{postId}/share/events',
                'body' => [
                    'platform' => 'string (voir platforms_allowed)',
                    '_token' => 'jeton CSRF (share_log{postId})',
                ],
            ],
        ];
    }

    /**
     * @return array{ok: true, logged: bool}|array{ok: false, error: string}
     */
    public function recordShareLog(Post $post, string $userKey, string $platform): array
    {
        $platform = strtolower(trim($platform));
        if (!\in_array($platform, ValidationLimits::SHARE_PLATFORMS, true)) {
            return ['ok' => false, 'error' => 'invalid_platform'];
        }

        if ('' === $userKey) {
            return ['ok' => true, 'logged' => false];
        }

        if ($this->chatBanRepository->isUserCurrentlyBannedForScope($userKey, ChatBan::SCOPE_SHARES)) {
            return ['ok' => false, 'error' => 'banned_for_shares'];
        }

        $share = new Share();
        $share->setPost($post);
        $share->setUserKey($userKey);
        $share->setPlatform($platform);

        $violations = $this->validator->validate($share);
        if (\count($violations) > 0) {
            return ['ok' => false, 'error' => 'validation'];
        }

        $this->entityManager->persist($share);
        $this->entityManager->flush();

        return ['ok' => true, 'logged' => true];
    }

    private function extractSharePlain(Post $post): string
    {
        $decoded = html_entity_decode(strip_tags((string) $post->getContent()), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $sharePlain = trim((string) preg_replace('/\s+/u', ' ', $decoded));
        if (mb_strlen($sharePlain, 'UTF-8') > 160) {
            $sharePlain = mb_substr($sharePlain, 0, 160, 'UTF-8').'…';
        }

        return $sharePlain;
    }
}
