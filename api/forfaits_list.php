<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Catalogue des forfaits (Orange/MTN/Moov) — lecture publique (aucun jeton
// requis), même patron que settings_get.php : un visiteur non connecté
// parcourt déjà l'espace client librement (voir index.html), le catalogue
// de forfaits doit rester visible avant toute connexion.
$rows = db()->query('SELECT * FROM forfaits ORDER BY operateur, categorie')->fetchAll();
foreach ($rows as &$r) { $r = decodeJsonColumns($r, ['details']); }
unset($r);
echo json_encode(['forfaits' => $rows]);
