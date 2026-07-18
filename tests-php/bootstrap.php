<?php
declare(strict_types=1);

// Bootstrap PHPUnit — démarre le serveur PHP intégré sur api/ (avec
// api/config.php local pointant vers MariaDB local, jamais commité, voir
// .gitignore) pour que la suite tape les VRAIS endpoints en HTTP, exactement
// comme la prod, plutôt que de réimplémenter leur logique dans les tests.
// Un seul serveur partagé pour toute la suite, arrêté à la fin du process.

const TEST_API_HOST = '127.0.0.1';
const TEST_API_PORT = 8091;
const TEST_API_BASE = 'http://' . TEST_API_HOST . ':' . TEST_API_PORT;

const TEST_DB_HOST = '127.0.0.1';
const TEST_DB_NAME = 'kbineplus_test';
const TEST_DB_USER = 'root';
const TEST_DB_PASS = '';

if (!file_exists(__DIR__ . '/../api/config.php')) {
  fwrite(STDERR, "api/config.php manquant — voir tests-php/README.md pour la config MariaDB locale attendue.\n");
  exit(1);
}

$apiDir = realpath(__DIR__ . '/../api');
$logFile = sys_get_temp_dir() . '/kbineplus-test-server.log';

// Descripteurs 1/2 redirigés vers un FICHIER, jamais des pipes non vidés :
// le serveur intégré PHP journalise chaque requête sur stderr, et un pipe
// dont personne ne lit se remplit (petit buffer OS sous Windows) puis
// bloque l'écriture côté enfant — gelant le serveur (mono-thread) au
// milieu d'une requête, sans jamais répondre ni au client ni planter.
$cmd = sprintf('php -S %s:%d -t %s', TEST_API_HOST, TEST_API_PORT, escapeshellarg($apiDir));
$descriptors = [0 => ['pipe', 'r'], 1 => ['file', $logFile, 'a'], 2 => ['file', $logFile, 'a']];
$process = proc_open($cmd, $descriptors, $pipes, $apiDir);
if (!is_resource($process)) {
  fwrite(STDERR, "Impossible de démarrer le serveur PHP intégré (api/).\n");
  exit(1);
}
fclose($pipes[0]);

$ready = false;
for ($i = 0; $i < 50; $i++) {
  $ch = curl_init(TEST_API_BASE . '/settings_get.php');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT_MS, 300);
  curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($code > 0) { $ready = true; break; }
  usleep(100000);
}
if (!$ready) {
  proc_terminate($process);
  fwrite(STDERR, "Le serveur PHP intégré (api/) n'a jamais répondu sur " . TEST_API_BASE . ".\n");
  exit(1);
}

register_shutdown_function(function () use ($process, $pipes) {
  foreach ($pipes as $p) { if (is_resource($p)) fclose($p); }
  if (is_resource($process)) {
    proc_terminate($process);
    proc_close($process);
  }
});

require __DIR__ . '/Support/ApiClient.php';
require __DIR__ . '/Support/Fixtures.php';
require __DIR__ . '/ApiTestCase.php';
