<?php

namespace App\Console\Commands;

use App\Services\QueueEventService;
use Illuminate\Console\Command;

class ProcessQueueEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:process-events
        {--limit=1 : Number of events to process}
        {--tipo=minuto : Event frequency type (minuto, horario, nocturno)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending events in the colaevento queue (reserva sync, automatic invoicing)';

    protected QueueEventService $queueEventService;

    public function __construct(QueueEventService $queueEventService)
    {
        parent::__construct();
        $this->queueEventService = $queueEventService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $tipo = $this->option('tipo');

        $this->info("Processing queue events (limit: {$limit}, tipo: {$tipo})...");

        $stats = $this->queueEventService->processPendingEvents($limit, $tipo);

        $this->info("Processed: {$stats['processed']}");

        if ($stats['failed'] > 0) {
            $this->error("Failed: {$stats['failed']}");
        }

        if ($stats['skipped'] > 0) {
            $this->warn("Skipped: {$stats['skipped']}");
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
