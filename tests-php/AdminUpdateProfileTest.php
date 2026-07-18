<?php
declare(strict_types=1);

// api/admin_update_profile.php — remplace saveAdminProfile()/saveAdminPerms()
// (js/admin.js), qui ne mettaient jusqu'ici à jour que le cache LOCAL de
// l'appareil du super admin (DB.users.update()) : un "Assistant clientèle"
// configuré (poste, permissions) ne recevait jamais ces informations côté
// serveur, et se retrouvait donc sans rien dès sa première connexion sur son
// propre appareil. Voir aussi AdminCreateAccountTest.php (mêmes champs, à la
// création plutôt qu'à la modification).
final class AdminUpdateProfileTest extends ApiTestCase
{
    public function testSuperAdminCanUpdateSimpleAdminProfileAndPermissions(): void
    {
        $super = Fixtures::createProfile('admin', ['admin_level' => 'super']);
        $simple = Fixtures::createProfile('admin', ['admin_level' => 'simple', 'poste' => null, 'permissions' => null]);

        $res = ApiClient::post('/admin_update_profile.php', [
            'id' => $simple['profile']['id'],
            'nom' => 'Kone', 'prenom' => 'Awa', 'email' => 'awa.kone@gmail.com',
            'date_naissance' => '1995-04-12', 'pays' => "Côte d'Ivoire", 'ville' => 'Abidjan', 'quartier' => 'Cocody',
            'whatsapp' => '0700000099', 'poste' => 'Assistant clientèle',
            'permissions' => ['dashboard', 'transactions'],
        ], $super['token']);

        $this->assertSame(200, $res->status, $res->raw);
        $profile = $res->json['profile'];
        $this->assertSame('Kone', $profile['nom']);
        $this->assertSame('Assistant clientèle', $profile['poste']);
        $this->assertSame(['dashboard', 'transactions'], json_decode((string)$profile['permissions'], true));

        $reloaded = Fixtures::fetchProfile($simple['profile']['id']);
        $this->assertSame('Assistant clientèle', $reloaded['poste']);
        $this->assertSame(['dashboard', 'transactions'], json_decode((string)$reloaded['permissions'], true));
    }

    public function testPosteAndPermissionsIgnoredWhenTargetIsSuperAdmin(): void
    {
        $super = Fixtures::createProfile('admin', ['admin_level' => 'super']);

        $res = ApiClient::post('/admin_update_profile.php', [
            'id' => $super['profile']['id'],
            'nom' => $super['profile']['nom'],
            'poste' => 'Assistant clientèle',
            'permissions' => ['dashboard'],
        ], $super['token']);

        $this->assertSame(200, $res->status, $res->raw);
        $reloaded = Fixtures::fetchProfile($super['profile']['id']);
        $this->assertNull($reloaded['poste']);
        $this->assertNull($reloaded['permissions']);
    }

    public function testRegularAdminCannotUpdateAnyProfile(): void
    {
        $regularAdmin = Fixtures::createProfile('admin', ['admin_level' => 'simple']);
        $other = Fixtures::createProfile('admin', ['admin_level' => 'simple']);

        $res = ApiClient::post('/admin_update_profile.php', [
            'id' => $other['profile']['id'], 'nom' => 'Intrus',
        ], $regularAdmin['token']);

        $this->assertSame(403, $res->status);
        $this->assertSame($other['profile']['nom'], Fixtures::fetchProfile($other['profile']['id'])['nom']);
    }

    public function testUpdatingUnknownAccountFails(): void
    {
        $super = Fixtures::createProfile('admin', ['admin_level' => 'super']);
        $res = ApiClient::post('/admin_update_profile.php', ['id' => 'id-inexistant', 'nom' => 'X'], $super['token']);
        $this->assertSame(404, $res->status);
    }

    public function testCannotUpdateANonAdminAccount(): void
    {
        $super = Fixtures::createProfile('admin', ['admin_level' => 'super']);
        $client = Fixtures::createProfile('client');
        $res = ApiClient::post('/admin_update_profile.php', ['id' => $client['profile']['id'], 'nom' => 'X'], $super['token']);
        $this->assertSame(404, $res->status);
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $super = Fixtures::createProfile('admin', ['admin_level' => 'super']);
        $existing = Fixtures::createProfile('admin', ['admin_level' => 'simple', 'email' => 'deja.pris@gmail.com']);
        $target = Fixtures::createProfile('admin', ['admin_level' => 'simple']);

        $res = ApiClient::post('/admin_update_profile.php', [
            'id' => $target['profile']['id'], 'email' => 'deja.pris@gmail.com',
        ], $super['token']);

        $this->assertFalse($res->ok());
        $this->assertNotSame('deja.pris@gmail.com', Fixtures::fetchProfile($target['profile']['id'])['email']);
    }

    public function testPinResetAllowsLoginWithNewPin(): void
    {
        $super = Fixtures::createProfile('admin', ['admin_level' => 'super']);
        $target = Fixtures::createProfile('admin', ['admin_level' => 'simple']);

        $res = ApiClient::post('/admin_update_profile.php', [
            'id' => $target['profile']['id'], 'pin' => '9876',
        ], $super['token']);
        $this->assertSame(200, $res->status, $res->raw);

        $login = ApiClient::post('/login.php', [
            'identifiant' => $target['profile']['email'], 'pin' => '9876', 'role' => 'admin',
        ]);
        $this->assertSame(200, $login->status, $login->raw);
        $this->assertArrayHasKey('token', $login->json);
    }
}
