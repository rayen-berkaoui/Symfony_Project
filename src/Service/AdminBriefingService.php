<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ChatBanRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class AdminBriefingService
{
    private const SESSION_SNAPSHOT = '_tbn_admin_console_snapshot';
    private const SUPPORTED_RANGES = ['24h', '72h', '7d', '30d'];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ChatBanRepository $chatBanRepository,
    ) {
    }

    public function buildPageBriefing(array $snap, string $selectedRange = '24h'): array
    {
        $session = $this->getSession();
        $range = $this->normalizeRange($selectedRange);
        $visit = $this->diffVisitAndPersist($session, $snap);
        $priority = $this->buildPriorityQueue($snap);
        $cross = $this->buildEngagementCrossSignal($snap);
        $anomalies = $this->detectAnomalies($snap);
        $report = $this->buildRangeReport($snap, $range);
        $advice = $this->buildAiGuidance($snap, $report, $priority, $anomalies);

        return [
            'visit' => $visit,
            'priority' => $priority,
            'cross_signal' => $cross,
            'anomalies' => $anomalies,
            'report' => $report,
            'ai_guidance' => $advice,
        ];
    }

    public function buildWhatsNewAssistantPayload(array $snap): array
    {
        $session = $this->getSession();
        $visit = $this->diffVisitAndPersist($session, $snap);
        $priority = $this->buildPriorityQueue($snap);

        $lines = $visit['lines'];
        if ([] === $lines && !$visit['first_open']) {
            $lines[] = 'Aucun mouvement notable sur les compteurs suivis depuis votre dernière ouverture de cette console.';
        }

        $headline = $visit['first_open']
            ? 'Référence enregistrée'
            : 'Évolution depuis votre dernière ouverture';

        $sub = $visit['first_open']
            ? 'Les prochains chargements de cette page compareront l’état actuel à cette ligne de base.'
            : ($visit['recorded_at'] ? 'Dernière référence : '.$visit['recorded_at'] : null);

        $detail = implode("\n", $lines);
        $insights = [];
        foreach (\array_slice($priority, 0, 3) as $p) {
            $insights[] = '['.strtoupper($p['severity']).'] '.$p['title'].' — '.$p['rationale'];
        }
        if ([] === $insights) {
            $insights[] = 'Aucune alerte prioritaire automatique : poursuivre la veille habituelle.';
        }

        $metrics = [
            [
                'key' => 'delta_posts',
                'label' => 'Δ Publications (total)',
                'value' => $this->formatDelta((int) $snap['post_total'], $visit['prev']['post_total'] ?? null),
            ],
            [
                'key' => 'delta_com',
                'label' => 'Δ Commentaires (total)',
                'value' => $this->formatDelta((int) $snap['comment_total'], $visit['prev']['comment_total'] ?? null),
            ],
            [
                'key' => 'delta_al',
                'label' => 'Δ Alertes non lues',
                'value' => $this->formatDelta((int) $snap['bad_word_unread'], $visit['prev']['bad_word_unread'] ?? null),
            ],
        ];

        $related = [];
        foreach (\array_slice($priority, 0, 2) as $p) {
            $related[] = ['label' => $p['action_label'], 'route' => $p['route'], 'params' => $p['params'] ?? []];
        }
        if ([] === $related) {
            $related = [['label' => 'Dashboard', 'route' => 'app_admin_dashboard']];
        }

        return [
            'reply' => $visit['first_open']
                ? 'Première ouverture de la console dans cette session : une ligne de base est enregistrée pour mesurer les prochains écarts.'
                : 'Voici ce qui a changé sur les indicateurs clés depuis votre dernière ouverture de cette page.',
            'intent' => 'visit_delta',
            'confidence' => 'high',
            'related' => $related,
            'envelope' => [
                'headline' => $headline,
                'subhead' => $sub,
                'metrics' => $metrics,
                'insights' => $insights,
                'detail' => $detail ?: null,
                'lists' => $visit['first_open'] ? [] : [['title' => 'Chronologie des écarts', 'items' => $lines]],
            ],
        ];
    }

    public function isWhatsNewQuestion(string $q): bool
    {
        return $this->hasAny($q, [
            'quoi de neuf', 'dernière visite', 'derniere visite', 'ma dernière visite', 'ma derniere visite',
            'depuis ma visite', 'depuis la dernière', 'delta depuis',
            'what\'s new', 'since last visit', 'since my last',
            'ما الجديد', 'منذ آخر', 'منذ اخر', 'آخر زيارة', 'اخر زيارة', 'تحديث منذ',
        ]);
    }

    private function diffVisitAndPersist(SessionInterface $session, array $snap): array
    {
        $compact = $this->compactSnapshot($snap);
        $raw = $session->get(self::SESSION_SNAPSHOT);
        $prev = \is_string($raw) ? json_decode($raw, true) : null;
        $prev = \is_array($prev) ? $prev : null;

        $first = null === $prev;
        $lines = [];

        if (!$first && \is_array($prev)) {
            $lines = array_merge($lines, $this->diffScalar('Publications (total)', (int) ($prev['post_total'] ?? 0), (int) $compact['post_total']));
            $lines = array_merge($lines, $this->diffScalar('Commentaires (total)', (int) ($prev['comment_total'] ?? 0), (int) $compact['comment_total']));
            $lines = array_merge($lines, $this->diffScalar('Alertes bad words (non lues)', (int) ($prev['bad_word_unread'] ?? 0), (int) $compact['bad_word_unread']));
            $lines = array_merge($lines, $this->diffScalar('Blocages actifs', (int) ($prev['ban_active'] ?? 0), (int) $compact['ban_active']));
            $lines = array_merge($lines, $this->diffScalar('Publications sur 7 jours', (int) ($prev['posts_last_7_days'] ?? 0), (int) $compact['posts_last_7_days']));
            $lines = array_merge($lines, $this->diffScalar('Commentaires sur 7 jours', (int) ($prev['comments_last_7_days'] ?? 0), (int) $compact['comments_last_7_days']));
        }

        $recordedAt = $prev['saved_at'] ?? null;
        $compact['saved_at'] = (new \DateTimeImmutable())->format('d/m/Y H:i');
        $session->set(self::SESSION_SNAPSHOT, json_encode($compact, \JSON_THROW_ON_ERROR));

        return [
            'first_open' => $first,
            'lines' => $lines,
            'recorded_at' => $recordedAt,
            'prev' => $prev,
        ];
    }

    private function diffScalar(string $label, int $before, int $after): array
    {
        if ($before === $after) {
            return [];
        }
        $d = $after - $before;
        $sign = $d > 0 ? '+' : '';

        return [$label.' : '.$before.' → '.$after.' ('.$sign.$d.').'];
    }

    private function formatDelta(int $now, ?int $before): string
    {
        if (null === $before) {
            return (string) $now;
        }
        if ($before === $now) {
            return (string) $now.' (=)';
        }
        $d = $now - $before;

        return $now.' ('.($d > 0 ? '+' : '').$d.')';
    }

    private function compactSnapshot(array $snap): array
    {
        return [
            'post_total' => (int) ($snap['post_total'] ?? 0),
            'comment_total' => (int) ($snap['comment_total'] ?? 0),
            'bad_word_unread' => (int) ($snap['bad_word_unread'] ?? 0),
            'ban_active' => (int) ($snap['ban_active'] ?? 0),
            'posts_last_7_days' => (int) ($snap['posts_last_7_days'] ?? 0),
            'comments_last_7_days' => (int) ($snap['comments_last_7_days'] ?? 0),
        ];
    }

    private function buildPriorityQueue(array $snap): array
    {
        $out = [];
        $alerts = (int) ($snap['bad_word_unread'] ?? 0);
        if ($alerts > 0) {
            $out[] = [
                'severity' => 'critical',
                'title' => 'Alertes lexicales en attente',
                'rationale' => $alerts.' notification(s) non lue(s) nécessitent une décision (blocage ou ignore).',
                'action_label' => 'Traiter les alertes',
                'route' => 'app_admin_blocked',
            ];
        }

        $expiring = $this->chatBanRepository->countActiveBansExpiringWithinHours(48);
        if ($expiring > 0) {
            $out[] = [
                'severity' => 'warning',
                'title' => 'Fin de sanctions proches',
                'rationale' => $expiring.' bannissement(s) actif(s) expire(nt) dans les 48 h : anticiper la reprise d’accès.',
                'action_label' => 'Voir les blocages',
                'route' => 'app_admin_blocked',
            ];
        }

        $p7 = (int) ($snap['posts_last_7_days'] ?? 0);
        $pPrev = (int) ($snap['posts_prev_7_days'] ?? 0);
        if ($pPrev >= 3 && $p7 < (int) floor(0.45 * $pPrev)) {
            $out[] = [
                'severity' => 'warning',
                'title' => 'Fort ralentissement des publications',
                'rationale' => 'Le volume sur 7 j est nettement inférieur à la fenêtre précédente — vérifier l’accès au formulaire ou un incident technique.',
                'action_label' => 'Voir les publications',
                'route' => 'app_admin_posts',
            ];
        }

        $most = $snap['most_commented'] ?? [];
        if ([] !== $most && $most[0]['comment_count'] >= 12) {
            $pid = $most[0]['post_id'];
            $out[] = [
                'severity' => 'info',
                'title' => 'Post sous forte discussion',
                'rationale' => 'Le post #'.$pid.' concentre '.$most[0]['comment_count'].' commentaires — risque de dérive ou de spam.',
                'action_label' => 'Ouvrir la publication',
                'route' => 'app_post_show',
                'params' => ['id' => $pid],
            ];
        }

        return $out;
    }

    private function buildEngagementCrossSignal(array $snap): array
    {
        $pt = max(1, (int) ($snap['post_total'] ?? 0));
        $ct = (int) ($snap['comment_total'] ?? 0);
        $life = round($ct / $pt, 2);

        $p7 = max(1, (int) ($snap['posts_last_7_days'] ?? 0));
        $c7 = (int) ($snap['comments_last_7_days'] ?? 0);
        $recent = round($c7 / $p7, 2);

        $detail = 'Historique : '.$life.' commentaire(s) / post en moyenne. Fenêtre 7 j : '.$recent.' commentaire(s) / post publié sur la période.';
        if ($recent > $life * 1.35 && $c7 >= 5) {
            $detail .= ' La période récente est nettement plus « conversationnelle » que la moyenne du site.';
        } elseif ($recent < $life * 0.65 && $c7 >= 3) {
            $detail .= ' La période récente est moins discussion que la moyenne historique.';
        }

        return [
            'title' => 'Densité de discussion (croisement)',
            'value' => $recent.' / post (7 j) · '.$life.' / post (historique)',
            'detail' => $detail,
        ];
    }

    private function detectAnomalies(array $snap): array
    {
        $a = [];
        $like = (int) ($snap['like'] ?? 0);
        $dis = (int) ($snap['dislike'] ?? 0);
        $t = max(1, $like + $dis);
        $ratio = 100 * $like / $t;
        if ($t >= 15 && $ratio < 38.0) {
            $a[] = 'Polarité des réactions plutôt négative ('.round($ratio, 1).' % de j’aime) — à interpréter avec le contexte éditorial.';
        }

        $c7 = (int) ($snap['comments_last_7_days'] ?? 0);
        $p7 = (int) ($snap['posts_last_7_days'] ?? 0);
        if ($p7 > 0 && $c7 > 8 * $p7) {
            $a[] = 'Ratio commentaires / publications (7 j) très élevé : la communauté commente plus qu’elle ne publie.';
        }

        return $a;
    }

    private function getSession(): SessionInterface
    {
        $req = $this->requestStack->getCurrentRequest();
        if (null === $req || !$req->hasSession()) {
            throw new \LogicException('Session requise pour le briefing admin.');
        }

        return $req->getSession();
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

    private function normalizeRange(string $range): string
    {
        return \in_array($range, self::SUPPORTED_RANGES, true) ? $range : '24h';
    }

    private function buildRangeReport(array $snap, string $range): array
    {
        $dailyPosts = \is_array($snap['daily_posts_series'] ?? null) ? $snap['daily_posts_series'] : [];
        $dailyComments = \is_array($snap['daily_comments_series'] ?? null) ? $snap['daily_comments_series'] : [];
        $days = match ($range) {
            '24h' => 1,
            '72h' => 3,
            '7d' => 7,
            '30d' => 30,
            default => 1,
        };

        $postsCurrent = $days === 1 ? (int) ($snap['posts_today'] ?? 0) : $this->sumSeriesDays($dailyPosts, $days);
        $commentsCurrent = $this->sumSeriesDays($dailyComments, $days);
        $postsPrevious = $this->sumSeriesDays($dailyPosts, $days, $days);
        $commentsPrevious = $this->sumSeriesDays($dailyComments, $days, $days);

        $deltaPosts = $postsCurrent - $postsPrevious;
        $deltaComments = $commentsCurrent - $commentsPrevious;

        $dominant = 'stable';
        if ($deltaPosts > 0 && $deltaComments > 0) {
            $dominant = 'croissance';
        } elseif ($deltaPosts < 0 || $deltaComments < 0) {
            $dominant = 'ralentissement';
        }

        $windows = [
            ['value' => '24h', 'label' => '24 heures', 'active' => '24h' === $range],
            ['value' => '72h', 'label' => '72 heures', 'active' => '72h' === $range],
            ['value' => '7d', 'label' => '7 jours', 'active' => '7d' === $range],
            ['value' => '30d', 'label' => '30 jours', 'active' => '30d' === $range],
        ];

        $metrics = [
            [
                'label' => 'Publications ajoutées',
                'value' => (string) $postsCurrent,
                'hint' => $this->formatSignedDelta($deltaPosts).' vs période précédente équivalente.',
            ],
            [
                'label' => 'Commentaires ajoutés',
                'value' => (string) $commentsCurrent,
                'hint' => $this->formatSignedDelta($deltaComments).' vs période précédente équivalente.',
            ],
            [
                'label' => 'Niveau d\'activité',
                'value' => ucfirst($dominant),
                'hint' => 'Lecture basée sur l\'évolution publications + commentaires.',
            ],
            [
                'label' => 'Suppressions détectées',
                'value' => 'N/A',
                'hint' => 'Les suppressions ne sont pas historisées dans ces agrégats; seules les variations nettes sont visibles.',
            ],
        ];

        $highlights = [];
        $highlights[] = 'Période analysée : '.$this->rangeLabel($range).'.';
        $highlights[] = $this->dominantLine($deltaPosts, $deltaComments);
        $highlights[] = $this->topContributorLine($snap, $range);

        return [
            'selected_range' => $range,
            'windows' => $windows,
            'metrics' => $metrics,
            'highlights' => $highlights,
        ];
    }

    private function sumSeriesDays(array $series, int $days, int $skip = 0): int
    {
        if ($days <= 0) {
            return 0;
        }
        $slice = \array_slice($series, -($days + $skip), $days);
        $n = 0;
        foreach ($slice as $row) {
            $n += (int) ($row['count'] ?? 0);
        }

        return $n;
    }

    private function formatSignedDelta(int $delta): string
    {
        if (0 === $delta) {
            return '0';
        }

        return ($delta > 0 ? '+' : '').$delta;
    }

    private function rangeLabel(string $range): string
    {
        return match ($range) {
            '24h' => '24 heures',
            '72h' => '72 heures',
            '7d' => '7 jours',
            '30d' => '30 jours',
            default => '24 heures',
        };
    }

    private function dominantLine(int $deltaPosts, int $deltaComments): string
    {
        if ($deltaPosts > 0 && $deltaComments > 0) {
            return 'Le rythme progresse sur le contenu et les échanges.';
        }
        if ($deltaPosts < 0 && $deltaComments < 0) {
            return 'Baisse conjointe détectée : contenu et discussion reculent.';
        }
        if ($deltaPosts > 0 && $deltaComments <= 0) {
            return 'Les publications montent mais la conversation ne suit pas encore.';
        }
        if ($deltaComments > 0 && $deltaPosts <= 0) {
            return 'La discussion progresse plus vite que la création de nouveaux posts.';
        }

        return 'Activité globalement stable sur la période.';
    }

    private function topContributorLine(array $snap, string $range): string
    {
        $top = \is_array($snap['top_commenters'] ?? null) ? $snap['top_commenters'] : [];
        if ([] === $top) {
            return 'Aucun contributeur dominant détecté sur les données disponibles.';
        }
        $name = (string) ($top[0]['userKey'] ?? 'N/A');
        $count = (int) ($top[0]['count'] ?? 0);

        return 'Contributeur le plus actif (base commentaires) : '.$name.' avec '.$count.' message(s) sur la fenêtre suivie.';
    }

    private function buildAiGuidance(array $snap, array $report, array $priority, array $anomalies): array
    {
        $tips = [];
        $tips[] = 'Définir un rituel de revue (matin/soir) sur '.$this->rangeLabel($report['selected_range']).' pour réagir plus vite aux variations.';

        if ([] !== $priority) {
            $tips[] = 'Traiter en premier « '.$priority[0]['title'].' » pour réduire le risque opérationnel immédiat.';
        } else {
            $tips[] = 'Aucune alerte critique : consacrer ce créneau à l’amélioration de la qualité (guidelines de modération, feedback auteurs).';
        }

        $posts7 = (int) ($snap['posts_last_7_days'] ?? 0);
        $comments7 = (int) ($snap['comments_last_7_days'] ?? 0);
        if ($posts7 > 0 && $comments7 > 4 * $posts7) {
            $tips[] = 'Le débat est très intense par rapport au volume de posts : ajouter un rappel de règles visible et surveiller le spam.';
        } else {
            $tips[] = 'Tester un format de publication plus conversationnel pour augmenter la qualité des échanges.';
        }

        if ([] !== $anomalies) {
            $tips[] = 'Signal automatique actif : prioriser une vérification manuelle des contenus récents avant toute action massive.';
        } else {
            $tips[] = 'Pas d’anomalie forte détectée : lancer une expérimentation éditoriale (thème/heure) puis comparer sur 72h.';
        }

        return $tips;
    }
}
