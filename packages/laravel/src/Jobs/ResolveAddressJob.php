<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\ResolutionResult;
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\AddressResolutionService;

final class ResolveAddressJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public readonly AddressInput $input,
        public readonly ?string $driver = null,
        ?string $connection = null,
        ?string $queue = null,
    ) {
        $this->onConnection($connection ?? $this->configuredConnection());
        $this->onQueue($queue ?? $this->configuredQueue());
    }

    public static function dispatch(
        AddressInput $input,
        ?string $driver = null,
        ?string $connection = null,
        ?string $queue = null,
    ): mixed {
        return app(Dispatcher::class)->dispatch(new self($input, $driver, $connection, $queue));
    }

    public function handle(AddressResolutionService $service): ResolutionResult
    {
        return $service->resolve($this->input, $this->driver);
    }

    private function configuredConnection(): ?string
    {
        $connection = config('novaposhta-address-resolver.queue.connection');

        return is_string($connection) && trim($connection) !== '' ? $connection : null;
    }

    private function configuredQueue(): ?string
    {
        $queue = config('novaposhta-address-resolver.queue.queue');

        return is_string($queue) && trim($queue) !== '' ? $queue : null;
    }
}
