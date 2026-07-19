<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Super admin : génère un lien de connexion sans mot de passe pour un
// administrateur simple (voir Auth.magicLogin()/js/auth.js et
// api/admin_magic_login.php, qui le consomme). Jeton opaque à usage
// unique, courte durée de vie — même format que le jeton de session émis
// par login.php (32 octets aléatoires, seul le hash SHA-256 est stocké).
$me = requireAuth(['admin']);
if ($me['admin_level'] !== 'super') fail('Seul le super administrateur peut générer un lien de connexion.', 403);

$in = body();
$adminId = (string)($in['admin_id'] ?? '');
if ($adminId === '') fail('Compte administrateur requis.');

$stmt = db()->prepare("SELECT id FROM profiles WHERE id = ? AND role = 'admin' AND admin_level = 'simple'");
$stmt->execute([$adminId]);
if (!$stmt->fetch()) fail('Ce compte n\'est pas un administrateur simple.');

$token = bin2hex(random_bytes(32));
$linkId = uuid4();
// Valable 15 minutes, à usage unique (voir admin_magic_login.php, qui
// marque used_at dès la première consultation).
db()->prepare('INSERT INTO admin_login_links (id, admin_id, token_hash, expires_at, created_by) VALUES (?, ?, ?, ?, ?)')
    ->execute([$linkId, $adminId, hash('sha256', $token), date('Y-m-d H:i:s', time() + 900), $me['id']]);

echo json_encode(['ok' => true, 'token' => $token]);
