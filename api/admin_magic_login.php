<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Consomme un lien de connexion sans mot de passe (voir
// api/admin_create_login_link.php) — public (aucun requireAuth : c'est
// justement le but, l'administrateur simple n'est pas encore connecté).
// Vérifie le jeton (hash, non expiré, jamais utilisé), le marque
// consommé, puis émet une vraie session — même bloc que login.php,
// pour que la suite (Bearer token, resumeSession...) fonctionne à
// l'identique d'une connexion classique.
$in = body();
$token = (string)($in['token'] ?? '');
if ($token === '') fail('Jeton manquant.', 401);

$pdo = db();
$tokenHash = hash('sha256', $token);
$stmt = $pdo->prepare('SELECT * FROM admin_login_links WHERE token_hash = ?');
$stmt->execute([$tokenHash]);
$link = $stmt->fetch();

if (!$link) fail('Lien de connexion invalide.', 401);
if ($link['used_at'] !== null) fail('Ce lien de connexion a déjà été utilisé.', 401);
if (strtotime($link['expires_at']) < time()) fail('Ce lien de connexion a expiré.', 401);

$profileStmt = $pdo->prepare("SELECT * FROM profiles WHERE id = ? AND role = 'admin' AND admin_level = 'simple'");
$profileStmt->execute([$link['admin_id']]);
$profile = $profileStmt->fetch();
if (!$profile) fail('Compte introuvable.', 401);
if ($profile['statut'] === 'bloqué') fail('Compte bloqué. Contactez le super administrateur.', 403);

$pdo->prepare('UPDATE admin_login_links SET used_at = NOW() WHERE id = ?')->execute([$link['id']]);

$sessionToken = bin2hex(random_bytes(32));
$pdo->prepare('INSERT INTO sessions (token_hash, profile_id, role, expires_at) VALUES (?, ?, ?, ?)')
    ->execute([hash('sha256', $sessionToken), $profile['id'], $profile['role'], date('Y-m-d H:i:s', time() + 2592000)]);

unset($profile['mot_de_passe_hash']);
echo json_encode(['profile' => $profile, 'token' => $sessionToken]);
