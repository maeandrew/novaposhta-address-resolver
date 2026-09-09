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

    #[Test]
    public function it_returns_a_nonzero_code_when_manual_review_is_required(): void
    {
        $this->artisan('novaposhta:resolve', [
            'address' => 'Київ',
            '--json' => true,
        ])
            ->expectsOutputToContain('"status": "ambiguous"')
            ->assertExitCode(2);
    }
}
