<?php
declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

final class ProbationPolicy
{
    public static function maximumDays(string $education, string $position): int
    {
        $position = mb_strtolower($position, 'UTF-8');
        if (preg_match('/quản lý|trưởng phòng|giám đốc/u', $position) === 1) {
            return 180;
        }

        $education = mb_strtolower($education, 'UTF-8');
        if (preg_match('/cao đẳng|đại học|cử nhân|kỹ sư|thạc sĩ|tiến sĩ/u', $education) === 1) {
            return 60;
        }
        if (str_contains($education, 'trung cấp')) {
            return 30;
        }

        return 6;
    }

    public static function endDate(string $startDate, int $days): string
    {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        if ($start === false || $days < 1) {
            throw new \InvalidArgumentException('Ngày bắt đầu hoặc thời hạn thử việc không hợp lệ.');
        }

        return $start->modify('+' . ($days - 1) . ' days')->format('Y-m-d');
    }

    public static function durationDays(string $startDate, string $endDate): ?int
    {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        return (int) $start->diff($end)->days + 1;
    }
}
