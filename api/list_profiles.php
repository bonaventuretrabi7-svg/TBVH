<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Liste des comptes (client/cabine/admin) — utilisée par le tableau de
// bord admin (voir refreshUsersFromServer() dans js/admin.js) pour
// refléter TOUS les comptes existants sur le serveur, pas seulement ceux
// déjà connus sur l'appareil de l'admin (voir le diagnostic : un client
// inscrit lui-même depuis son propre téléphone n'apparaissait jamais dans
// les listes de l'admin, qui ne lisaient auparavant que le cache local).
// Réservée à un jeton admin valide — jamais les mots de passe (hash
// retiré de chaque ligne).
requireAdminToken();

$in = body();
$role = isset($in['role']) && $in['role'] !== '' ? (string)$in['role'] : null;
if ($role !== null && !in_array($role, ['client', 'cabine', 'admin'], true)) fail('Rôle invalide.');

$sql = 'SELECT * FROM profiles';
$params = [];
if ($role !== null) { $sql .= ' WHERE role = ?'; $params[] = $role; }

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
foreach ($rows as &$row) { unset($row['mot_de_passe_hash']); }
unset($row);

echo json_encode(['profiles' => $rows]);
