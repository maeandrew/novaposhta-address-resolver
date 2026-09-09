<?php

declare(strict_types=1);

namespace MaeAndrew\NovaPoshtaAddressResolver\Enums;

enum ResolutionStatus: string
{
    case RESOLVED = 'resolved';
    case AMBIGUOUS = 'ambiguous';
    case NOT_FOUND = 'not_found';
    case PROVIDER_ERROR = 'provider_error';
    case INVALID_INPUT = 'invalid_input';
}
