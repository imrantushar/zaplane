<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Suremembers;

class SuremembersTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Suremembers::class;
    }
}