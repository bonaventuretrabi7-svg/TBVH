<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Recherche d'un compte client actif par numéro de téléphone — utilisé par
// le transfert client-à-client (voir lookupTransferRecipient()/
// handleClientTransfer(), js/client.js) pour vérifier l'existence réelle du
// destinataire AVANT de transférer. DB.users.byPhone() (recherche dans le
// cache local) ne suffit pas : contrairement à l'admin (voir
// list_profiles.php), un client n'a jamais dans son cache local les profils
// des AUTRES clients, seulement le sien — la recherche doit donc interroger
// le serveur.
$me = requireAuth(['client']);

$in = body();
$phone = trim((string)($in['phone'] ?? ''));
if (!preg_match('/^[0-9]{10}$/', $phone)) fail('Numéro invalide.');

$stmt = db()->prepare("SELECT id, prenom, nom, telephone FROM profiles WHERE role = 'client' AND statut = 'actif' AND telephone = ?");
$stmt->execute([$phone]);
$row = $stmt->fetch();

echo json_encode(['ok' => true, 'found' => (bool)$row, 'recipient' => $row ?: null]);
