<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Admin : suspension manuelle d'une cabine (indéfinie, pas d'échéance
// automatique) — remplace DB.business.suspendCabineManually() (js/db.js).
// Levée uniquement par cet administrateur ou le super administrateur (voir
// toggleCabine(), js/admin.js — reste local, mutation directe du profil
// hors du périmètre du moteur de commandes).
$me = requireAuth(['admin']);

$in = body();
$cabineId = (string)($in['cabine_id'] ?? '');
$motif = trim((string)($in['motif'] ?? ''));
if ($cabineId === '') fail('Identifiant de cabine requis.');
if ($motif === '') fail('Le motif est obligatoire.');

$pdo = db();
// Existence vérifiée séparément (pas via rowCount() de l'UPDATE ci-dessous)
// : rowCount() reflète les LIGNES CHANGÉES, pas les lignes matchées par le
// WHERE — une resuspension avec exactement le même motif par le même admin
// laisserait rowCount() à 0 malgré une cabine bien existante.
$checkStmt = $pdo->prepare("SELECT id FROM profiles WHERE id = ? AND role = 'cabine'");
$checkStmt->execute([$cabineId]);
if (!$checkStmt->fetch()) fail('Cabine introuvable.');

$pdo->prepare("UPDATE profiles SET statut='suspendu', suspendu_auto=0, suspendu_by=?, suspendu_motif=?, suspendu_jusqu=NULL WHERE id = ? AND role = 'cabine'")
    ->execute([$me['id'], $motif, $cabineId]);

$pdo->prepare('INSERT INTO suspension_logs (id, cabine_id, motif, auto, date_debut, date_fin_prevue, date_levee, levee_par) VALUES (?, ?, ?, 0, NOW(), NULL, NULL, NULL)')
    ->execute([uuid4(), $cabineId, $motif]);
createNotification($cabineId, 'Votre compte a été suspendu par l\'administration : ' . $motif, 'warning');

echo json_encode(['ok' => true]);
