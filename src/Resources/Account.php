<?php

declare(strict_types=1);

namespace Mozex\Iminify\Resources;

use Mozex\Iminify\Http\ApiRequest;
use Mozex\Iminify\Responses\Account as AccountResponse;

class Account extends ApiResource
{
    public function get(): AccountResponse
    {
        return $this->record(ApiRequest::get('account'), AccountResponse::from(...));
    }
}
