<?php
declare(strict_types=1);

// Régression : api/orders_suspend.php a longtemps eu un bug d'ordre
// d'évaluation MySQL (`statut = 'suspendue', statut_avant_suspension =
// statut` capturait la NOUVELLE valeur, pas l'originale, car MySQL évalue
// un SET multi-colonnes de gauche à droite) — corrigé en inversant l'ordre.
// Ce test vérifie le comportement observable de bout en bout (suspendre
// PUIS réactiver doit restaurer le statut d'origine), qui aurait échoué
// silencieusement avec le bug (la commande serait restée bloquée à
// "suspendue" pour toujours, réactivation impossible).
final class SuspensionRegressionTest extends ApiTestCase
{
    public function testSuspendThenReactivateRestoresOriginalPendingStatus(): void
    {
        $client = Fixtures::createProfile('client');
        $cabine = Fixtures::createProfile('cabine');
        $admin = Fixtures::createProfile('admin');
        $txn = Fixtures::createTransaction([
            'client_id' => $client['profile']['id'],
            'cabine_id' => $cabine['profile']['id'],
            'statut' => 'en_attente',
        ]);

        $suspend = ApiClient::post('/orders_suspend.php', ['transaction_id' => $txn['id'], 'motif' => 'vérification'], $admin['token']);
        $this->assertTrue($suspend->ok(), $suspend->raw);

        $afterSuspend = Fixtures::fetchTransaction($txn['id']);
        $this->assertSame('suspendue', $afterSuspend['statut']);
        $this->assertSame('en_attente', $afterSuspend['statut_avant_suspension'], 'doit garder le statut ORIGINAL, pas "suspendue"');

        $reactivate = ApiClient::post('/orders_reactivate.php', ['transaction_id' => $txn['id']], $admin['token']);
        $this->assertTrue($reactivate->ok(), $reactivate->raw);

        $afterReactivate = Fixtures::fetchTransaction($txn['id']);
        $this->assertSame('en_attente', $afterReactivate['statut'], 'doit être restauré à en_attente, pas resté bloqué à suspendue');
    }

    public function testSuspendThenReactivateRestoresOriginalCompletedStatus(): void
    {
        $client = Fixtures::createProfile('client');
        $cabine = Fixtures::createProfile('cabine');
        $admin = Fixtures::createProfile('admin');
        $txn = Fixtures::createTransaction([
            'client_id' => $client['profile']['id'],
            'cabine_id' => $cabine['profile']['id'],
            'statut' => 'terminé',
        ]);

        ApiClient::post('/orders_suspend.php', ['transaction_id' => $txn['id'], 'motif' => 'vérification'], $admin['token']);
        $afterSuspend = Fixtures::fetchTransaction($txn['id']);
        $this->assertSame('terminé', $afterSuspend['statut_avant_suspension']);

        ApiClient::post('/orders_reactivate.php', ['transaction_id' => $txn['id']], $admin['token']);
        $afterReactivate = Fixtures::fetchTransaction($txn['id']);
        $this->assertSame('terminé', $afterReactivate['statut']);
    }

    public function testSuspendRequiresNonEmptyMotif(): void
    {
        $txn = Fixtures::createTransaction();
        $admin = Fixtures::createProfile('admin');

        $res = ApiClient::post('/orders_suspend.php', ['transaction_id' => $txn['id'], 'motif' => ''], $admin['token']);
        $this->assertSame(400, $res->status);

        $unchanged = Fixtures::fetchTransaction($txn['id']);
        $this->assertSame('en_attente', $unchanged['statut']);
    }
}
