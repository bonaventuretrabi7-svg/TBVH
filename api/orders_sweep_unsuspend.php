<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/orders_common.php';

// Parcourt toutes les cabines actuellement suspendues automatiquement et
// lève celles dont l'échéance de 24h est dépassée — remplace
// DB.business.sweepAutoUnsuspensions() (js/db.js). Couvre aussi une cabine
// suspendue qui n'a plus aucune commande en attente (le cas normal), donc
// jamais visitée par orders_sweep.php.
requireAuth();

$pdo = db();
$ids = array_column(
  $pdo->query("SELECT id FROM profiles WHERE role = 'cabine' AND statut = 'suspendu' AND suspendu_auto = 1")->fetchAll(),
  'id'
);

$liftedCount = 0;
foreach ($ids as $id) {
  if (checkAutoUnsuspend($pdo, $id)) $liftedCount++;
}

echo json_encode(['ok' => true, 'liftedCount' => $liftedCount]);
