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
        $this->newLine();

        $stats = $this->queueEventService->processPendingEvents($limit, $tipo);

        // Mostrar eventos exitosos
        if (!empty($stats['success'])) {
            $this->info("EVENTOS PROCESADOS ({$stats['processed']}):");
            foreach ($stats['success'] as $success) {
                $this->line("  OK  [{$success['evento_id']}] {$success['tipo']}/{$success['modelo']} (ID: {$success['id_relacionado']})");
            }
            $this->newLine();
        }

        // Mostrar errores detallados
        if (!empty($stats['errors'])) {
            $this->error("ERRORES ({$stats['failed']}):");
            $this->newLine();

            foreach ($stats['errors'] as $i => $error) {
                $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->error("Error #" . ($i + 1));
                $this->line("Evento ID: {$error['evento_id']}");
                $this->line("Tipo: {$error['tipo']} / Modelo: {$error['modelo']}");
                $this->line("ID Relacionado: {$error['id_relacionado']}");
                $this->newLine();
                $this->error("Mensaje: {$error['error']}");
                $this->line("Archivo: {$error['file']}");
                $this->newLine();
                $this->warn("Stack trace:");
                $this->line($this->formatTrace($error['trace']));
                $this->newLine();
            }
        }

        // Resumen
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("RESUMEN:");
        $this->info("  Processed: {$stats['processed']}");

        if ($stats['failed'] > 0) {
            $this->error("  Failed: {$stats['failed']}");
        } else {
            $this->line("  Failed: 0");
        }

        if ($stats['skipped'] > 0) {
            $this->warn("  Skipped: {$stats['skipped']}");
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Formatea el trace con indentación
     */
    private function formatTrace(string $trace): string
    {
        $lines = explode("\n", $trace);
        return implode("\n", array_map(fn($line) => "  │ {$line}", $lines));
    }
}
