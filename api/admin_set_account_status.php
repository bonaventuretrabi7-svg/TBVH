<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Change le statut d'un compte (bloqué -> actif, suspendre/réactiver un
// client, activer/désactiver une cabine) -- remplace DB.users.update()
// local (js/admin.js : suspendUser()/activateUser()/toggleCabine()/
// debloquerCompte()). Aucune de ces actions n'atteignait jusqu'ici le
// serveur : un compte "suspendu"/"désactivé" restait pleinement
// fonctionnel côté serveur, et un compte "débloqué" restait bloqué pour
// toujours (login.php vérifie exactement statut = 'bloqué' en base).
$me = requireAuth(['admin']);

$in = body();
$targetId = (string)($in['id'] ?? '');
$statut = (string)($in['statut'] ?? '');
if ($targetId === '') fail('Identifiant de compte requis.');
if (!in_array($statut, ['actif', 'suspendu', 'inactif', 'bloqué'], true)) fail('Statut invalide.');

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM profiles WHERE id = ?');
$stmt->execute([$targetId]);
$target = $stmt->fetch();
if (!$target) fail('Compte introuvable.', 404);

// Un admin simple ne peut pas agir sur un compte administrateur — réservé
// au super admin, même garde que les autres actions super-only.
if ($target['role'] === 'admin' && $me['admin_level'] !== 'super') {
  fail('Seul le super administrateur peut modifier un compte administrateur.', 403);
}

// Une suspension MANUELLE de cabine (suspendu_by non nul) ne peut être
// levée que par l'administrateur qui l'a posée, ou par le super
// administrateur — même règle déjà appliquée côté client (toggleCabine(),
// js/admin.js), revérifiée ici pour ne pas dépendre uniquement d'un
// contrôle côté appelant.
if ($statut === 'actif' && $target['role'] === 'cabine' && $target['statut'] === 'suspendu'
    && !empty($target['suspendu_by']) && $target['suspendu_by'] !== $me['id'] && $me['admin_level'] !== 'super') {
  fail('Seul l\'administrateur à l\'origine ou le super administrateur peut débloquer ce compte.', 403);
}

$columns = ['statut = ?'];
$params  = [$statut];

if ($statut === 'actif') {
  $columns[] = 'tentatives_echouees = 0';
  if ($target['role'] === 'cabine') {
    $columns[] = 'suspendu_auto = 0';
    $columns[] = 'suspendu_by = NULL';
    $columns[] = 'suspendu_motif = NULL';
    $columns[] = 'suspendu_jusqu = NULL';
  }
}

$params[] = $targetId;
$pdo->prepare('UPDATE profiles SET ' . implode(', ', $columns) . ' WHERE id = ?')->execute($params);

echo json_encode(['ok' => true]);
