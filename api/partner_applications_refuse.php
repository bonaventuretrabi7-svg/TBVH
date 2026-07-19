<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Refuse une candidature partenaire — remplace refusePartnerRequest()
// (js/admin.js).
requireAuth(['admin']);

$in = body();
$id = (string)($in['application_id'] ?? '');
if ($id === '') fail('Identifiant de candidature requis.');

$pdo = db();
$appStmt = $pdo->prepare("SELECT telephone FROM partner_applications WHERE id = ? AND statut = 'en_attente'");
$appStmt->execute([$id]);
$app = $appStmt->fetch();
if (!$app) fail('Candidature introuvable ou déjà traitée.');

$stmt = $pdo->prepare("UPDATE partner_applications SET statut = 'refusée', date_traitement = NOW() WHERE id = ?");
$stmt->execute([$id]);

// Même logique que partner_applications_validate.php : prévient le
// candidat dans son fil de notifications s'il a aussi un compte client.
$clientStmt = $pdo->prepare("SELECT id FROM profiles WHERE telephone = ? AND role = 'client'");
$clientStmt->execute([$app['telephone']]);
$clientProfile = $clientStmt->fetch();
if ($clientProfile) {
  createNotification($clientProfile['id'], 'Votre demande de partenariat n\'a pas été retenue.', 'info');
}

echo json_encode(['ok' => true]);
