<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Laravel\Tests;

use PHPUnit\Framework\Attributes\Test;

final class CommandTest extends TestCase
{
    #[Test]
    public function it_resolves_json_in_dry_run_mode(): void
    {
        $this->artisan('novaposhta:resolve', [
            'address' => 'Київ, відділення №285',
            '--json' => true,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('"status": "resolved"')
            ->assertExitCode(0);
    }
}
