<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Modifie un forfait existant — remplace DB.forfaits.update() (js/db.js).
// Réservé au super administrateur.
$me = requireAuth(['admin']);
if ($me['admin_level'] !== 'super') fail('Seul le super administrateur peut gérer les forfaits.', 403);

$in = body();
$id = (string)($in['id'] ?? '');
if ($id === '') fail('Identifiant de forfait requis.');

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

$checkStmt = db()->prepare('SELECT id FROM forfaits WHERE id = ?');
$checkStmt->execute([$id]);
if (!$checkStmt->fetch()) fail('Forfait introuvable.', 404);

db()->prepare('UPDATE forfaits SET operateur=?, categorie=?, nom=?, detail=?, duree=?, prix=?, ussd_template=? WHERE id=?')
    ->execute([$operateur, $categorie, $nom, $detail, $duree, $prix, $ussd, $id]);

$stmt = db()->prepare('SELECT * FROM forfaits WHERE id = ?');
$stmt->execute([$id]);
echo json_encode(['ok' => true, 'forfait' => $stmt->fetch()]);
