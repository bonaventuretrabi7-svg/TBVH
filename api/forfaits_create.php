<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Ajoute un forfait au catalogue — remplace DB.forfaits.create() (js/db.js).
// Réservé au super administrateur (même restriction que côté client,
// addForfait() dans js/admin.js — revérifiée ici pour qu'un appel direct
// ne puisse pas la contourner).
$me = requireAuth(['admin']);
if ($me['admin_level'] !== 'super') fail('Seul le super administrateur peut gérer les forfaits.', 403);

$in = body();
$operateur = (string)($in['operateur'] ?? '');
$categorie = (string)($in['categorie'] ?? '');
$nom       = trim((string)($in['nom'] ?? ''));
$detail    = trim((string)($in['detail'] ?? ''));
$duree     = trim((string)($in['duree'] ?? ''));
$prix      = (int)($in['prix'] ?? 0);
$ussd      = isset($in['ussdTemplate']) && $in['ussdTemplate'] !== '' ? (string)$in['ussdTemplate'] : null;

if ($operateur === '' || $categorie === '' || $nom === '' || $detail === '' || $duree === '' || $prix <= 0) {
  fail('Veuillez remplir tous les champs obligatoires.');
}

$id = uuid4();
db()->prepare('INSERT INTO forfaits (id, operateur, categorie, nom, detail, duree, prix, ussd_template, verified, details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NULL)')
    ->execute([$id, $operateur, $categorie, $nom, $detail, $duree, $prix, $ussd]);

$stmt = db()->prepare('SELECT * FROM forfaits WHERE id = ?');
$stmt->execute([$id]);
echo json_encode(['ok' => true, 'forfait' => $stmt->fetch()]);
