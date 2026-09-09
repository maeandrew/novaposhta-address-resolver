<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Laravel\Console;

use Illuminate\Console\Command;
use MaeAndrew\NovaPoshtaAddressResolver\DTO\AddressInput;
use MaeAndrew\NovaPoshtaAddressResolver\Laravel\AddressResolutionService;

final class ResolveAddressCommand extends Command
{
    protected $signature = 'novaposhta:resolve
        {address : Free-form Nova Poshta address}
        {--driver= : Configured resolver driver}
        {--json : Print the complete serializable result as JSON}
        {--dry-run : Resolve without invoking any host persistence callback}';

    protected $description = 'Resolve a Nova Poshta address through the configured provider.';

    public function __construct(private readonly AddressResolutionService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $addressArgument = $this->argument('address');
        $address = is_string($addressArgument) ? $addressArgument : '';
        $driver = $this->option('driver');
        $driver = is_string($driver) && trim($driver) !== '' ? $driver : null;
        $result = $this->service->resolve(AddressInput::fromText($address), $driver);

        if ((bool) $this->option('json')) {
            $this->line(json_encode(
                $result->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ));

            return self::SUCCESS;
        }

        $this->line('Status: ' . $result->status->value);
        $this->line('Confidence: ' . $result->confidence);

        if ($result->settlement !== null) {
            $this->line('Settlement: ' . $result->settlement->name . ' [' . $result->settlement->ref . ']');
        }

        if ($result->warehouse !== null) {
            $this->line('Warehouse: ' . $result->warehouse->name . ' [' . $result->warehouse->ref . ']');
        }

        if ($result->needsReview()) {
            $this->warn('Manual review is required; no host data was persisted.');
        }

        if ((bool) $this->option('dry-run')) {
            $this->comment('Dry run: this package does not persist host models.');
        }

        return self::SUCCESS;
    }
}
