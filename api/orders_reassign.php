<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/orders_common.php';

// Réassignation manuelle par l'administration — remplace
// DB.business.reassignTransaction()/bulkReassign() (js/db.js), fusionnées
// en un seul endpoint (transaction_ids accepte un tableau d'un ou
// plusieurs ids). Chaque commande est traitée indépendamment (un échec sur
// l'une n'empêche pas les autres) et son transfert est lui-même un CAS
// (WHERE statut='en_attente' dans le UPDATE) plutôt qu'un simple UPDATE
// sans garde.
requireAuth(['admin']);

$in = body();
$ids = $in['transaction_ids'] ?? [];
$newCabineId = (string)($in['cabine_id'] ?? '');
if (!is_array($ids) || !$ids || $newCabineId === '') fail('Paramètres invalides.');

$pdo = db();
$cabStmt = $pdo->prepare("SELECT * FROM profiles WHERE id = ? AND role = 'cabine'");
$cabStmt->execute([$newCabineId]);
$newCab = $cabStmt->fetch();
if (!$newCab) fail('Cabine invalide.');
// Une cabine qui n'est pas en service (suspendue ou en pause) ne doit
// jamais recevoir de commande, y compris via une réassignation manuelle —
// déjà appliqué pour l'attribution initiale (pickInitialCabine()) et la
// réassignation automatique (findReassignmentTarget()), manquait ici.
if ($newCab['statut'] !== 'actif') fail('Cette cabine est suspendue — impossible de lui assigner une commande.');
if (!empty($newCab['en_pause'])) fail('Cette cabine est en pause — impossible de lui assigner une commande.');

$results = [];
foreach ($ids as $txnId) {
  $txnId = (string)$txnId;

  if (isCabineAtLimit($pdo, $newCab)) {
    $results[] = ['id' => $txnId, 'ok' => false, 'error' => 'Cette cabine a atteint sa limite de commandes.'];
    continue;
  }

  $txnStmt = $pdo->prepare('SELECT * FROM transactions WHERE id = ?');
  $txnStmt->execute([$txnId]);
  $txn = $txnStmt->fetch();
  if (!$txn || $txn['statut'] !== 'en_attente') {
    $results[] = ['id' => $txnId, 'ok' => false, 'error' => 'Commande introuvable ou déjà traitée.'];
    continue;
  }

  $oldCabineId = $txn['cabine_id'];
  $upd = $pdo->prepare("UPDATE transactions SET cabine_id = ?, date_assignation = NOW(), alerte_envoyee = 0 WHERE id = ? AND statut = 'en_attente'");
  $upd->execute([$newCabineId, $txnId]);
  if ($upd->rowCount() === 0) {
    $results[] = ['id' => $txnId, 'ok' => false, 'error' => 'Commande introuvable ou déjà traitée.'];
    continue;
  }

  if ($oldCabineId) createNotification($oldCabineId, "La commande a été réassignée à une autre cabine par l'administration.", 'info');
  createNotification($newCabineId, "Nouvelle commande assignée par l'administration : " . $txn['operateur'] . ' ' . number_format((float)$txn['montant'], 0, ',', ' ') . ' F.', 'new_request');
  $results[] = ['id' => $txnId, 'ok' => true];
}

$okCount = count(array_filter($results, fn($r) => $r['ok']));
echo json_encode(['ok' => true, 'okCount' => $okCount, 'failCount' => count($results) - $okCount, 'results' => $results]);
