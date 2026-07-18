<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Base commune : remet la base de test à zéro avant CHAQUE test (isolation
// totale, voir Fixtures::reset()) — la suite est petite, le coût d'un
// TRUNCATE complet par test est négligeable comparé à la sûreté que ça donne.
abstract class ApiTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Fixtures::reset();
    }
}
