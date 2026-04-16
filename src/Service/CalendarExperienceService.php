<?php

namespace App\Service;

use App\Entity\Reservation;

class CalendarExperienceService
{
    public function buildGoogleCalendarUrl(Reservation $reservation): ?string
    {
        $data = $this->reservationData($reservation);
        if (!$data || !$data['start'] || !$data['end']) {
            return null;
        }

        $params = http_build_query([
            'action' => 'TEMPLATE',
            'text' => $data['title'],
            'dates' => $this->toGoogleDate($data['start']) . '/' . $this->toGoogleDate($data['end']),
            'details' => 'Reservation ' . ($reservation->getCodeConfirmation() ?: ''),
            'location' => $data['location'],
        ]);

        return 'https://calendar.google.com/calendar/render?' . $params;
    }

    public function buildIcs(Reservation $reservation): ?string
    {
        $data = $this->reservationData($reservation);
        if (!$data || !$data['start'] || !$data['end']) {
            return null;
        }

        $title = $this->escape($data['title']);
        $location = $this->escape($data['location']);
        $description = $this->escape('Reservation ' . ($reservation->getCodeConfirmation() ?: ''));

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Tabaany//Reservations//FR',
            'BEGIN:VEVENT',
            'UID:' . md5((string) $reservation->getId()) . '@tabaany',
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . $this->toGoogleDate($data['start']),
            'DTEND:' . $this->toGoogleDate($data['end']),
            'SUMMARY:' . $title,
            'DESCRIPTION:' . $description,
            'LOCATION:' . $location,
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);
    }

    private function reservationData(Reservation $reservation): ?array
    {
        $panier = null;
        try {
            $panier = $reservation->getPanier();
        } catch (\Throwable) {
            $panier = null;
        }

        if ($panier && $panier->getDateDebut() && $panier->getDateFin()) {
            return [
                'title' => $panier->getDisplayName() ?: ('Reservation ' . $reservation->getCodeConfirmation()),
                'location' => $panier->getDisplayCity() ?: $panier->getServiceTypeLabel(),
                'start' => $panier->getDateDebut(),
                'end' => $panier->getDateFin(),
            ];
        }

        $comment = (string) ($reservation->getReviewComment() ?? '');
        $title = $this->marker($comment, 'SNAPSHOT_NAME') ?: ('Reservation ' . $reservation->getCodeConfirmation());
        $location = $this->marker($comment, 'SNAPSHOT_CITY') ?: ($this->marker($comment, 'SNAPSHOT_TYPE') ?: 'Tunisie');
        $start = $this->markerDate($comment, 'SNAPSHOT_START');
        $end = $this->markerDate($comment, 'SNAPSHOT_END');

        if (!$start || !$end) {
            return null;
        }

        return [
            'title' => $title,
            'location' => $location,
            'start' => $start,
            'end' => $end,
        ];
    }

    private function marker(string $text, string $key): ?string
    {
        if (preg_match('/\[' . preg_quote($key, '/') . ':(.*?)\]/', $text, $match)) {
            return trim((string) ($match[1] ?? ''));
        }

        return null;
    }

    private function markerDate(string $text, string $key): ?\DateTimeInterface
    {
        $value = $this->marker($text, $key);
        if (!$value) {
            return null;
        }

        try {
            return new \DateTime($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function toGoogleDate(\DateTimeInterface $date): string
    {
        $utc = (new \DateTimeImmutable($date->format('Y-m-d H:i:s'), $date->getTimezone()))->setTimezone(new \DateTimeZone('UTC'));
        return $utc->format('Ymd\THis\Z');
    }

    private function escape(string $value): string
    {
        return str_replace(["\\", ';', ',', "\n", "\r"], ["\\\\", '\\;', '\\,', '\\n', ''], $value);
    }
}
