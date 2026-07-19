<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Création d'une commande "service avancé" (Facture / Recharge UV /
// Exchange) par le client — remplace la version 100% locale de
// _svcDebitAndRecord() (js/client.js), qui ne débitait/enregistrait que
// côté appareil et restait donc invisible de l'administration (voir
// loadTransactions()/loadRechargeUvAdmin()/loadExchangeAdmin(), js/admin.js,
// déjà filtrés sur `type` mais jamais alimentés côté serveur pour ces 3
// types). Version minimale : pas de circuit d'acceptation cabiniste, la
// commande reste 'en_attente' comme aujourd'hui — `cabine_id` toujours
// NULL (profiles n'a pas de colonne cabine_id, le me.cabine_id lu
// localement n'a donc jamais de valeur réelle serveur).
$me = requireAuth(['client']);

$in        = body();
$type      = (string)($in['type'] ?? '');
$montant   = (int)($in['montant'] ?? 0);
$service   = isset($in['service']) ? (string)$in['service'] : '';
$operateur = isset($in['operateur']) ? (string)$in['operateur'] : '';
$numero    = isset($in['numero']) ? (string)$in['numero'] : '';
$details   = array_key_exists('details', $in) ? json_encode($in['details']) : null;
$notes     = isset($in['notes']) ? (string)$in['notes'] : null;

if (!in_array($type, ['facture', 'recharge_uv', 'exchange'], true)) fail('Type de commande invalide.');
if ($montant <= 0) fail('Montant invalide.');
if ($type === 'recharge_uv' && $montant < 10000) fail('Montant minimum : 10 000 FCFA.');

$FRAIS_SERVICE_AVANCE = 200;
$totalDebit = $montant + $FRAIS_SERVICE_AVANCE;

$pdo = db();
$pdo->beginTransaction();
try {
  $debit = $pdo->prepare('UPDATE profiles SET solde = solde - ? WHERE id = ? AND solde >= ?');
  $debit->execute([$totalDebit, $me['id'], $totalDebit]);
  if ($debit->rowCount() === 0) {
    $pdo->rollBack();
    fail('Solde insuffisant (montant + 200 FCFA de frais de service).', 400);
  }

  $txnId = uuid4();
  $pdo->prepare('INSERT INTO transactions
      (id, client_id, cabine_id, type, service, operateur, numero_beneficiaire, montant, frais_service, statut, details, notes, date)
      VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, \'en_attente\', ?, ?, NOW())')
      ->execute([$txnId, $me['id'], $type, $service, $operateur, $numero, $montant, $FRAIS_SERVICE_AVANCE, $details, $notes]);

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  throw $e;
}

createNotification($me['id'], 'Votre demande de ' . number_format((float)$montant, 0, ',', ' ') . ' F (' . $service . ') est en attente de traitement.', 'info');

$txnStmt = $pdo->prepare('SELECT * FROM transactions WHERE id = ?');
$txnStmt->execute([$txnId]);
echo json_encode(['ok' => true, 'transaction' => decodeJsonColumns($txnStmt->fetch(), ['details'])]);
