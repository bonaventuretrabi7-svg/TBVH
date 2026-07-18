<?php
declare(strict_types=1);

// api/transferts_cabine_list.php / api/resubscriptions_list.php (Phase B) —
// les deux tables sont déjà peuplées correctement par
// api/cabine_transfer.php/api/cabine_resubscribe.php (testé ailleurs) ;
// ici on vérifie uniquement la LECTURE nouvellement ajoutée, portée par
// rôle comme retards_list.php : une cabine ne voit que ce qui la concerne,
// un admin voit tout.
final class TransfertsResubscriptionsListTest extends ApiTestCase
{
    public function testCabineSeesTransfertsSentAndReceivedOnly(): void
    {
        $cabineA = Fixtures::createProfile('cabine');
        $cabineB = Fixtures::createProfile('cabine');
        $cabineC = Fixtures::createProfile('cabine');

        $pdo = Fixtures::pdo();
        $insert = fn($from, $to) => $pdo->prepare('INSERT INTO transferts_cabine (id, from_cabine_id, to_cabine_id, montant, frais, date) VALUES (UUID(), ?, ?, 1000, 150, NOW())')->execute([$from, $to]);
        $insert($cabineA['profile']['id'], $cabineB['profile']['id']); // A -> B (A concernée)
        $insert($cabineB['profile']['id'], $cabineA['profile']['id']); // B -> A (A concernée)
        $insert($cabineB['profile']['id'], $cabineC['profile']['id']); // B -> C (A non concernée)

        $res = ApiClient::get('/transferts_cabine_list.php', $cabineA['token']);
        $this->assertSame(200, $res->status);
        $this->assertCount(2, $res->json['transferts']);
    }

    public function testAdminSeesAllTransferts(): void
    {
        $cabineA = Fixtures::createProfile('cabine');
        $cabineB = Fixtures::createProfile('cabine');
        $admin = Fixtures::createProfile('admin');

        Fixtures::pdo()->prepare('INSERT INTO transferts_cabine (id, from_cabine_id, to_cabine_id, montant, frais, date) VALUES (UUID(), ?, ?, 1000, 150, NOW())')
            ->execute([$cabineA['profile']['id'], $cabineB['profile']['id']]);

        $res = ApiClient::get('/transferts_cabine_list.php', $admin['token']);
        $this->assertCount(1, $res->json['transferts']);
    }

    public function testCabineSeesOnlyOwnResubscriptions(): void
    {
        $cabineA = Fixtures::createProfile('cabine');
        $cabineB = Fixtures::createProfile('cabine');

        $pdo = Fixtures::pdo();
        $pdo->prepare('INSERT INTO resubscriptions (id, cabine_id, formule, prix, date) VALUES (UUID(), ?, \'Premium\', 10000, NOW())')->execute([$cabineA['profile']['id']]);
        $pdo->prepare('INSERT INTO resubscriptions (id, cabine_id, formule, prix, date) VALUES (UUID(), ?, \'VIP\', 20000, NOW())')->execute([$cabineB['profile']['id']]);

        $res = ApiClient::get('/resubscriptions_list.php', $cabineA['token']);
        $this->assertCount(1, $res->json['resubscriptions']);
        $this->assertSame('Premium', $res->json['resubscriptions'][0]['formule']);
    }

    public function testAdminSeesAllResubscriptions(): void
    {
        $cabineA = Fixtures::createProfile('cabine');
        $admin = Fixtures::createProfile('admin');
        Fixtures::pdo()->prepare('INSERT INTO resubscriptions (id, cabine_id, formule, prix, date) VALUES (UUID(), ?, \'Premium\', 10000, NOW())')->execute([$cabineA['profile']['id']]);

        $res = ApiClient::get('/resubscriptions_list.php', $admin['token']);
        $this->assertCount(1, $res->json['resubscriptions']);
    }

    public function testClientCannotAccessEitherEndpoint(): void
    {
        $client = Fixtures::createProfile('client');
        $this->assertSame(403, ApiClient::get('/transferts_cabine_list.php', $client['token'])->status);
        $this->assertSame(403, ApiClient::get('/resubscriptions_list.php', $client['token'])->status);
    }
}
