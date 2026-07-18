<?php
declare(strict_types=1);

// Phase 6 : forfaits_create.php/forfaits_update.php/forfaits_remove.php et
// commissions_update_rate.php sont réservés au SUPER administrateur — la
// restriction vérifiée côté client (js/admin.js) est revérifiée ici côté
// serveur, pour qu'un appel direct par un admin normal ne puisse pas la
// contourner. Vérifie aussi que le taux de commission modifié se répercute
// réellement sur le calcul d'une nouvelle commande (calcCommission(),
// api/orders_common.php).
final class ForfaitsCommissionsRoleTest extends ApiTestCase
{
    public function testSuperAdminCanCreateForfait(): void
    {
        $superAdmin = Fixtures::createProfile('admin', ['admin_level' => 'super']);

        $res = ApiClient::post('/forfaits_create.php', [
            'operateur' => 'Orange', 'categorie' => 'Internet', 'nom' => 'Pass 1Go',
            'detail' => '1 Go', 'duree' => '7 jours', 'prix' => 500,
        ], $superAdmin['token']);

        $this->assertTrue($res->ok(), $res->raw);
        $this->assertSame('Pass 1Go', $res->json['forfait']['nom']);
    }

    public function testRegularAdminCannotCreateForfait(): void
    {
        $regularAdmin = Fixtures::createProfile('admin', ['admin_level' => 'standard']);

        $res = ApiClient::post('/forfaits_create.php', [
            'operateur' => 'Orange', 'categorie' => 'Internet', 'nom' => 'Pass 1Go',
            'detail' => '1 Go', 'duree' => '7 jours', 'prix' => 500,
        ], $regularAdmin['token']);

        $this->assertSame(403, $res->status);
        $count = (int)Fixtures::pdo()->query('SELECT COUNT(*) FROM forfaits')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testRegularAdminCannotUpdateCommissionRate(): void
    {
        $regularAdmin = Fixtures::createProfile('admin', ['admin_level' => 'standard']);

        $res = ApiClient::post('/commissions_update_rate.php', ['pourcentage' => 20], $regularAdmin['token']);
        $this->assertSame(403, $res->status);
    }

    public function testCommissionRateChangeAffectsNewOrders(): void
    {
        $superAdmin = Fixtures::createProfile('admin', ['admin_level' => 'super']);
        $rateRes = ApiClient::post('/commissions_update_rate.php', ['pourcentage' => 8], $superAdmin['token']);
        $this->assertTrue($rateRes->ok(), $rateRes->raw);

        $client = Fixtures::createProfile('client', ['solde' => 10000]);
        $cabine = Fixtures::createProfile('cabine');
        Fixtures::ping($cabine['profile']['id']);

        $order = ApiClient::post('/orders_create.php', [
            'operateur' => 'Orange', 'numero_beneficiaire' => '0700000000', 'montant' => 1000,
        ], $client['token']);

        $this->assertTrue($order->ok(), $order->raw);
        $this->assertSame(80, (int)$order->json['transaction']['commission'], 'round(1000 * 8%) = 80');
    }

    public function testCommissionRateRejectsOutOfRangeValue(): void
    {
        $superAdmin = Fixtures::createProfile('admin', ['admin_level' => 'super']);

        $res = ApiClient::post('/commissions_update_rate.php', ['pourcentage' => 75], $superAdmin['token']);
        $this->assertFalse($res->ok());
    }
}
