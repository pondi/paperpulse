<?php

use Pest\TestSuite;
use Tests\DuskTestCase;

it('registers the namespaced browser test case while running a unit test', function () {
    expect(TestSuite::getInstance()->tests->getUsesForPath(dirname(__DIR__).'/Browser'))
        ->toBe([DuskTestCase::class]);
});
