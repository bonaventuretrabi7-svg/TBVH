<?php
declare(strict_types=1);

// api/notifications_list.php / notifications_mark_read.php /
// notifications_mark_all_read.php (Phase C) — la table `notifications`
// est déjà peuplée depuis la Phase 4 par createNotification()
// (api/bootstrap.php), appelée par la quasi-totalité des endpoints
// métier ; ces tests couvrent uniquement la lecture/mise à jour
// nouvellement ajoutées.
final class NotificationsTest extends ApiTestCase
{
    private function insertNotif(string $userId, string $message, bool $lu = false): string
    {
        $id = bin2hex(random_bytes(16));
        Fixtures::pdo()->prepare('INSERT INTO notifications (id, utilisateur_id, message, lu, date, type) VALUES (?, ?, ?, ?, NOW(), \'info\')')
            ->execute([$id, $userId, $message, $lu ? 1 : 0]);
        return $id;
    }

    public function testListReturnsOnlyOwnNotifications(): void
    {
        $client = Fixtures::createProfile('client');
        $other = Fixtures::createProfile('client');
        $this->insertNotif($client['profile']['id'], 'Pour moi');
        $this->insertNotif($other['profile']['id'], 'Pas pour moi');

        $res = ApiClient::get('/notifications_list.php', $client['token']);
        $this->assertSame(200, $res->status);
        $this->assertCount(1, $res->json['notifications']);
        $this->assertSame('Pour moi', $res->json['notifications'][0]['message']);
    }

    public function testMarkReadOnlyAffectsOwnNotification(): void
    {
        $client = Fixtures::createProfile('client');
        $other = Fixtures::createProfile('client');
        $myNotifId = $this->insertNotif($client['profile']['id'], 'À moi');
        $otherNotifId = $this->insertNotif($other['profile']['id'], 'Pas à moi');

        $res = ApiClient::post('/notifications_mark_read.php', ['notification_id' => $myNotifId], $client['token']);
        $this->assertTrue($res->ok());

        // Tente de marquer la notification d'un AUTRE compte — ne doit rien changer.
        ApiClient::post('/notifications_mark_read.php', ['notification_id' => $otherNotifId], $client['token']);

        $mine = Fixtures::pdo()->query("SELECT lu FROM notifications WHERE id = '$myNotifId'")->fetchColumn();
        $theirs = Fixtures::pdo()->query("SELECT lu FROM notifications WHERE id = '$otherNotifId'")->fetchColumn();
        $this->assertSame(1, (int)$mine);
        $this->assertSame(0, (int)$theirs, 'la notification du compte tiers ne doit jamais être modifiable par un autre appelant');
    }

    public function testMarkAllReadOnlyAffectsCallersNotifications(): void
    {
        $client = Fixtures::createProfile('client');
        $other = Fixtures::createProfile('client');
        $this->insertNotif($client['profile']['id'], 'A');
        $this->insertNotif($client['profile']['id'], 'B');
        $this->insertNotif($other['profile']['id'], 'C');

        $res = ApiClient::post('/notifications_mark_all_read.php', [], $client['token']);
        $this->assertTrue($res->ok());

        $unreadMine = (int)Fixtures::pdo()->query("SELECT COUNT(*) FROM notifications WHERE utilisateur_id = '{$client['profile']['id']}' AND lu = 0")->fetchColumn();
        $unreadTheirs = (int)Fixtures::pdo()->query("SELECT COUNT(*) FROM notifications WHERE utilisateur_id = '{$other['profile']['id']}' AND lu = 0")->fetchColumn();
        $this->assertSame(0, $unreadMine);
        $this->assertSame(1, $unreadTheirs);
    }

    public function testEndpointsRequireAuth(): void
    {
        $this->assertSame(401, ApiClient::get('/notifications_list.php', null)->status);
    }
}
