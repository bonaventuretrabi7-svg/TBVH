<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Dépôt d'une candidature partenaire — remplace Applications.create()
// (js/client.js, prgSubmit()), 100% locale jusqu'ici (jamais vue par
// l'administration à moins d'utiliser le même navigateur). Public (aucune
// authentification) : un futur partenaire n'a pas encore de compte, même
// point d'entrée public que create_account.php. Le PIN choisi est haché
// IMMÉDIATEMENT (jamais stocké en clair) — voir
// partner_applications_validate.php, qui crée le compte cabine directement
// avec ce hash, sans jamais repasser par un PIN en clair.
$in = body();
$prenom     = trim((string)($in['prenom'] ?? ''));
$nom        = trim((string)($in['nom'] ?? ''));
$email      = trim((string)($in['email'] ?? ''));
$telephone  = trim((string)($in['telephone'] ?? ''));
$whatsapp   = trim((string)($in['whatsapp'] ?? ''));
$cabineNom  = trim((string)($in['cabine_nom'] ?? ''));
$pin        = (string)($in['pin'] ?? '');
$photo      = (string)($in['photo'] ?? '');
$pieceRecto = (string)($in['piece_recto'] ?? '');
$pieceVerso = (string)($in['piece_verso'] ?? '');
$codeQr     = (string)($in['code_qr'] ?? '');
$motivation = trim((string)($in['motivation'] ?? ''));
$abonnement = (string)($in['abonnement'] ?? '');
$paiementAbo  = (string)($in['paiement_abo'] ?? '');
$paiementVers = (string)($in['paiement_vers'] ?? '');
$numeroCompte = trim((string)($in['numero_compte'] ?? ''));
$experience   = (string)($in['experience'] ?? '');
$puces        = isset($in['puces']) ? json_encode($in['puces']) : null;
// Parrainage (facultatif, voir _parseParrainCode(), js/client.js) : un
// identifiant inconnu/absent n'empêche jamais le dépôt de la candidature,
// juste aucune récompense versée à la validation (voir
// partner_applications_validate.php) — même principe que create_account.php.
$parrainTelephone = isset($in['parrain_telephone']) && $in['parrain_telephone'] !== '' ? trim((string)$in['parrain_telephone']) : null;

if ($prenom === '' || $nom === '' || $telephone === '') fail('Prénom, nom et téléphone requis.');
if ($cabineNom === '') fail('Le nom de la cabine est requis.');
if (!preg_match('/^\d{4}$/', $pin)) fail('Le code PIN doit contenir exactement 4 chiffres.');
if (!preg_match('/^[^\s@]+@gmail\.com$/i', $email)) fail('Adresse Gmail invalide (ex : nom@gmail.com).');

// Un même numéro/email/nom de cabine ne peut être utilisé que par UNE
// candidature active à la fois (en_attente ou déjà validée) — une
// candidature refusée n'empêche jamais une nouvelle tentative, voir
// partner_applications_check_phone.php/_email.php/_fullname.php/_cabine_nom.php
// (aperçu en direct, js/client.js) pour le même principe. Le nom+prénom est
// vérifié ENSEMBLE, jamais séparément — deux candidats différents peuvent
// tout à fait partager un simple prénom ou nom de famille courant.
$pdo = db();
$dupTelStmt = $pdo->prepare("SELECT id FROM partner_applications WHERE telephone = ? AND statut IN ('en_attente', 'validée')");
$dupTelStmt->execute([$telephone]);
if ($dupTelStmt->fetch()) fail('Une candidature est déjà en cours avec ce numéro de téléphone.');

$dupCabineTelStmt = $pdo->prepare("SELECT id FROM profiles WHERE role = 'cabine' AND telephone = ?");
$dupCabineTelStmt->execute([$telephone]);
if ($dupCabineTelStmt->fetch()) fail('Ce numéro est déjà utilisé par un compte partenaire existant.');

$dupEmailStmt = $pdo->prepare("SELECT id FROM partner_applications WHERE LOWER(email) = LOWER(?) AND statut IN ('en_attente', 'validée')");
$dupEmailStmt->execute([$email]);
if ($dupEmailStmt->fetch()) fail('Une candidature est déjà en cours avec cette adresse Gmail.');

$dupCabineEmailStmt = $pdo->prepare("SELECT id FROM profiles WHERE role = 'cabine' AND LOWER(email) = LOWER(?)");
$dupCabineEmailStmt->execute([$email]);
if ($dupCabineEmailStmt->fetch()) fail('Cette adresse Gmail est déjà utilisée par un compte partenaire existant.');

$dupFullnameStmt = $pdo->prepare("SELECT id FROM partner_applications
    WHERE LOWER(TRIM(prenom)) = LOWER(TRIM(?)) AND LOWER(TRIM(nom)) = LOWER(TRIM(?)) AND statut IN ('en_attente', 'validée')");
$dupFullnameStmt->execute([$prenom, $nom]);
if ($dupFullnameStmt->fetch()) fail('Une candidature est déjà en cours avec ce nom et prénom.');

$dupCabineFullnameStmt = $pdo->prepare("SELECT id FROM profiles
    WHERE role = 'cabine' AND LOWER(TRIM(prenom)) = LOWER(TRIM(?)) AND LOWER(TRIM(nom)) = LOWER(TRIM(?))");
$dupCabineFullnameStmt->execute([$prenom, $nom]);
if ($dupCabineFullnameStmt->fetch()) fail('Ce nom et prénom sont déjà utilisés par un compte partenaire existant.');

$dupCabineNomStmt = $pdo->prepare("SELECT id FROM partner_applications
    WHERE LOWER(TRIM(cabine_nom)) = LOWER(TRIM(?)) AND statut IN ('en_attente', 'validée')");
$dupCabineNomStmt->execute([$cabineNom]);
if ($dupCabineNomStmt->fetch()) fail('Une candidature est déjà en cours avec ce nom de cabine.');

$dupCabineNomProfileStmt = $pdo->prepare("SELECT id FROM profiles WHERE role = 'cabine' AND LOWER(TRIM(cabine_nom)) = LOWER(TRIM(?))");
$dupCabineNomProfileStmt->execute([$cabineNom]);
if ($dupCabineNomProfileStmt->fetch()) fail('Ce nom de cabine est déjà utilisé par un compte partenaire existant.');

$id = uuid4();
$pdo->prepare('INSERT INTO partner_applications
    (id, prenom, nom, email, telephone, whatsapp, cabine_nom, mot_de_passe_hash, photo, piece_recto, piece_verso, code_qr,
     motivation, abonnement, paiement_abo, paiement_vers, numero_compte, experience, puces, parrain_telephone, statut, date_created)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'en_attente\', NOW())')
    ->execute([$id, $prenom, $nom, $email, $telephone, $whatsapp, $cabineNom, password_hash($pin, PASSWORD_BCRYPT),
        $photo ?: null, $pieceRecto ?: null, $pieceVerso ?: null, $codeQr ?: null, $motivation, $abonnement ?: null, $paiementAbo ?: null,
        $paiementVers ?: null, $numeroCompte, $experience ?: null, $puces, $parrainTelephone]);

echo json_encode(['ok' => true]);
