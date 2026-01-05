<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class ProcessAllLicensesQueueCommand extends Command
{
    protected $signature = 'queue:process-all-licenses
        {--limit=10 : Number of events to process per license}
        {--tipo=minuto : Event frequency type (minuto, horario, nocturno)}';

    protected $description = 'Process queue events for all active licenses (activar_cron=1 and app_url set)';

    public function handle(): int
    {
        // Obtener licencias activas con app_url configurado
        $licencias = DB::connection('license')
            ->table('licencia')
            ->where('activar_cron', 1)
            ->whereNotNull('app_url')
            ->where('app_url', '!=', '')
            ->get();

        if ($licencias->isEmpty()) {
            $this->warn('No hay licencias con activar_cron=1 y app_url configurado');
            return self::SUCCESS;
        }

        $limit = $this->option('limit');
        $tipo = $this->option('tipo');

        $this->info("Procesando {$licencias->count()} licencias (tipo: {$tipo})...");

        $failed = 0;

        foreach ($licencias as $licencia) {
            // Extraer subdominio: "app-rays.witwan.com" → "app-rays"
            $envName = explode('.', $licencia->app_url)[0];

            $this->line("→ {$licencia->licencia_nombre} (APP_ENV={$envName})");

            // Ejecutar comando con el APP_ENV de la licencia
            $process = new Process([
                'php', 'artisan', 'queue:process-events', "--limit={$limit}", "--tipo={$tipo}"
            ], base_path(), [
                'APP_ENV' => $envName
            ]);

            $process->setTimeout(120);
            $process->run();

            if ($process->isSuccessful()) {
                $this->info("  ✓ " . trim($process->getOutput()));
            } else {
                $this->error("  ✗ Error: " . trim($process->getErrorOutput()));
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Completado. Fallidos: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
