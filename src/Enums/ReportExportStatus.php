<?php

namespace Intrfce\LaravelReportable\Enums;

enum ReportExportStatus: string
{
    case Pending = 'pending';
    case Dispatched = 'dispatched';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Dispatched => 'Dispatched',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed]);
    }

    public function isRunning(): bool
    {
        return in_array($this, [self::Dispatched, self::Processing]);
    }
}
