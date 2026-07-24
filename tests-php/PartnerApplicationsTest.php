<?php
declare(strict_types=1);

// api/partner_applications_{create,list,validate,refuse}.php (Phase F) —
// remplace le flux 100% local (Applications, js/client.js + lecture
// localStorage directe, js/admin.js). Le PIN choisi est haché
// IMMÉDIATEMENT à la création, jamais stocké/transmis en clair ; la
// validation crée le compte cabine directement avec ce hash.
final class PartnerApplicationsTest extends ApiTestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'prenom' => 'Jean', 'nom' => 'Kouassi', 'email' => 'jean.kouassi@gmail.com',
            'telephone' => '0700000001', 'whatsapp' => '0700000001', 'cabine_nom' => 'Cabine Jean',
            'pin' => '1234', 'photo' => 'data:image/png;base64,abc', 'code_qr' => 'data:image/png;base64,def',
            'motivation' => 'Motivé', 'abonnement' => 'Premium', 'paiement_abo' => 'Orange Money',
            'paiement_vers' => 'Orange Money', 'numero_compte' => '0700000001', 'experience' => 'Débutant',
            'puces' => ['orange' => 1, 'mtn' => 0, 'moov' => 0],
        ], $overrides);
    }

    public function testCreateThenAdminCanValidateAndAccountWorks(): void
    {
        $admin = Fixtures::createProfile('admin');

        $create = ApiClient::post('/partner_applications_create.php', $this->validPayload());
        $this->assertTrue($create->ok(), $create->raw);

        $list = ApiClient::get('/partner_applications_list.php', $admin['token']);
        $this->assertCount(1, $list->json['applications']);
        $this->assertArrayNotHasKey('mot_de_passe_hash', $list->json['applications'][0]);
        $appId = $list->json['applications'][0]['id'];

        $validate = ApiClient::post('/partner_applications_validate.php', ['application_id' => $appId], $admin['token']);
        $this->assertTrue($validate->ok(), $validate->raw);
        $this->assertNotEmpty($validate->json['cabineId']);

        // Le compte cree doit vraiment fonctionner (login reel avec le PIN choisi).
        $login = ApiClient::post('/login.php', ['identifiant' => 'jean.kouassi@gmail.com', 'pin' => '1234', 'role' => 'cabine'], null);
        $this->assertSame(200, $login->status);
        $this->assertSame('Cabine Jean', $login->json['profile']['cabine_nom']);
        $this->assertSame('0700000001', $login->json['profile']['whatsapp']);
    }

    public function testRejectsNonGmailEmail(): void
    {
        $res = ApiClient::post('/partner_applications_create.php', $this->validPayload(['email' => 'jean@yahoo.com']));
        $this->assertFalse($res->ok());
    }

    public function testRejectsInvalidPin(): void
    {
        $res = ApiClient::post('/partner_applications_create.php', $this->validPayload(['pin' => 'abcd']));
        $this->assertFalse($res->ok());
    }

    public function testCannotValidateTwice(): void
    {
        $admin = Fixtures::createProfile('admin');
        ApiClient::post('/partner_applications_create.php', $this->validPayload());
        $appId = Fixtures::pdo()->query('SELECT id FROM partner_applications LIMIT 1')->fetchColumn();

        $first = ApiClient::post('/partner_applications_validate.php', ['application_id' => $appId], $admin['token']);
        $this->assertTrue($first->ok());
        $second = ApiClient::post('/partner_applications_validate.php', ['application_id' => $appId], $admin['token']);
        $this->assertFalse($second->ok());

        $cabineCount = (int)Fixtures::pdo()->query("SELECT COUNT(*) FROM profiles WHERE role = 'cabine'")->fetchColumn();
        $this->assertSame(1, $cabineCount, 'un seul compte doit avoir ete cree malgre la 2e tentative');
    }

    public function testCannotValidateIfPhoneAlreadyUsedByAnotherCabine(): void
    {
        $admin = Fixtures::createProfile('admin');
        // Le numéro est encore libre au dépôt de la candidature (sinon
        // partner_applications_create.php la refuserait lui-même, voir
        // testRejectsNewApplicationIfPhoneAlreadyUsedByExistingCabineAccount)
        // — un autre compte cabine le prend ENTRE-TEMPS (ex. créé
        // directement par l'admin, hors de ce flux), et c'est ce que doit
        // rattraper la validation.
        ApiClient::post('/partner_applications_create.php', $this->validPayload());
        Fixtures::createProfile('cabine', ['telephone' => '0700000001']);
        $appId = Fixtures::pdo()->query('SELECT id FROM partner_applications LIMIT 1')->fetchColumn();

        $res = ApiClient::post('/partner_applications_validate.php', ['application_id' => $appId], $admin['token']);
        $this->assertFalse($res->ok());
    }

    public function testRefuseMarksApplicationWithoutCreatingAccount(): void
    {
        $admin = Fixtures::createProfile('admin');
        ApiClient::post('/partner_applications_create.php', $this->validPayload());
        $appId = Fixtures::pdo()->query('SELECT id FROM partner_applications LIMIT 1')->fetchColumn();

        $res = ApiClient::post('/partner_applications_refuse.php', ['application_id' => $appId], $admin['token']);
        $this->assertTrue($res->ok());

        $statut = Fixtures::pdo()->query("SELECT statut FROM partner_applications WHERE id = '$appId'")->fetchColumn();
        $this->assertSame('refusée', $statut);
        $cabineCount = (int)Fixtures::pdo()->query("SELECT COUNT(*) FROM profiles WHERE role = 'cabine'")->fetchColumn();
        $this->assertSame(0, $cabineCount);
    }

    public function testNonAdminCannotListOrValidate(): void
    {
        $client = Fixtures::createProfile('client');
        $this->assertSame(401, ApiClient::get('/partner_applications_list.php', null)->status);
        $this->assertSame(403, ApiClient::get('/partner_applications_list.php', $client['token'])->status);
    }

    // Phase 31 -- parrainage sur les candidatures partenaire (voir
    // migration_phase31_partner_referral.sql, partner_applications_validate.php).
    public function testValidatingApplicationCreditsParrainClient(): void
    {
        $admin = Fixtures::createProfile('admin');
        $parrain = Fixtures::createProfile('client', ['telephone' => '0700000099']);

        ApiClient::post('/partner_applications_create.php', $this->validPayload(['parrain_telephone' => '0700000099']));
        $appId = Fixtures::pdo()->query('SELECT id FROM partner_applications LIMIT 1')->fetchColumn();

        $validate = ApiClient::post('/partner_applications_validate.php', ['application_id' => $appId], $admin['token']);
        $this->assertTrue($validate->ok(), $validate->raw);

        $parrainAfter = Fixtures::fetchProfile($parrain['profile']['id']);
        $this->assertSame(1000, (int)$parrainAfter['solde'], 'le parrain doit avoir recu exactement 1000 F');
    }

    public function testValidatingApplicationWithOwnPhoneAsParrainGrantsNoReward(): void
    {
        $admin = Fixtures::createProfile('admin');
        // Le candidat a aussi un compte client avec le meme numero que sa
        // candidature, et se designe lui-meme comme parrain.
        $selfClient = Fixtures::createProfile('client', ['telephone' => '0700000001']);

        ApiClient::post('/partner_applications_create.php', $this->validPayload(['parrain_telephone' => '0700000001']));
        $appId = Fixtures::pdo()->query('SELECT id FROM partner_applications LIMIT 1')->fetchColumn();

        $validate = ApiClient::post('/partner_applications_validate.php', ['application_id' => $appId], $admin['token']);
        $this->assertTrue($validate->ok(), $validate->raw);

        $selfAfter = Fixtures::fetchProfile($selfClient['profile']['id']);
        $this->assertSame(0, (int)$selfAfter['solde'], 'un candidat ne doit jamais pouvoir se parrainer lui-meme');
    }

    public function testValidatingApplicationWithUnknownParrainStillWorks(): void
    {
        $admin = Fixtures::createProfile('admin');

        ApiClient::post('/partner_applications_create.php', $this->validPayload(['parrain_telephone' => '0799999999']));
        $appId = Fixtures::pdo()->query('SELECT id FROM partner_applications LIMIT 1')->fetchColumn();

        $validate = ApiClient::post('/partner_applications_validate.php', ['application_id' => $appId], $admin['token']);
        $this->assertTrue($validate->ok(), $validate->raw);
        $this->assertNotEmpty($validate->json['cabineId']);
    }

    // Un même numéro/email ne peut être "numéro principal"/adresse que d'UNE
    // candidature active (en_attente ou validée) à la fois — voir
    // partner_applications_check_phone.php/_email.php pour le même principe
    // côté aperçu en direct. Un refus n'est jamais définitif : le candidat
    // doit pouvoir retenter sa chance avec le même numéro/email.
    public function testRejectsNewApplicationIfPhoneAlreadyPending(): void
    {
        ApiClient::post('/partner_applications_create.php', $this->validPayload(['telephone' => '0711112222']));

        $res = ApiClient::post('/partner_applications_create.php', $this->validPayload([
            'telephone' => '0711112222', 'email' => 'autre@gmail.com',
        ]));

        $this->assertFalse($res->ok());
        $count = (int)Fixtures::pdo()->query("SELECT COUNT(*) FROM partner_applications WHERE telephone = '0711112222'")->fetchColumn();
        $this->assertSame(1, $count, 'la 2e tentative ne doit jamais être enregistrée');
    }

    public function testAllowsNewApplicationWithSamePhoneAfterPreviousRefusal(): void
    {
        $admin = Fixtures::createProfile('admin');
        ApiClient::post('/partner_applications_create.php', $this->validPayload(['telephone' => '0711112222']));
        $firstId = Fixtures::pdo()->query('SELECT id FROM partner_applications LIMIT 1')->fetchColumn();
        ApiClient::post('/partner_applications_refuse.php', ['application_id' => $firstId], $admin['token']);

        $res = ApiClient::post('/partner_applications_create.php', $this->validPayload([
            'telephone' => '0711112222', 'email' => 'autre@gmail.com',
        ]));

        $this->assertTrue($res->ok(), $res->raw);
    }

    public function testRejectsNewApplicationIfPhoneAlreadyUsedByExistingCabineAccount(): void
    {
        Fixtures::createProfile('cabine', ['telephone' => '0711112222']);

        $res = ApiClient::post('/partner_applications_create.php', $this->validPayload(['telephone' => '0711112222']));

        $this->assertFalse($res->ok());
    }

    public function testRejectsNewApplicationIfEmailAlreadyPending(): void
    {
        ApiClient::post('/partner_applications_create.php', $this->validPayload(['email' => 'doublon@gmail.com']));

        $res = ApiClient::post('/partner_applications_create.php', $this->validPayload([
            'telephone' => '0722223333', 'email' => 'doublon@gmail.com',
        ]));

        $this->assertFalse($res->ok());
        $count = (int)Fixtures::pdo()->query("SELECT COUNT(*) FROM partner_applications WHERE LOWER(email) = 'doublon@gmail.com'")->fetchColumn();
        $this->assertSame(1, $count, 'la 2e tentative ne doit jamais être enregistrée');
    }

    public function testAllowsNewApplicationWithSameEmailAfterPreviousRefusal(): void
    {
        $admin = Fixtures::createProfile('admin');
        ApiClient::post('/partner_applications_create.php', $this->validPayload(['email' => 'doublon@gmail.com']));
        $firstId = Fixtures::pdo()->query('SELECT id FROM partner_applications LIMIT 1')->fetchColumn();
        ApiClient::post('/partner_applications_refuse.php', ['application_id' => $firstId], $admin['token']);

        $res = ApiClient::post('/partner_applications_create.php', $this->validPayload([
            'telephone' => '0722223333', 'email' => 'doublon@gmail.com',
        ]));

        $this->assertTrue($res->ok(), $res->raw);
    }

    // Nom+prénom vérifiés ENSEMBLE : deux candidats différents partageant
    // juste un prénom ou un nom de famille courant ne doivent jamais être
    // bloqués l'un par l'autre (voir partner_applications_check_fullname.php).
    public function testRejectsNewApplicationIfSameFullNameAlreadyPending(): void
    {
        ApiClient::post('/partner_applications_create.php', $this->validPayload([
            'prenom' => 'Aya', 'nom' => 'Koffi', 'telephone' => '0733334444', 'email' => 'aya1@gmail.com',
        ]));

        $res = ApiClient::post('/partner_applications_create.php', $this->validPayload([
            'prenom' => ' aya ', 'nom' => ' KOFFI ', 'telephone' => '0733335555', 'email' => 'aya2@gmail.com',
        ]));

        $this->assertFalse($res->ok());
    }

    public function testAllowsDifferentCandidatesSharingOnlyFirstOrLastName(): void
    {
        ApiClient::post('/partner_applications_create.php', $this->validPayload([
            'prenom' => 'Aya', 'nom' => 'Koffi', 'telephone' => '0733334444', 'email' => 'aya1@gmail.com',
        ]));

        // Même prénom, nom différent -> pas un doublon.
        $res1 = ApiClient::post('/partner_applications_create.php', $this->validPayload([
            'prenom' => 'Aya', 'nom' => 'Traore', 'telephone' => '0733335555', 'email' => 'aya2@gmail.com',
        ]));
        $this->assertTrue($res1->ok(), $res1->raw);

        // Même nom, prénom différent -> pas un doublon.
        $res2 = ApiClient::post('/partner_applications_create.php', $this->validPayload([
            'prenom' => 'Marie', 'nom' => 'Koffi', 'telephone' => '0733336666', 'email' => 'aya3@gmail.com',
        ]));
        $this->assertTrue($res2->ok(), $res2->raw);
    }

    public function testAllowsSameFullNameAfterPreviousRefusal(): void
    {
        $admin = Fixtures::createProfile('admin');
        ApiClient::post('/partner_applications_create.php', $this->validPayload([
            'prenom' => 'Aya', 'nom' => 'Koffi', 'telephone' => '0733334444', 'email' => 'aya1@gmail.com',
        ]));
        $firstId = Fixtures::pdo()->query('SELECT id FROM partner_applications LIMIT 1')->fetchColumn();
        ApiClient::post('/partner_applications_refuse.php', ['application_id' => $firstId], $admin['token']);

        $res = ApiClient::post('/partner_applications_create.php', $this->validPayload([
            'prenom' => 'Aya', 'nom' => 'Koffi', 'telephone' => '0733335555', 'email' => 'aya2@gmail.com',
        ]));

        $this->assertTrue($res->ok(), $res->raw);
    }

    public function testRejectsNewApplicationIfCabineNomAlreadyPending(): void
    {
        ApiClient::post('/partner_applications_create.php', $this->validPayload([
            'cabine_nom' => 'Kbine Plus Cocody', 'telephone' => '0744445555', 'email' => 'cab1@gmail.com',
        ]));

        $res = ApiClient::post('/partner_applications_create.php', $this->validPayload([
            'cabine_nom' => '  kbine plus cocody  ', 'telephone' => '0744446666', 'email' => 'cab2@gmail.com',
        ]));

        $this->assertFalse($res->ok());
    }

    public function testAllowsCabineNomReuseAfterPreviousRefusal(): void
    {
        $admin = Fixtures::createProfile('admin');
        ApiClient::post('/partner_applications_create.php', $this->validPayload([
            'cabine_nom' => 'Kbine Plus Cocody', 'telephone' => '0744445555', 'email' => 'cab1@gmail.com',
        ]));
        $firstId = Fixtures::pdo()->query('SELECT id FROM partner_applications LIMIT 1')->fetchColumn();
        ApiClient::post('/partner_applications_refuse.php', ['application_id' => $firstId], $admin['token']);

        $res = ApiClient::post('/partner_applications_create.php', $this->validPayload([
            'cabine_nom' => 'Kbine Plus Cocody', 'telephone' => '0744446666', 'email' => 'cab2@gmail.com',
        ]));

        $this->assertTrue($res->ok(), $res->raw);
    }
}
