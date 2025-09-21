<?php

namespace WooUaShippingPro\Providers;

use WooUaShippingPro\Providers\Contracts\CarrierInterface;
use WooUaShippingPro\Settings\Options;

class Factory
{
    public static function make(?string $carrier = null): ?CarrierInterface
    {
        $carrier = $carrier ?: Options::get(Options::CARRIER, 'nova_poshta');

        switch ($carrier) {
            case 'nova_poshta':
                return new NovaPoshta((string) Options::get(Options::API_KEY_NP));
            case 'ukrposhta':
                return new Ukrposhta((string) Options::get(Options::API_KEY_UP));
            default:
                return null;
        }
    }
}
