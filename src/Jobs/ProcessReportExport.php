<?php

namespace Intrfce\LaravelReportable\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Intrfce\LaravelReportable\Models\ReportExport;
use Throwable;

class ProcessReportExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public ReportExport $export
    ) {}

    public function handle(): void
    {
        $this->export->markAsProcessing();

        try {
            $reportable = $this->export->getReportable();
            $reportable->processExport($this->export);

            $this->export->markAsCompleted();
        } catch (Throwable $e) {
            $this->export->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->export->markAsFailed($exception->getMessage());
    }
}
