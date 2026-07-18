<?php
declare(strict_types=1);

// Régression : api/admin_set_abonnement.php (et cabine_suspend_manual.php,
// même patron) utilisaient rowCount() de l'UPDATE pour détecter "cabine
// introuvable" — mais rowCount() reflète les lignes CHANGÉES, pas celles
// matchées par le WHERE. Un appel qui repose la MÊME formule (aucune colonne
// ne change réellement) renvoyait alors à tort "Cabine introuvable.",
// alors que la cabine existe bel et bien. Corrigé via un SELECT d'existence
// séparé avant l'UPDATE.
final class AdminSetAbonnementRegressionTest extends ApiTestCase
{
    public function testSettingTheSameFormuleTwiceStillSucceeds(): void
    {
        $admin = Fixtures::createProfile('admin');
        $cabine = Fixtures::createProfile('cabine', ['abonnement' => 'Premium', 'commissions_total' => 0]);

        $first = ApiClient::post('/admin_set_abonnement.php', [
            'cabine_id' => $cabine['profile']['id'],
            'formule' => 'Premium',
        ], $admin['token']);
        $this->assertTrue($first->ok(), $first->raw);

        // Deuxième appel : UPDATE ... SET abonnement='Premium', commissions_total=0
        // ne change AUCUNE colonne (déjà ces valeurs) -> rowCount() = 0, mais la
        // cabine existe bien -> doit quand même répondre ok:true.
        $second = ApiClient::post('/admin_set_abonnement.php', [
            'cabine_id' => $cabine['profile']['id'],
            'formule' => 'Premium',
        ], $admin['token']);
        $this->assertTrue($second->ok(), $second->raw);
    }

    public function testUnknownCabineIdFails(): void
    {
        $admin = Fixtures::createProfile('admin');

        $res = ApiClient::post('/admin_set_abonnement.php', [
            'cabine_id' => 'id-inexistant',
            'formule' => 'Premium',
        ], $admin['token']);
        $this->assertFalse($res->ok());
    }

    public function testChangingFormuleResetsCommissionsTotal(): void
    {
        $admin = Fixtures::createProfile('admin');
        $cabine = Fixtures::createProfile('cabine', ['abonnement' => 'Premium', 'commissions_total' => 12000]);

        $res = ApiClient::post('/admin_set_abonnement.php', [
            'cabine_id' => $cabine['profile']['id'],
            'formule' => 'VIP',
        ], $admin['token']);
        $this->assertTrue($res->ok());

        $updated = Fixtures::fetchProfile($cabine['profile']['id']);
        $this->assertSame('VIP', $updated['abonnement']);
        $this->assertSame(0, (int)$updated['commissions_total']);
    }
}
