<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Modifie le profil (coordonnées, poste, permissions, PIN) d'un compte
// administrateur -- remplace saveAdminProfile()/saveAdminPerms() (js/admin.js),
// qui ne mettaient jusqu'ici à jour que le cache LOCAL de l'admin qui
// cliquait (DB.users.update()) : aucune de ces informations n'atteignait
// jamais le serveur. Un "Assistant clientèle" configuré par le super admin
// se retrouvait donc sans permissions ni poste dès sa première connexion
// sur son propre appareil -- corrige le bug rapporté ("l'administrateur
// n'arrive pas à ajouter d'assistant client").
// Réservé au super administrateur, comme côté client (saveAdminProfile()/
// saveAdminPerms() bloquent déjà tout admin simple avant même d'appeler
// cet endpoint -- défense en profondeur ici aussi).
$me = requireAuth(['admin']);
if ($me['admin_level'] !== 'super') fail('Seul le super administrateur peut modifier un compte administrateur.', 403);

$in = body();
$targetId = (string)($in['id'] ?? '');
if ($targetId === '') fail('Identifiant de compte requis.');

$pdo = db();
$stmt = $pdo->prepare('SELECT id, role, admin_level FROM profiles WHERE id = ?');
$stmt->execute([$targetId]);
$target = $stmt->fetch();
if (!$target || $target['role'] !== 'admin') fail('Compte administrateur introuvable.', 404);

if (array_key_exists('email', $in) && $in['email'] !== '') {
  $emailStmt = $pdo->prepare('SELECT id FROM profiles WHERE LOWER(email) = LOWER(?) AND role = ? AND id != ?');
  $emailStmt->execute([(string)$in['email'], 'admin', $targetId]);
  if ($emailStmt->fetch()) fail('Cet email est déjà utilisé par un autre compte.');
}

$columns = [];
$params = [];

foreach (['nom' => 's', 'prenom' => 's', 'email' => 's', 'date_naissance' => 's',
          'pays' => 's', 'ville' => 's', 'quartier' => 's', 'whatsapp' => 's',
          'photo' => 's'] as $key => $type) {
  if (array_key_exists($key, $in)) { $columns[] = "$key = ?"; $params[] = (string)$in[$key]; }
}
if (array_key_exists('docs', $in)) { $columns[] = 'docs = ?'; $params[] = json_encode($in['docs']); }

// poste/permissions : sans effet sur le super administrateur lui-même (ces
// deux champs ne concernent que les admins simples -- voir edit-admin-poste-
// group, masqué côté UI quand la cible est le super admin).
if ($target['admin_level'] !== 'super') {
  if (array_key_exists('poste', $in)) { $columns[] = 'poste = ?'; $params[] = (string)$in['poste']; }
  if (array_key_exists('permissions', $in)) { $columns[] = 'permissions = ?'; $params[] = json_encode($in['permissions']); }
}

if (array_key_exists('pin', $in) && $in['pin'] !== '') {
  if (!preg_match('/^\d{4}$/', (string)$in['pin'])) fail('Le nouveau PIN doit contenir exactement 4 chiffres.');
  $columns[] = 'mot_de_passe_hash = ?';
  $params[] = password_hash((string)$in['pin'], PASSWORD_BCRYPT);
}

if (!$columns) fail('Aucune modification fournie.');

$params[] = $targetId;
$pdo->prepare('UPDATE profiles SET ' . implode(', ', $columns) . ' WHERE id = ?')->execute($params);

$stmt = $pdo->prepare('SELECT * FROM profiles WHERE id = ?');
$stmt->execute([$targetId]);
$profile = $stmt->fetch();
unset($profile['mot_de_passe_hash']);
echo json_encode(['profile' => $profile]);
