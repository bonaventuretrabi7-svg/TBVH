<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/orders_common.php';

// Dès la connexion d'une cabine, lui réassigne automatiquement les
// commandes en attente non assignées (pool "administration") — remplace
// DB.business.assignPendingToCabine() (js/db.js). La plus ancienne d'abord,
// jusqu'à ce que sa limite de commandes soit atteinte ; chaque revendication
// est un CAS individuel (WHERE cabine_id IS NULL) pour rester correcte même
// si une autre cabine ou un balayage réclame la même commande en parallèle.
$me = requireAuth(['cabine']);

if ($me['statut'] !== 'actif' || !empty($me['en_pause'])) {
  echo json_encode(['ok' => true, 'count' => 0]);
  exit;
}

$pdo = db();

if (hasBlockingReclamation($pdo, $me['id'])) {
  echo json_encode(['ok' => true, 'count' => 0]);
  exit;
}

$stmt = $pdo->prepare("SELECT * FROM transactions WHERE statut = 'en_attente' AND cabine_id IS NULL
  AND (client_id IS NULL OR client_id != ?) ORDER BY date ASC");
$stmt->execute([$me['id']]);
$pool = $stmt->fetchAll();

$count = 0;
foreach ($pool as $t) {
  if (isCabineAtLimit($pdo, $me)) break;
  if (!cabineAcceptsNetwork($me, $t['operateur'])) continue;
  if (!cabineAcceptsService($me, $t['type'])) continue;

  $claim = $pdo->prepare("UPDATE transactions SET cabine_id = ?, date_assignation = NOW() WHERE id = ? AND cabine_id IS NULL AND statut = 'en_attente'");
  $claim->execute([$me['id'], $t['id']]);
  if ($claim->rowCount() > 0) {
    $count++;
    createNotification($me['id'], 'Nouvelle commande assignée : ' . $t['operateur'] . ' ' . number_format((float)$t['montant'], 0, ',', ' ') . ' F.', 'new_request');
  }
}

echo json_encode(['ok' => true, 'count' => $count]);
