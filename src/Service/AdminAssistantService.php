<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ChatBanRepository;
use App\Repository\ChatMessageRepository;
use App\Repository\CommentRepository;
use App\Repository\NotificationRepository;
use App\Repository\PostRepository;
use Symfony\Component\HttpFoundation\RequestStack;

final class AdminAssistantService
{
    public function __construct(
        private readonly AdminAnalyticsService $analytics,
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly ChatBanRepository $chatBanRepository,
        private readonly ChatMessageRepository $chatMessageRepository,
        private readonly NotificationRepository $notificationRepository,
        private readonly AdminBriefingService $briefing,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function answer(string $question): array
    {
        $q = mb_strtolower(trim($question), 'UTF-8');
        $snap = $this->buildSnapshot();

        if ('' === $q) {
            return $this->pack(
                'empty',
                'low',
                'Question requise',
                'Saisissez une question ou choisissez une suggestion ci-dessous.',
                'Posez une question sur l’activité, la modération ou les tendances.',
                $this->defaultRelated(),
                [],
                [
                    'Synthèse instantanée à partir des tables Doctrine (agrégations SQL / DQL).',
                    'Tendances : comparaison glissante 7 jours / 7 jours précédents.',
                    'Aucune clé API externe : logique métier et heuristiques locales.',
                ],
                null,
                []
            );
        }

        if ($this->briefing->isWhatsNewQuestion($q)) {
            $req = $this->requestStack->getCurrentRequest();
            if (null !== $req && $req->hasSession()) {
                return $this->briefing->buildWhatsNewAssistantPayload($snap);
            }
        }

        if ($this->hasAny($q, ['aide', 'help', 'que peux', 'que peut', 'comment utiliser', 'fonction', 'كيف', 'مساعدة'])) {
            return $this->helpResponse($snap);
        }

        if ($this->hasAny($q, ['alerte', 'bad word', 'grossier', 'insulte', 'mot interdit', 'validation admin', 'تنبيه', 'كلمات'])) {
            return $this->badWordAlertsResponse($snap);
        }

        if ($this->hasAny($q, ['ban', 'bloqu', 'blocage', 'modération', 'sanction'])) {
            return $this->bansResponse($snap);
        }

        if ($this->hasAny($q, ['hashtag', 'tendance', '# ', ' hash'])) {
            return $this->hashtagsResponse($snap);
        }

        if ($this->hasAny($q, ['commentaire', 'comment'])) {
            return $this->commentsResponse($snap);
        }

        if ($this->hasAny($q, ['publication', 'post', 'publier'])) {
            return $this->postsResponse($snap);
        }

        if ($this->hasAny($q, ['partage', 'share', 'مشاركة'])) {
            return $this->sharesResponse($snap);
        }

        if ($this->hasAny($q, ['réaction', 'reaction', 'like', 'dislike', 'j\'aime', 'تفاعل'])) {
            return $this->reactionsResponse($snap);
        }

        if ($this->hasAny($q, ['chat', 'discussion', 'salon', 'شات'])) {
            return $this->chatResponse($snap);
        }

        if ($this->hasAny($q, ['plus comment', 'débat', 'populaire', 'engagement', 'الأكثر'])) {
            return $this->mostCommentedResponse($snap);
        }

        if ($this->hasAny($q, ['actif', 'qui comment', 'top comment', 'مساهم'])) {
            return $this->topCommentersResponse($snap);
        }

        if ($this->hasAny($q, ['activité', 'courbe', 'graphique', '7 jour', '30 jour', 'tendance temporel'])) {
            return $this->activityResponse($snap);
        }

        if ($this->hasAny($q, ['résumé', 'synthèse', 'état', 'vue d\'ensemble', 'dashboard', 'situation'])) {
            return $this->fullSummaryResponse($snap);
        }

        return $this->fallbackResponse($snap);
    }

    public function buildSnapshot(): array
    {
        $daily = $this->analytics->getDailyActivityLastDays(30);
        $postsSeries = $daily['posts'];
        $commentsSeries = $daily['comments'];

        $sumLast7 = static function (array $series): int {
            $slice = \array_slice($series, -7, 7);
            $n = 0;
            foreach ($slice as $row) {
                $n += (int) ($row['count'] ?? 0);
            }

            return $n;
        };

        $sumPrev7 = static function (array $series): int {
            $slice = \array_slice($series, -14, 7);
            $n = 0;
            foreach ($slice as $row) {
                $n += (int) ($row['count'] ?? 0);
            }

            return $n;
        };

        $posts7 = $sumLast7($postsSeries);
        $postsPrev7 = $sumPrev7($postsSeries);
        $comments7 = $sumLast7($commentsSeries);
        $commentsPrev7 = $sumPrev7($commentsSeries);

        $hourly = $this->analytics->getHourlyPostsToday();
        $todayPosts = \array_sum($hourly);

        $reactions = $this->analytics->getPostReactionTotals();
        $like = (int) $reactions['like'];
        $dis = (int) $reactions['dislike'];
        $reactTotal = max(1, $like + $dis);
        $likeRatio = round(100 * $like / $reactTotal, 1);

        $badUnread = \count($this->notificationRepository->findUnreadBadWordAlertsForAdmin(200));

        return [
            'post_total' => $this->postRepository->count([]),
            'comment_total' => $this->commentRepository->count([]),
            'distinct_hashtags' => $this->analytics->countDistinctHashtags(),
            'share_rows' => $this->analytics->getTotalShareRows(),
            'ban_active' => $this->chatBanRepository->countCurrentlyActiveBans(),
            'chat_messages_total' => $this->chatMessageRepository->count([]),
            'posts_today' => $todayPosts,
            'posts_last_7_days' => $posts7,
            'posts_prev_7_days' => $postsPrev7,
            'comments_last_7_days' => $comments7,
            'comments_prev_7_days' => $commentsPrev7,
            'like' => $like,
            'dislike' => $dis,
            'like_ratio_pct' => $likeRatio,
            'share_activity' => $reactions['share_activity'],
            'warnings' => $this->analytics->getWarningBanBreakdown(),
            'top_hashtags' => $this->analytics->getTopHashtags(8),
            'most_commented' => $this->analytics->getMostCommentedPosts(5),
            'top_commenters' => $this->analytics->getTopCommenters(6),
            'daily_posts_series' => $postsSeries,
            'daily_comments_series' => $commentsSeries,
            'bad_word_unread' => $badUnread,
            'moderation_load' => $this->computeModerationLoad($badUnread, (int) $this->chatBanRepository->countCurrentlyActiveBans()),
        ];
    }

    private function computeModerationLoad(int $alerts, int $activeBans): string
    {
        $score = min(100, $alerts * 12 + $activeBans * 3);
        if ($score >= 55) {
            return 'élevée';
        }
        if ($score >= 20) {
            return 'modérée';
        }

        return 'faible';
    }

    private function trendDelta(int $current, int $previous): array
    {
        if (0 === $previous && 0 === $current) {
            return ['dir' => 'flat', 'label' => 'stable', 'pct' => null];
        }
        if (0 === $previous) {
            return ['dir' => 'up', 'label' => 'nouvelle dynamique', 'pct' => null];
        }
        $pct = round(100 * ($current - $previous) / $previous, 1);
        if ($pct > 1.0) {
            return ['dir' => 'up', 'label' => '+'.$pct.' % vs période préc.', 'pct' => $pct];
        }
        if ($pct < -1.0) {
            return ['dir' => 'down', 'label' => $pct.' % vs période préc.', 'pct' => $pct];
        }

        return ['dir' => 'flat', 'label' => 'stable', 'pct' => $pct];
    }

    private function hasAny(string $q, array $needles): bool
    {
        foreach ($needles as $n) {
            if (str_contains($q, mb_strtolower($n, 'UTF-8'))) {
                return true;
            }
        }

        return false;
    }

    private function pack(
        string $intent,
        string $confidence,
        string $headline,
        string $reply,
        ?string $subhead,
        array $related,
        array $metrics,
        array $insights,
        ?string $detail,
        array $lists,
    ): array {
        return [
            'reply' => $reply,
            'intent' => $intent,
            'confidence' => $confidence,
            'related' => $related,
            'envelope' => [
                'headline' => $headline,
                'subhead' => $subhead,
                'metrics' => $metrics,
                'insights' => $insights,
                'detail' => $detail,
                'lists' => $lists,
            ],
        ];
    }

    private function helpResponse(array $snap): array
    {
        $trP = $this->trendDelta((int) $snap['posts_last_7_days'], (int) $snap['posts_prev_7_days']);
        $trC = $this->trendDelta((int) $snap['comments_last_7_days'], (int) $snap['comments_prev_7_days']);

        $metrics = [
            [
                'key' => 'posts7',
                'label' => 'Publications / 7 j.',
                'value' => (int) $snap['posts_last_7_days'],
                'trend' => $trP['dir'],
                'trend_label' => $trP['label'],
            ],
            [
                'key' => 'com7',
                'label' => 'Commentaires / 7 j.',
                'value' => (int) $snap['comments_last_7_days'],
                'trend' => $trC['dir'],
                'trend_label' => $trC['label'],
            ],
            [
                'key' => 'mod',
                'label' => 'Charge modération',
                'value' => (string) $snap['moderation_load'],
                'hint' => 'heuristique locale',
            ],
        ];

        $insights = [
            'Posez des questions ciblées : « synthèse dashboard », « tendance publications », « alertes bad words ».',
            'Les pourcentages de tendance comparent la fenêtre glissante actuelle à la fenêtre précédente (même durée).',
        ];

        return $this->pack(
            'help',
            'high',
            'Mode opératoire — Assistant décisionnel',
            'Assistant basé sur données internes : tendances 7+7 jours, KPIs et recommandations. Aucun modèle cloud tiers.',
            'Système expert + agrégations Doctrine',
            $this->defaultRelated(),
            $metrics,
            $insights,
            null,
            [
                [
                    'title' => 'Exemples de requêtes',
                    'items' => [
                        '« Synthèse complète » — vue agrégée multi-indicateurs',
                        '« Tendance commentaires » — dynamique récente',
                        '« Top hashtags » — classement fréquentiel',
                        '« Pression modération » — alertes & blocages',
                    ],
                ],
            ]
        );
    }

    private function badWordAlertsResponse(array $snap): array
    {
        $n = (int) ($snap['bad_word_unread'] ?? 0);
        $headline = 0 === $n ? 'File d’alertes — vide' : 'File d’alertes — action requise';
        $reply = 0 === $n
            ? 'Aucune alerte lexicale en attente. La file est synchronisée avec la table notifications (type bad_word_detected).'
            : "{$n} notification(s) non lue(s) destinées à l’admin. Prioriser la revue dans Bloqués.";

        $insights = [];
        if ($n > 0) {
            $insights[] = 'Traiter les alertes dans l’ordre chronologique inverse (les plus récentes peuvent refléter un comportement actif).';
            $insights[] = 'Après décision (blocage confirmé ou ignoré), la notification est marquée lue pour éviter le double traitement.';
        } else {
            $insights[] = 'Maintenir la liste BAD_WORDS_LIST dans .env alignée sur la politique éditoriale du projet.';
        }

        return $this->pack(
            'bad_word_alerts',
            'high',
            $headline,
            $reply,
            'File côté destinataire « Admin »',
            [
                ['label' => 'Traiter les alertes', 'route' => 'app_admin_blocked'],
            ],
            [
                [
                    'key' => 'unread',
                    'label' => 'Alertes non lues',
                    'value' => $n,
                ],
                [
                    'key' => 'load',
                    'label' => 'Charge modération estimée',
                    'value' => (string) $snap['moderation_load'],
                ],
            ],
            $insights,
            null,
            []
        );
    }

    private function bansResponse(array $snap): array
    {
        $w = $snap['warnings'];
        $active = (int) ($snap['ban_active'] ?? 0);

        $reply = "État des bannissements : {$active} actif(s) (fenêtre temporelle respectée). "
            ."Répartition : temporaires en cours {$w['alert1']}, permanents {$w['blocked']}, expirés {$w['alert2']}.";

        return $this->pack(
            'bans',
            'high',
            'Politique de sanctions — vue agrégée',
            $reply,
            'Table chat_bans · portées chat / posts / commentaires / réactions / partages',
            [['label' => 'Gérer les blocages', 'route' => 'app_admin_blocked']],
            [
                ['key' => 'active', 'label' => 'Blocages actifs', 'value' => $active],
                ['key' => 'temp', 'label' => 'Avec échéance future', 'value' => $w['alert1']],
                ['key' => 'perm', 'label' => 'Permanents', 'value' => $w['blocked']],
                ['key' => 'exp', 'label' => 'Expirés (historique)', 'value' => $w['alert2']],
            ],
            [
                'Les bans expirés restent en base pour l’audit ; seuls les actifs filtrent les actions utilisateur.',
                'Affinez la portée (scopes) pour limiter l’impact au salon ou aux publications selon le risque.',
            ],
            null,
            []
        );
    }

    private function hashtagsResponse(array $snap): array
    {
        $top = $snap['top_hashtags'];
        if ([] === $top) {
            return $this->pack(
                'hashtags',
                'high',
                'Index sémantique — vide',
                'Aucun jeton hashtag indexé (hashtags_index). Le classement TF-like sera alimenté après publications taguées.',
                null,
                [['label' => 'Exploration hashtags', 'route' => 'app_admin_hashtags']],
                [['key' => 'distinct', 'label' => 'Tags distincts', 'value' => 0]],
                ['Publier du contenu avec hashtags pour nourrir l’indice et le module Smart Hashtag côté auteur.'],
                null,
                []
            );
        }

        $items = [];
        foreach ($top as $i => $row) {
            $rank = $i + 1;
            $items[] = "#{$rank} · #{$row['tag']} — {$row['count']} occurrence(s)";
        }

        return $this->pack(
            'hashtags',
            'high',
            'Tendances lexicales — top '.(\count($top)),
            'Classement par fréquence d’apparition dans le champ hashtags_index des publications.',
            'Fréquence brute sur le corpus actuel',
            [['label' => 'Hashtags (admin)', 'route' => 'app_admin_hashtags']],
            [
                [
                    'key' => 'distinct',
                    'label' => 'Hashtags distincts (corpus)',
                    'value' => (int) $snap['distinct_hashtags'],
                ],
                [
                    'key' => 'top1',
                    'label' => 'Leader',
                    'value' => '#'.$top[0]['tag'],
                    'hint' => (string) $top[0]['count'].' posts',
                ],
            ],
            [
                'Croiser avec le dashboard : un tag dominant peut signaler un événement ou une campagne spontanée.',
            ],
            null,
            [['title' => 'Classement', 'items' => $items]]
        );
    }

    private function commentsResponse(array $snap): array
    {
        $total = (int) ($snap['comment_total'] ?? 0);
        $c7 = (int) ($snap['comments_last_7_days'] ?? 0);
        $cPrev = (int) ($snap['comments_prev_7_days'] ?? 0);
        $tr = $this->trendDelta($c7, $cPrev);

        $reply = "Stock total : {$total} commentaires. Volume glissant 7 j : {$c7} ({$tr['label']}).";

        return $this->pack(
            'comments',
            'high',
            'Engagement — commentaires',
            $reply,
            'Comparaison 7 j / 7 j précédents',
            [['label' => 'Modération commentaires', 'route' => 'app_admin_comments']],
            [
                ['key' => 'total', 'label' => 'Total historique', 'value' => $total],
                [
                    'key' => '7d',
                    'label' => 'Volume 7 j.',
                    'value' => $c7,
                    'trend' => $tr['dir'],
                    'trend_label' => $tr['label'],
                ],
            ],
            $c7 < $cPrev && $cPrev > 0
                ? ['La baisse récente peut refléter moins de publications sources ou une audience moins active.']
                : ['Surveiller les files longues sur posts viraux : prioriser la modération des extraits les plus cités.'],
            null,
            []
        );
    }

    private function postsResponse(array $snap): array
    {
        $total = (int) ($snap['post_total'] ?? 0);
        $today = (int) ($snap['posts_today'] ?? 0);
        $p7 = (int) ($snap['posts_last_7_days'] ?? 0);
        $pPrev = (int) ($snap['posts_prev_7_days'] ?? 0);
        $tr = $this->trendDelta($p7, $pPrev);

        $reply = "Cumul : {$total} publications. Fenêtre 7 j : {$p7} ({$tr['label']}). Aujourd’hui : {$today}.";

        return $this->pack(
            'posts',
            'high',
            'Production éditoriale',
            $reply,
            'Agrégat posts · agrégation horaire pour « aujourd’hui »',
            [['label' => 'Liste publications', 'route' => 'app_admin_posts']],
            [
                ['key' => 'total', 'label' => 'Total', 'value' => $total],
                [
                    'key' => '7d',
                    'label' => 'Publi. / 7 j.',
                    'value' => $p7,
                    'trend' => $tr['dir'],
                    'trend_label' => $tr['label'],
                ],
                ['key' => 'today', 'label' => 'Aujourd’hui', 'value' => $today],
            ],
            [
                'Si la tendance est à la baisse, vérifier la visibilité du formulaire de création et le mode hors-ligne côté client.',
            ],
            null,
            []
        );
    }

    private function sharesResponse(array $snap): array
    {
        $rows = (int) ($snap['share_rows'] ?? 0);
        $act = (int) ($snap['share_activity'] ?? 0);

        return $this->pack(
            'shares',
            'high',
            'Diffusion — partages',
            "Journal shares : {$rows} ligne(s). Activités typées SHARE (legacy activities) : {$act}.",
            'Deux sources complémentaires (table shares vs activities)',
            [['label' => 'Dashboard', 'route' => 'app_admin_dashboard']],
            [
                ['key' => 'shares', 'label' => 'Rows table shares', 'value' => $rows],
                ['key' => 'act', 'label' => 'Activités SHARE', 'value' => $act],
            ],
            [
                'Pour la soutenance : documenter que le partage social combine URL scheme côté UI + persistance serveur.',
            ],
            null,
            []
        );
    }

    private function reactionsResponse(array $snap): array
    {
        $like = (int) ($snap['like'] ?? 0);
        $dis = (int) ($snap['dislike'] ?? 0);
        $ratio = (float) ($snap['like_ratio_pct'] ?? 0.0);

        return $this->pack(
            'reactions',
            'high',
            'Sentiment — réactions',
            "Réactions sur posts (activities, hors commentaire) : J’aime {$like}, Je n’aime pas {$dis}. Ratio j’aime : {$ratio} %.",
            'Approximation de polarité sur le sous-ensemble réactions',
            [['label' => 'Dashboard', 'route' => 'app_admin_dashboard']],
            [
                ['key' => 'like', 'label' => 'J’aime', 'value' => $like],
                ['key' => 'dis', 'label' => 'Je n’aime pas', 'value' => $dis],
                ['key' => 'ratio', 'label' => 'Ratio j’aime', 'value' => $ratio.' %'],
            ],
            [
                $ratio < 45.0 && ($like + $dis) > 10
                    ? 'Polarité négative relative : vérifier le ton du contenu mis en avant ou la présence de débats.'
                    : 'Le ratio sert d’indicateur global ; il ne remplace pas une analyse qualitative des contenus.',
            ],
            null,
            []
        );
    }

    private function chatResponse(array $snap): array
    {
        $n = (int) ($snap['chat_messages_total'] ?? 0);

        return $this->pack(
            'chat',
            'high',
            'Salon temps réel — persistance',
            "{$n} message(s) stockés (historique). La charge temps réel dépend du service WebSocket et des annonces admin.",
            'Table chat_messages',
            [['label' => 'Contrôle & annonces', 'route' => 'app_admin_chat']],
            [['key' => 'msg', 'label' => 'Messages stockés', 'value' => $n]],
            [
                'Les annonces système utilisent une clé utilisateur réservée (Admin) pour le filtrage visuel côté client.',
            ],
            null,
            []
        );
    }

    private function mostCommentedResponse(array $snap): array
    {
        $rows = $snap['most_commented'];
        if ([] === $rows) {
            return $this->pack(
                'most_commented',
                'high',
                'Classement — indisponible',
                'Pas assez de liaisons post–commentaire pour établir un top.',
                null,
                [['label' => 'Publications', 'route' => 'app_admin_posts']],
                [],
                ['Encouragez l’interaction sur les publications récentes pour nourrir cet indicateur.'],
                null,
                []
            );
        }

        $items = [];
        foreach ($rows as $r) {
            $ex = str_replace(["\n", "\r"], ' ', $r['excerpt']);
            $items[] = 'Post #'.$r['post_id'].' · '.$r['comment_count'].' commentaire(s) · « '.$ex.' »';
        }

        return $this->pack(
            'most_commented',
            'high',
            'Contenus à fort engagement',
            'Posts classés par nombre de commentaires (jointure SQL agrégée). Utile pour prioriser la modération.',
            'Top '.\count($rows),
            [['label' => 'Voir les publications', 'route' => 'app_admin_posts']],
            [
                [
                    'key' => 'top',
                    'label' => 'Pic commentaires',
                    'value' => $rows[0]['comment_count'],
                    'hint' => 'Post #'.$rows[0]['post_id'],
                ],
            ],
            [
                'Un pic soudain peut indiquer un sujet sensible : ouvrir la publication avant d’agir sur les bans globaux.',
            ],
            null,
            [['title' => 'Détail du classement', 'items' => $items]]
        );
    }

    private function topCommentersResponse(array $snap): array
    {
        $rows = $snap['top_commenters'];
        if ([] === $rows) {
            return $this->pack(
                'top_commenters',
                'high',
                'Top contributeurs — vide',
                'Aucun commentaire en base.',
                null,
                [['label' => 'Commentaires', 'route' => 'app_admin_comments']],
                [],
                [],
                null,
                []
            );
        }

        $items = [];
        foreach ($rows as $r) {
            $items[] = $r['user_key'].' — '.$r['count'].' commentaire(s)';
        }

        return $this->pack(
            'top_commenters',
            'high',
            'Contributions par identifiant',
            'Classement par clé utilisateur (session user_key) — indicateur d’activité, pas d’identité réelle vérifiée.',
            'Top '.\count($rows),
            [['label' => 'Liste commentaires', 'route' => 'app_admin_comments']],
            [
                [
                    'key' => 'first',
                    'label' => 'Leader',
                    'value' => $rows[0]['user_key'],
                    'hint' => (string) $rows[0]['count'].' msg',
                ],
            ],
            [
                'Un contributeur très actif peut mériter surveillance ciblée (spam) ou reconnaissance (ambassadeur).',
            ],
            null,
            [['title' => 'Classement', 'items' => $items]]
        );
    }

    private function activityResponse(array $snap): array
    {
        $p7 = (int) ($snap['posts_last_7_days'] ?? 0);
        $c7 = (int) ($snap['comments_last_7_days'] ?? 0);
        $trP = $this->trendDelta($p7, (int) $snap['posts_prev_7_days']);
        $trC = $this->trendDelta($c7, (int) $snap['comments_prev_7_days']);

        $reply = "Fenêtre glissante 7 j : {$p7} publication(s), {$c7} commentaire(s). Posts : {$trP['label']}. Commentaires : {$trC['label']}.";

        return $this->pack(
            'activity',
            'high',
            'Dynamique récente — 7 + 7 jours',
            $reply,
            'Série journalière 30 j · comparaison de deux fenêtres consécutives',
            [['label' => 'Graphiques dashboard', 'route' => 'app_admin_dashboard']],
            [
                [
                    'key' => 'p7',
                    'label' => 'Posts 7 j.',
                    'value' => $p7,
                    'trend' => $trP['dir'],
                    'trend_label' => $trP['label'],
                ],
                [
                    'key' => 'c7',
                    'label' => 'Com. 7 j.',
                    'value' => $c7,
                    'trend' => $trC['dir'],
                    'trend_label' => $trC['label'],
                ],
            ],
            [
                'Pour la granularité horaire du jour en cours, le dashboard expose la distribution par heure (publications créées aujourd’hui).',
            ],
            null,
            []
        );
    }

    private function fullSummaryResponse(array $snap): array
    {
        $trP = $this->trendDelta((int) $snap['posts_last_7_days'], (int) $snap['posts_prev_7_days']);
        $trC = $this->trendDelta((int) $snap['comments_last_7_days'], (int) $snap['comments_prev_7_days']);
        $alerts = (int) ($snap['bad_word_unread'] ?? 0);

        $detail = $this->formatSummaryBlock($snap)
            ."\n\nTendance publications (7 j) : ".$trP['label']."\n"
            .'Tendance commentaires (7 j) : '.$trC['label']."\n"
            .'Charge modération estimée : '.$snap['moderation_load'].'.';

        $insights = [
            $alerts > 0 ? "Priorité : {$alerts} alerte(s) lexicale(s) — traiter avant d’élargir la modération." : 'Aucune alerte lexicale en attente : la file est vide.',
            'Le ratio j’aime / total réactions : '.$snap['like_ratio_pct'].' % (indicateur de polarité grossier).',
        ];

        return $this->pack(
            'summary',
            'high',
            'Synthèse exécutive — plateforme',
            'Vue consolidée : volumes, tendances 7+7 jours, modération et diffusion.',
            'Rapport décisionnel instantané',
            $this->defaultRelated(),
            [
                ['key' => 'pub', 'label' => 'Publications', 'value' => (int) $snap['post_total']],
                ['key' => 'com', 'label' => 'Commentaires', 'value' => (int) $snap['comment_total']],
                [
                    'key' => 'p7',
                    'label' => 'Posts / 7 j.',
                    'value' => (int) $snap['posts_last_7_days'],
                    'trend' => $trP['dir'],
                    'trend_label' => $trP['label'],
                ],
                [
                    'key' => 'c7',
                    'label' => 'Com. / 7 j.',
                    'value' => (int) $snap['comments_last_7_days'],
                    'trend' => $trC['dir'],
                    'trend_label' => $trC['label'],
                ],
                ['key' => 'al', 'label' => 'Alertes', 'value' => $alerts],
            ],
            $insights,
            $detail,
            []
        );
    }

    private function fallbackResponse(array $snap): array
    {
        $trP = $this->trendDelta((int) $snap['posts_last_7_days'], (int) $snap['posts_prev_7_days']);

        return $this->pack(
            'fallback',
            'medium',
            'Interprétation — requête ambiguë',
            'La requête ne correspond à aucun motif fort. Voici un instantané utile et des formulations à essayer.',
            'Correspondance partielle — aide contextuelle',
            $this->defaultRelated(),
            [
                ['key' => 'p7', 'label' => 'Posts 7 j.', 'value' => (int) $snap['posts_last_7_days'], 'trend' => $trP['dir'], 'trend_label' => $trP['label']],
                ['key' => 'al', 'label' => 'Alertes', 'value' => (int) $snap['bad_word_unread']],
            ],
            [
                'Formuler une intention explicite : « synthèse », « tendance », « top hashtags », « modération ».',
            ],
            $this->formatSummaryBlock($snap),
            [
                [
                    'title' => 'Suggestions',
                    'items' => [
                        'Synthèse dashboard',
                        'Tendance commentaires',
                        'Alertes bad words',
                        'Top hashtags',
                    ],
                ],
            ]
        );
    }

    private function formatSummaryBlock(array $snap): string
    {
        $alerts = (int) ($snap['bad_word_unread'] ?? 0);

        return sprintf(
            'Publications (total) : %d — aujourd’hui : %d'."\n"
            .'Commentaires (total) : %d'."\n"
            .'Hashtags distincts : %d · Partages enregistrés : %d'."\n"
            .'Blocages actifs : %d · Alertes non lues : %d'."\n"
            .'Messages chat (historique) : %d',
            (int) $snap['post_total'],
            (int) $snap['posts_today'],
            (int) $snap['comment_total'],
            (int) $snap['distinct_hashtags'],
            (int) $snap['share_rows'],
            (int) $snap['ban_active'],
            $alerts,
            (int) $snap['chat_messages_total']
        );
    }

    private function defaultRelated(): array
    {
        return [
            ['label' => 'Dashboard analytique', 'route' => 'app_admin_dashboard'],
            ['label' => 'Modération & alertes', 'route' => 'app_admin_blocked'],
            ['label' => 'Publications', 'route' => 'app_admin_posts'],
        ];
    }
}
