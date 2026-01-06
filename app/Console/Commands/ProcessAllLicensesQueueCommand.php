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
        $this->newLine();

        $failed = 0;
        $results = [];

        foreach ($licencias as $licencia) {
            // Extraer subdominio: "app-rays.witwan.com" → "app-rays"
            $envName = explode('.', $licencia->app_url)[0];

            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("Licencia: {$licencia->licencia_nombre}");
            $this->line("APP_ENV: {$envName}");
            $this->line("URL: {$licencia->app_url}");

            // Ejecutar comando con el APP_ENV de la licencia
            $process = new Process([
                'php', 'artisan', 'queue:process-events', "--limit={$limit}", "--tipo={$tipo}"
            ], base_path(), [
                'APP_ENV' => $envName
            ]);

            $process->setTimeout(120);

            try {
                $process->run();

                $stdout = trim($process->getOutput());
                $stderr = trim($process->getErrorOutput());
                $exitCode = $process->getExitCode();

                if ($process->isSuccessful()) {
                    $this->info("Estado: OK (exit code: {$exitCode})");
                    if ($stdout) {
                        $this->line("Output: {$stdout}");
                    }
                    $results[] = [
                        'licencia' => $licencia->licencia_nombre,
                        'estado' => 'OK',
                        'mensaje' => $stdout ?: 'Sin output',
                    ];
                } else {
                    $failed++;
                    $this->error("Estado: ERROR (exit code: {$exitCode})");

                    if ($stdout) {
                        $this->line("STDOUT:");
                        $this->line($this->formatOutput($stdout));
                    }

                    if ($stderr) {
                        $this->error("STDERR:");
                        $this->line($this->formatOutput($stderr));
                    }

                    $results[] = [
                        'licencia' => $licencia->licencia_nombre,
                        'estado' => "ERROR ({$exitCode})",
                        'mensaje' => $stderr ?: $stdout ?: 'Sin mensaje de error',
                    ];
                }
            } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
                $failed++;
                $this->error("Estado: TIMEOUT (>120s)");
                $results[] = [
                    'licencia' => $licencia->licencia_nombre,
                    'estado' => 'TIMEOUT',
                    'mensaje' => 'El proceso excedió el tiempo límite de 120 segundos',
                ];
            } catch (\Exception $e) {
                $failed++;
                $this->error("Estado: EXCEPCIÓN");
                $this->error("Tipo: " . get_class($e));
                $this->error("Mensaje: {$e->getMessage()}");
                $results[] = [
                    'licencia' => $licencia->licencia_nombre,
                    'estado' => 'EXCEPCIÓN',
                    'mensaje' => get_class($e) . ': ' . $e->getMessage(),
                ];
            }

            $this->newLine();
        }

        // Resumen final
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("RESUMEN");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $this->table(
            ['Licencia', 'Estado', 'Mensaje'],
            array_map(fn($r) => [
                $r['licencia'],
                $r['estado'],
                \Illuminate\Support\Str::limit($r['mensaje'], 60),
            ], $results)
        );

        $this->newLine();
        $total = count($licencias);
        $exitosos = $total - $failed;
        $this->info("Total: {$total} | Exitosos: {$exitosos} | Fallidos: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Formatea el output agregando indentación para mejor legibilidad
     */
    private function formatOutput(string $output): string
    {
        $lines = explode("\n", $output);
        return implode("\n", array_map(fn($line) => "  │ {$line}", $lines));
    }
}
