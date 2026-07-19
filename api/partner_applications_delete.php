<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Supprime définitivement une candidature partenaire (quel que soit son
// statut) — remplace deletePartnerRequest() (js/admin.js). Contrairement à
// refuser (qui garde une trace "refusée"), ceci retire complètement la
// ligne, pour un candidat en double, un test, ou un doublon à nettoyer.
requireAuth(['admin']);

$in = body();
$id = (string)($in['application_id'] ?? '');
if ($id === '') fail('Identifiant de candidature requis.');

$stmt = db()->prepare('DELETE FROM partner_applications WHERE id = ?');
$stmt->execute([$id]);
if ($stmt->rowCount() === 0) fail('Candidature introuvable.', 404);

echo json_encode(['ok' => true]);
