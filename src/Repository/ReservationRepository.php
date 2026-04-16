<?php

namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function findByUser(Utilisateur $user): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.panier', 'p')->addSelect('p')
            ->leftJoin('p.utilisateur', 'u')->addSelect('u')
            ->where('p.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('r.datePaiement', 'DESC')
            ->getQuery()
            ->getResult();
    }


    public function findByPaymeeToken(string $token): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.reviewComment LIKE :token')
            ->setParameter('token', '%[PAYMEE_TOKEN:' . $token . ']%')
            ->getQuery()
            ->getResult();
    }

    public function findByPaymeeOrderId(string $orderId): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.reviewComment LIKE :orderId')
            ->setParameter('orderId', '%[PAYMEE_ORDER:' . $orderId . ']%')
            ->getQuery()
            ->getResult();
    }

    public function searchByFilters(?string $search, ?string $status, ?string $mode): QueryBuilder
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.panier', 'p')->addSelect('p')
            ->leftJoin('p.utilisateur', 'u')->addSelect('u');

        if ($search) {
            $qb->andWhere('r.codeConfirmation LIKE :search OR r.modePaiement LIKE :search OR r.statutPaiement LIKE :search OR CAST(r.id AS string) LIKE :search OR u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($status) {
            $qb->andWhere('LOWER(r.statutPaiement) LIKE :status')
                ->setParameter('status', '%' . strtolower($status) . '%');
        }

        if ($mode) {
            $qb->andWhere('r.modePaiement = :mode')->setParameter('mode', $mode);
        }

        return $qb->orderBy('r.datePaiement', 'DESC');
    }

    public function getStats(): array
    {
        $total = (int) $this->count([]);
        $paid = (int) $this->createQueryBuilder('r')->select('COUNT(r.id)')->where('LOWER(r.statutPaiement) LIKE :status')->setParameter('status', '%pay%')->getQuery()->getSingleScalarResult();
        $inProgress = (int) $this->createQueryBuilder('r')->select('COUNT(r.id)')->where('LOWER(r.statutPaiement) LIKE :status')->setParameter('status', '%cours%')->getQuery()->getSingleScalarResult();
        $waiting = (int) $this->createQueryBuilder('r')->select('COUNT(r.id)')->where('LOWER(r.statutPaiement) LIKE :status')->setParameter('status', '%attente%')->getQuery()->getSingleScalarResult();
        $revenue = (float) ($this->createQueryBuilder('r')->select('SUM(r.montantTotal)')->where('LOWER(r.statutPaiement) LIKE :status')->setParameter('status', '%pay%')->getQuery()->getSingleScalarResult() ?? 0);
        $avgRating = $this->createQueryBuilder('r')->select('AVG(r.rating)')->where('r.rating IS NOT NULL')->getQuery()->getSingleScalarResult();

        return [
            'total' => $total,
            'paye' => $paid,
            'en_cours' => $inProgress,
            'en_attente' => $waiting,
            'revenue' => $revenue,
            'avg_rating' => $avgRating ? round((float) $avgRating, 1) : null,
        ];
    }

    public function getModeBreakdown(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.modePaiement AS mode_paiement, COUNT(r.id) AS total')
            ->groupBy('r.modePaiement')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    public function getRecentRatings(int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.panier', 'p')->addSelect('p')
            ->leftJoin('p.utilisateur', 'u')->addSelect('u')
            ->where('r.rating IS NOT NULL')
            ->orderBy('r.datePaiement', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getExportRows(): array
    {
        $sql = <<<'SQL'
SELECT
    r.id_reservation AS id,
    r.code_confirmation AS code_confirmation,
    r.date_paiement AS date_paiement,
    r.montant_total AS montant_total,
    r.mode_paiement AS mode_paiement,
    r.statut_paiement AS statut_paiement,
    r.rating AS rating,
    p.id_panier AS panier_id,
    p.type_service AS type_service,
    p.date_debut AS date_debut,
    p.date_fin AS date_fin,
    p.nb_personnes AS nb_personnes,
    p.nb_chambres AS nb_chambres,
    p.id_etablissement AS service_id,
    u.nom AS client_nom,
    u.prenom AS client_prenom,
    u.email AS client_email,
    e.nom AS etablissement_nom,
    e.ville AS etablissement_ville,
    l.nom AS lieu_nom,
    l.ville AS lieu_ville,
    lt.nom AS lieu_touristique_nom,
    lt.ville AS lieu_touristique_ville
FROM reservation r
LEFT JOIN panier p ON p.id_panier = r.id_panier
LEFT JOIN utilisateur u ON u.id = p.id_client
LEFT JOIN etablissement e ON e.idEtablissement = p.id_etablissement
LEFT JOIN lieu l ON l.id_lieu = p.id_etablissement
LEFT JOIN lieu_touristique lt ON lt.id_lieu = p.id_etablissement
ORDER BY r.date_paiement DESC
SQL;
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql);
        return array_map([$this, 'normalizeExportRow'], $rows);
    }



    public function getUserRows(int $userId): array
    {
        $sql = <<<'SQL'
SELECT
    r.id_reservation AS id,
    r.code_confirmation AS code_confirmation,
    r.date_paiement AS date_paiement,
    r.montant_total AS montant_total,
    r.mode_paiement AS mode_paiement,
    r.statut_paiement AS statut_paiement,
    r.rating AS rating,
    r.review_comment AS review_comment,
    p.id_panier AS panier_id,
    p.id_client AS client_id,
    p.type_service AS type_service,
    p.date_debut AS date_debut,
    p.date_fin AS date_fin,
    p.nb_personnes AS nb_personnes,
    p.nb_chambres AS nb_chambres,
    p.nb_adultes AS nb_adultes,
    p.nb_enfants AS nb_enfants,
    p.id_etablissement AS service_id,
    u.nom AS client_nom,
    u.prenom AS client_prenom,
    u.email AS client_email,
    e.nom AS etablissement_nom,
    e.ville AS etablissement_ville,
    l.nom AS lieu_nom,
    l.ville AS lieu_ville,
    lt.nom AS lieu_touristique_nom,
    lt.ville AS lieu_touristique_ville
FROM reservation r
LEFT JOIN panier p ON p.id_panier = r.id_panier
LEFT JOIN utilisateur u ON u.id = p.id_client
LEFT JOIN etablissement e ON e.idEtablissement = p.id_etablissement
LEFT JOIN lieu l ON l.id_lieu = p.id_etablissement
LEFT JOIN lieu_touristique lt ON lt.id_lieu = p.id_etablissement
ORDER BY r.date_paiement DESC
SQL;
        $rows = array_map([$this, 'normalizeExportRow'], $this->getEntityManager()->getConnection()->fetchAllAssociative($sql));

        return array_values(array_filter($rows, fn (array $row) => $this->matchesRowToUser($row, $userId)));
    }

    public function getUserFlexibleRow(int $reservationId, int $userId): ?array
    {
        $sql = <<<'SQL'
SELECT
    r.id_reservation AS id,
    r.code_confirmation AS code_confirmation,
    r.date_paiement AS date_paiement,
    r.montant_total AS montant_total,
    r.mode_paiement AS mode_paiement,
    r.statut_paiement AS statut_paiement,
    r.rating AS rating,
    r.review_comment AS review_comment,
    p.id_panier AS panier_id,
    p.id_client AS client_id,
    p.type_service AS type_service,
    p.date_debut AS date_debut,
    p.date_fin AS date_fin,
    p.nb_personnes AS nb_personnes,
    p.nb_chambres AS nb_chambres,
    p.nb_adultes AS nb_adultes,
    p.nb_enfants AS nb_enfants,
    p.id_etablissement AS service_id,
    u.nom AS client_nom,
    u.prenom AS client_prenom,
    u.email AS client_email,
    e.nom AS etablissement_nom,
    e.ville AS etablissement_ville,
    l.nom AS lieu_nom,
    l.ville AS lieu_ville,
    lt.nom AS lieu_touristique_nom,
    lt.ville AS lieu_touristique_ville
FROM reservation r
LEFT JOIN panier p ON p.id_panier = r.id_panier
LEFT JOIN utilisateur u ON u.id = p.id_client
LEFT JOIN etablissement e ON e.idEtablissement = p.id_etablissement
LEFT JOIN lieu l ON l.id_lieu = p.id_etablissement
LEFT JOIN lieu_touristique lt ON lt.id_lieu = p.id_etablissement
WHERE r.id_reservation = :reservationId
LIMIT 1
SQL;
        $row = $this->getEntityManager()->getConnection()->fetchAssociative($sql, ['reservationId' => $reservationId]);
        if (!$row) {
            return null;
        }
        $row = $this->normalizeExportRow($row);

        return $this->matchesRowToUser($row, $userId) ? $row : null;
    }

    public function belongsToUser(int $reservationId, int $userId): bool
    {
        return $this->getUserFlexibleRow($reservationId, $userId) !== null;
    }
    public function getUserExportRow(int $reservationId, int $userId): ?array
    {
        return $this->getUserFlexibleRow($reservationId, $userId);
    }

    private function normalizeExportRow(array $row): array
    {
        $reviewComment = (string) ($row['review_comment'] ?? '');
        $serviceName = $row['etablissement_nom'] ?: ($row['lieu_nom'] ?: ($row['lieu_touristique_nom'] ?: ($this->extractMarker($reviewComment, 'SNAPSHOT_NAME') ?: ('Service archivé #' . ($row['panier_id'] ?: 'N/A')))));
        $serviceCity = $row['etablissement_ville'] ?: ($row['lieu_ville'] ?: ($row['lieu_touristique_ville'] ?: ($this->extractMarker($reviewComment, 'SNAPSHOT_CITY') ?: 'Tunisie')));
        $clientName = trim((string) (($row['client_prenom'] ?? '') . ' ' . ($row['client_nom'] ?? '')));
        $status = $this->normalizeStatus((string) ($row['statut_paiement'] ?? ''));
        $mode = ((string) ($row['mode_paiement'] ?? '')) === 'Especes' ? 'Espèces' : ((string) ($row['mode_paiement'] ?? ''));
        $typeService = (string) ($row['type_service'] ?? ($this->extractMarker($reviewComment, 'SNAPSHOT_TYPE') ?: 'Lieu'));
        $dateDebut = $row['date_debut'] ? new \DateTimeImmutable((string) $row['date_debut']) : $this->parseDateMarker($reviewComment, 'SNAPSHOT_START');
        $dateFin = $row['date_fin'] ? new \DateTimeImmutable((string) $row['date_fin']) : $this->parseDateMarker($reviewComment, 'SNAPSHOT_END');
        $nbPersonnes = isset($row['nb_personnes']) && $row['nb_personnes'] !== null ? (int) $row['nb_personnes'] : (int) ($this->extractMarker($reviewComment, 'SNAPSHOT_PEOPLE') ?: 0);
        $nbChambres = isset($row['nb_chambres']) && $row['nb_chambres'] !== null ? (int) $row['nb_chambres'] : (int) ($this->extractMarker($reviewComment, 'SNAPSHOT_ROOMS') ?: 0);
        $clientId = isset($row['client_id']) && $row['client_id'] !== null ? (int) $row['client_id'] : (int) ($this->extractMarker($reviewComment, 'USER_ID') ?: 0);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'codeConfirmation' => (string) ($row['code_confirmation'] ?? ''),
            'datePaiement' => $row['date_paiement'] ? new \DateTimeImmutable((string) $row['date_paiement']) : null,
            'montantTotal' => (float) ($row['montant_total'] ?? 0),
            'modePaiementLabel' => $mode,
            'normalizedStatutPaiement' => $status,
            'rating' => isset($row['rating']) ? (int) $row['rating'] : null,
            'reviewComment' => $reviewComment !== '' ? $reviewComment : null,
            'panierId' => isset($row['panier_id']) && $row['panier_id'] !== null ? (int) $row['panier_id'] : null,
            'clientId' => $clientId,
            'displayName' => $serviceName,
            'displayCity' => $serviceCity,
            'typeService' => $typeService,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,
            'nbPersonnes' => $nbPersonnes,
            'nbChambres' => $nbChambres,
            'clientName' => $clientName !== '' ? $clientName : 'Client indisponible',
            'clientEmail' => (string) ($row['client_email'] ?? ''),
            'orphaned' => empty($row['panier_id']),
        ];
    }

    private function matchesRowToUser(array $row, int $userId): bool
    {
        return (int) ($row['clientId'] ?? 0) === $userId || str_contains((string) ($row['reviewComment'] ?? ''), '[USER_ID:' . $userId . ']');
    }

    private function extractMarker(string $text, string $key): ?string
    {
        if (preg_match('/\[' . preg_quote($key, '/') . ':(.*?)\]/', $text, $m)) {
            return trim((string) ($m[1] ?? ''));
        }

        return null;
    }

    private function parseDateMarker(string $text, string $key): ?\DateTimeImmutable
    {
        $value = $this->extractMarker($text, $key);
        if (!$value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeStatus(string $value): string
    {
        $value = strtolower($value);
        if (str_contains($value, 'pay')) {
            return 'Paye';
        }
        if (str_contains($value, 'cours')) {
            return 'En cours de paiement';
        }
        if (str_contains($value, 'rembours')) {
            return 'Rembourse';
        }
        if (str_contains($value, 'attente')) {
            return 'En attente';
        }
        if (str_contains($value, 'annul')) {
            return 'Annule';
        }
        return $value !== '' ? ucfirst($value) : 'En attente';
    }
}
