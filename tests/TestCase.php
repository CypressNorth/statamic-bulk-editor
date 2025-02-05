<?php

namespace CypressNorth\StatamicBulkEditor\Tests;

use CypressNorth\StatamicBulkEditor\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
