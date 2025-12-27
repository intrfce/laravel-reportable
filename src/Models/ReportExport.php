<?php

namespace Intrfce\LaravelReportable\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Intrfce\LaravelReportable\Enums\ReportExportStatus;
use Intrfce\LaravelReportable\Jobs\ProcessReportExport;
use Intrfce\LaravelReportable\Reportable;

class ReportExport extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => ReportExportStatus::class,
        'query_bindings' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'rows_processed' => 'integer',
        'total_rows' => 'integer',
    ];

    protected static function booted(): void
    {
        static::created(function (ReportExport $export) {
            $export->dispatchJob();
        });
    }

    /**
     * Create a new export from a Reportable instance.
     */
    public static function fromReportable(Reportable $reportable): static
    {
        $query = $reportable->getBuiltQuery();

        return static::create([
            'reportable_class' => get_class($reportable),
            'serialized_reportable' => serialize($reportable),
            'query_sql' => $query->toRawSql(),
            'query_bindings' => $query->getBindings(),
            'status' => ReportExportStatus::Pending,
            'output_disk' => $reportable->disk(),
            'output_path' => $reportable->outputPath(),
        ]);
    }

    /**
     * Get the reportable instance.
     */
    public function getReportable(): Reportable
    {
        return unserialize($this->serialized_reportable);
    }

    /**
     * The export this was retried from.
     */
    public function retriedFrom(): BelongsTo
    {
        return $this->belongsTo(static::class, 'retried_from_id');
    }

    /**
     * Exports that are retries of this one.
     */
    public function retries(): HasMany
    {
        return $this->hasMany(static::class, 'retried_from_id');
    }

    /**
     * Create a retry of this export.
     */
    public function retry(): static
    {
        $reportable = $this->getReportable();
        $query = $reportable->getBuiltQuery();

        return static::create([
            'reportable_class' => $this->reportable_class,
            'serialized_reportable' => $this->serialized_reportable,
            'query_sql' => $query->toRawSql(),
            'query_bindings' => $query->getBindings(),
            'status' => ReportExportStatus::Pending,
            'output_disk' => $this->output_disk,
            'output_path' => $this->output_path,
            'retried_from_id' => $this->id,
        ]);
    }

    /**
     * Dispatch the processing job.
     */
    public function dispatchJob(): void
    {
        $this->update(['status' => ReportExportStatus::Dispatched]);

        ProcessReportExport::dispatch($this)
            ->onQueue(config('laravel-reportable.queue', 'default'))
            ->onConnection(config('laravel-reportable.connection'));
    }

    /**
     * Mark the export as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => ReportExportStatus::Processing,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the export as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => ReportExportStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the export as failed.
     */
    public function markAsFailed(string $message): void
    {
        $this->update([
            'status' => ReportExportStatus::Failed,
            'failed_at' => now(),
            'error_message' => $message,
        ]);
    }

    /**
     * Update the progress.
     */
    public function updateProgress(int $rowsProcessed, ?int $totalRows = null): void
    {
        $data = ['rows_processed' => $rowsProcessed];

        if ($totalRows !== null) {
            $data['total_rows'] = $totalRows;
        }

        $this->update($data);
    }

    /**
     * Check if the export can be retried.
     */
    public function canRetry(): bool
    {
        return $this->status === ReportExportStatus::Failed;
    }

    /**
     * Check if the export is finished.
     */
    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    /**
     * Check if the export is running.
     */
    public function isRunning(): bool
    {
        return $this->status->isRunning();
    }

    /**
     * Get the progress percentage.
     */
    public function progressPercentage(): ?float
    {
        if ($this->total_rows === null || $this->total_rows === 0) {
            return null;
        }

        return round(($this->rows_processed / $this->total_rows) * 100, 2);
    }
}
