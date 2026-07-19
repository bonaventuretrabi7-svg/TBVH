<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Enregistre la photo de profil de l'utilisateur connecté (client, mais le
// même endpoint fonctionne pour tout rôle) -- remplace le stockage 100%
// local (localStorage 'cbp_photo_' + id, voir uploadProfilePhoto(),
// js/client.js) : sans ça, la photo ne suivait pas le compte d'un appareil
// à l'autre et restait invisible de l'administration/cabine. Toujours
// l'utilisateur AUTHENTIFIÉ lui-même, jamais un id fourni par l'appelant.
$me = requireAuth();

$in    = body();
$photo = (string)($in['photo'] ?? '');
if ($photo === '') fail('Photo manquante.');

db()->prepare('UPDATE profiles SET photo = ? WHERE id = ?')->execute([$photo, $me['id']]);

echo json_encode(['ok' => true]);
