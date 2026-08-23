<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Compila las queries con la gramática de MySQL, sin conectarse a ninguna base.
 *
 * Los tests que verifican el mapeo filtro -> SQL comparan contra SQL de MySQL
 * (backticks, `date()`), que es lo que corre en producción. Pero phpunit.xml
 * fuerza `DB_CONNECTION=sqlite` para los tests que sí tocan una base, y con esa
 * conexión el mismo builder emite comillas dobles y `strftime()`, así que las
 * comparaciones fallan aunque la query esté bien.
 *
 * Cambiar la conexión por defecto a `mysql` alcanza: `toSql()` sólo necesita la
 * gramática, y el PDO de Laravel es perezoso —nunca se abre si no se ejecuta la
 * query—, así que estos tests siguen sin depender de un servidor.
 */
trait CompilaSqlDeMysql
{
    protected function usarGramaticaMysql(): void
    {
        config(['database.default' => 'mysql']);

        DB::purge();
    }
}
