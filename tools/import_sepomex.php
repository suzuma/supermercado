<?php

/**
 * Importador de datos SEPOMEX a la tabla codigos_postales.
 *
 * Uso:
 *   php tools/import_sepomex.php /ruta/al/archivo/CPdescarga.txt
 *
 * Descarga el archivo oficial en:
 *   https://www.correosdemexico.gob.mx/SSLServicios/ConsultaCP/CodigoPostal_Exportar.aspx
 *   (selecciona "Formato TXT", descarga y descomprime el ZIP)
 *
 * El archivo usa encoding Windows-1252 y separador pipe "|".
 */

declare(strict_types=1);

define('_BASE_PATH_', dirname(__DIR__) . '/');
require _BASE_PATH_ . 'config.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// ── Validar argumento ────────────────────────────────────────────
if ($argc < 2 || !file_exists($argv[1])) {
    fwrite(STDERR, "Uso: php tools/import_sepomex.php /ruta/CPdescarga.txt\n");
    exit(1);
}

$archivo = $argv[1];

// ── Conexión a BD ────────────────────────────────────────────────
$capsule = new Capsule();
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => $_ENV['DB_HOST']     ?? '127.0.0.1',
    'database'  => $_ENV['DB_DATABASE'] ?? 'supermercado',
    'username'  => $_ENV['DB_USERNAME'] ?? 'root',
    'password'  => $_ENV['DB_PASSWORD'] ?? '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// ── Limpiar tabla antes de importar ─────────────────────────────
echo "Limpiando tabla codigos_postales...\n";
Capsule::table('codigos_postales')->truncate();

// ── Leer y procesar CSV ──────────────────────────────────────────
$handle = fopen($archivo, 'r');
if (!$handle) {
    fwrite(STDERR, "No se pudo abrir el archivo.\n");
    exit(1);
}

$lote       = [];
$tamanoLote = 500;
$total      = 0;
$linea      = 0;

// El archivo oficial tiene 2 líneas de encabezado antes de los datos
$encabezados = 0;

while (($raw = fgets($handle)) !== false) {
    $linea++;

    // Convertir de Windows-1252 a UTF-8
    $row = mb_convert_encoding(trim($raw), 'UTF-8', 'Windows-1252');

    // Saltar las primeras 2 líneas (encabezados del SEPOMEX)
    if ($encabezados < 2) {
        $encabezados++;
        continue;
    }

    $cols = explode('|', $row);

    // El formato oficial tiene al menos 7 columnas:
    // [0]d_codigo [1]d_asenta [2]d_tipo_asenta [3]D_mnpio [4]d_estado [5]d_ciudad [6]d_CP ...
    if (count($cols) < 5) {
        continue;
    }

    $cp        = trim($cols[0]);
    $colonia   = trim($cols[1]);
    $municipio = trim($cols[3]);
    $estado    = trim($cols[4]);
    $ciudad    = isset($cols[5]) ? trim($cols[5]) : '';

    if (strlen($cp) !== 5 || empty($colonia)) {
        continue;
    }

    $lote[] = [
        'cp'        => $cp,
        'colonia'   => $colonia,
        'municipio' => $municipio,
        'estado'    => $estado,
        'ciudad'    => $ciudad,
    ];

    if (count($lote) >= $tamanoLote) {
        Capsule::table('codigos_postales')->insert($lote);
        $total += count($lote);
        $lote   = [];
        echo "\rInsertados: {$total}...";
    }
}

// Insertar registros restantes
if (!empty($lote)) {
    Capsule::table('codigos_postales')->insert($lote);
    $total += count($lote);
}

fclose($handle);

echo "\rImportación completada. Total de registros: {$total}\n";