<?php

namespace App\Services\Cooperative;

use InvalidArgumentException;

/**
 * Aturan status pengajuan pinjaman (prinsip maker-checker, bagian 17 dokumen analisis).
 */
class LoanApplicationService
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_POSTED = 'posted';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_SUBMITTED => 'Menunggu Persetujuan',
        self::STATUS_APPROVED => 'Disetujui',
        self::STATUS_REJECTED => 'Ditolak',
        self::STATUS_CANCELLED => 'Dibatalkan',
        self::STATUS_POSTED => 'Diposting ke Pinjaman',
    ];

    /**
     * Transisi status yang sah.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::STATUS_SUBMITTED => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_APPROVED => [self::STATUS_CANCELLED],
        self::STATUS_REJECTED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_POSTED => [],
    ];

    public function __construct(private readonly LoanSimulationService $simulations) {}

    /**
     * @return array{summary: array<string, int|float|string>, schedule: list<array<string, int|bool|string>>}
     */
    public function simulate(int $principal, int $tenorMonths, float $annualRatePercent, string $method, int $adminFee = 0, string $adminFeeType = 'exclude'): array
    {
        return $this->simulations->simulate($principal, $tenorMonths, $annualRatePercent, $method, $adminFee, $adminFeeType);
    }

    /**
     * @throws InvalidArgumentException ketika transisi tidak sah
     */
    public function assertTransition(string $currentStatus, string $targetStatus): void
    {
        if (! isset(self::TRANSITIONS[$currentStatus])) {
            throw new InvalidArgumentException("Status saat ini tidak dikenal: {$currentStatus}.");
        }

        $allowed = self::TRANSITIONS[$currentStatus];

        if (! in_array($targetStatus, $allowed, true)) {
            throw new InvalidArgumentException(
                'Perubahan status dari '.$this->statusLabel($currentStatus).' ke '.$this->statusLabel($targetStatus).' tidak diizinkan.'
            );
        }
    }

    /**
     * Maker tidak boleh menyetujui/menolak pengajuannya sendiri.
     */
    public function canDecide(int $applicationApplicantUserId, int $actorUserId): bool
    {
        return $applicationApplicantUserId !== $actorUserId;
    }

    /**
     * Pembatalan: pada submitted hanya pembuat boleh membatalkan;
     * pada approved hanya orang lain (bukan pembuat) boleh membatalkan persetujuan.
     */
    public function canCancel(int $applicantUserId, int $actorUserId, string $currentStatus): bool
    {
        return match ($currentStatus) {
            self::STATUS_SUBMITTED => $applicantUserId === $actorUserId,
            self::STATUS_APPROVED => $applicantUserId !== $actorUserId,
            default => false,
        };
    }

    public function isFinal(string $status): bool
    {
        return in_array($status, [self::STATUS_POSTED, self::STATUS_REJECTED, self::STATUS_CANCELLED], true);
    }

    public function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst($status);
    }

    public function statusBadgeClass(string $status): string
    {
        return match ($status) {
            self::STATUS_SUBMITTED => 'bg-amber-50 text-amber-700 border-amber-200',
            self::STATUS_APPROVED => 'bg-green-50 text-green-700 border-green-200',
            self::STATUS_REJECTED => 'bg-red-50 text-red-700 border-red-200',
            self::STATUS_CANCELLED => 'bg-gray-100 text-gray-600 border-gray-200',
            self::STATUS_POSTED => 'bg-blue-50 text-blue-700 border-blue-200',
            default => 'bg-gray-100 text-gray-600 border-gray-200',
        };
    }
}
