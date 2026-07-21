<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/orders_common.php';

// Refus (renvoi manuel motivé) d'une commande par la cabine — remplace
// DB.business.refuseRequest() (js/db.js). Même correctif CAS de propriété
// qu'orders_accept.php : la commande n'est libérée que si elle appartenait
// bien à la cabine appelante. Réattribution en 2 temps atomiques
// (libération puis revendication, chacune avec sa propre garde WHERE)
// plutôt qu'un seul UPDATE, pour rester correct même si une autre requête
// (réattribution admin manuelle, balayage des retards) agit sur la même
// commande entre les deux étapes.
$me = requireAuth(['cabine']);

$in            = body();
$txnId         = (string)($in['transaction_id'] ?? '');
$motif         = isset($in['motif']) ? (string)$in['motif'] : null;
$justification = $motif === 'autre' ? (string)($in['justification'] ?? '') : null;
if ($txnId === '') fail('Identifiant de commande requis.');

$pdo = db();

// Étape 1 — libération (CAS de propriété) : échoue si la commande n'était
// déjà plus assignée à cette cabine (déjà traitée, ou réattribuée entre
// l'affichage et ce clic).
$stmt = $pdo->prepare("UPDATE transactions SET
    dernier_renvoi_motif = ?, dernier_renvoi_justification = ?, dernier_renvoi_date = NOW(), dernier_renvoi_cabine_id = ?,
    cabine_id = NULL
  WHERE id = ? AND cabine_id = ? AND statut = 'en_attente'");
$stmt->execute([$motif, $justification, $me['id'], $txnId, $me['id']]);
if ($stmt->rowCount() === 0) fail('Demande introuvable ou déjà traitée.', 409);

$txnStmt = $pdo->prepare('SELECT * FROM transactions WHERE id = ?');
$txnStmt->execute([$txnId]);
$txn = $txnStmt->fetch();

// Compte le renvoi, quelle que soit l'issue de la réattribution ci-dessous.
$pdo->prepare('UPDATE profiles SET commandes_renvoyees = commandes_renvoyees + 1 WHERE id = ?')->execute([$me['id']]);

// Fenêtre glissante de 2 min : 5 renvois → suspension automatique 24h
// (voir DB.cabineRefusals et suspendCabineAuto, orders_common.php).
$pdo->prepare('INSERT INTO cabine_refusals (id, cabine_id, date) VALUES (?, ?, NOW())')->execute([uuid4(), $me['id']]);
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM cabine_refusals WHERE cabine_id = ? AND date >= NOW() - INTERVAL 120 SECOND");
$countStmt->execute([$me['id']]);
if ((int)$countStmt->fetchColumn() >= 5) {
  suspendCabineAuto($pdo, $me['id'], '5 commandes renvoyées en moins de 2 minutes');
}

// Étape 2 — revendication : seulement si la commande est toujours
// non assignée (garde WHERE cabine_id IS NULL, même principe CAS).
$target = findReassignmentTarget($pdo, $me['id'], $txn['operateur'], $txn['type']);
$reassignedTo = null;
if ($target) {
  $claim = $pdo->prepare("UPDATE transactions SET cabine_id = ?, date_assignation = NOW(), alerte_envoyee = 0 WHERE id = ? AND cabine_id IS NULL AND statut = 'en_attente'");
  $claim->execute([$target['id'], $txnId]);
  if ($claim->rowCount() > 0) {
    $reassignedTo = $target['id'];
    createNotification($target['id'], 'Nouvelle demande de transfert ' . $txn['operateur'] . ' ' . number_format((float)$txn['montant'], 0, ',', ' ') . ' F (réaffectée).', 'new_request');
  }
}
if ($reassignedTo === null) {
  createNotification($me['id'], 'La commande que vous avez renvoyée reste en attente côté administration — aucune autre cabine connectée disponible.', 'info');
}

echo json_encode(['ok' => true, 'reassignedTo' => $reassignedTo]);
