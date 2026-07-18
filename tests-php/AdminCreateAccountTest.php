<?php
declare(strict_types=1);

// api/admin_create_account.php — le formulaire "Créer un compte"
// (handleCreateUser()/finishCreateUser(), js/admin.js) collecte depuis le
// début poste/permissions/whatsapp/photo/pays/ville/quartier/date de
// naissance/pièces pour un compte administrateur, mais l'endpoint ne
// stockait jusqu'ici que nom/prenom/telephone/email/pin/admin_level : tout
// le reste (en particulier permissions) était silencieusement perdu -- un
// "Assistant clientèle" nouvellement créé se retrouvait sans aucun accès
// dès sa première connexion. Voir aussi AdminUpdateProfileTest.php.
final class AdminCreateAccountTest extends ApiTestCase
{
    public function testCreatingAdminPersistsFullProfile(): void
    {
        $super = Fixtures::createProfile('admin', ['admin_level' => 'super']);

        $res = ApiClient::post('/admin_create_account.php', [
            'role' => 'admin', 'nom' => 'Kouassi', 'prenom' => 'Aya',
            'telephone' => '0700001111', 'email' => 'aya.kouassi@gmail.com', 'pin' => '1234',
            'admin_level' => 'simple', 'poste' => 'Assistant clientèle',
            'permissions' => ['dashboard', 'transactions'],
            'whatsapp' => '0700001111', 'photo' => 'data:image/png;base64,xyz',
            'pays' => "Côte d'Ivoire", 'ville' => 'Abidjan', 'quartier' => 'Yopougon',
            'date_naissance' => '1998-06-01',
            'docs' => ['cni_recto' => 'recto.png', 'cni_verso' => 'verso.png', 'photo' => 'photo.png'],
        ], $super['token']);

        $this->assertSame(200, $res->status, $res->raw);
        $profile = $res->json['profile'];
        $this->assertSame('Assistant clientèle', $profile['poste']);
        $this->assertSame(['dashboard', 'transactions'], json_decode((string)$profile['permissions'], true));
        $this->assertSame('0700001111', $profile['whatsapp']);
        $this->assertSame('Abidjan', $profile['ville']);

        $saved = Fixtures::fetchProfile($profile['id']);
        $this->assertSame('Assistant clientèle', $saved['poste']);
        $this->assertSame(['dashboard', 'transactions'], json_decode((string)$saved['permissions'], true));
        $this->assertSame(['cni_recto' => 'recto.png', 'cni_verso' => 'verso.png', 'photo' => 'photo.png'], json_decode((string)$saved['docs'], true));
    }

    public function testCreatingAdminWithoutOptionalProfileFieldsStillWorks(): void
    {
        $super = Fixtures::createProfile('admin', ['admin_level' => 'super']);

        $res = ApiClient::post('/admin_create_account.php', [
            'role' => 'admin', 'nom' => 'Minimal', 'prenom' => 'Compte',
            'telephone' => '0700002222', 'email' => 'minimal@gmail.com', 'pin' => '1234',
            'admin_level' => 'simple',
        ], $super['token']);

        $this->assertSame(200, $res->status, $res->raw);
        $this->assertNull($res->json['profile']['poste']);
        $this->assertNull($res->json['profile']['permissions']);
    }
}
