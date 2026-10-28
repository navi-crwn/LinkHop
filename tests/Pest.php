<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/
// TestCase refreshes the database (and builds the hub's account tables from database/migrations-archive).
pest()->extend(TestCase::class)
    ->in('Feature', '../packages/bio/tests');
// Tests that need the hop/bio package (its routes and tables). `BIO_ENABLED=false php artisan test --exclude-group=bio`
// runs the suite with bio off, which is what a LinkHop without bio looks like.
pest()->group('bio')->in(
    '../packages/bio/tests',
    'Feature/AbuseProtectionTest.php',
    'Feature/AdminPagesSmokeTest.php',
    'Feature/BackendAuditTest.php',
    'Feature/ExampleBioSeederTest.php',
    'Feature/HopContentApiTest.php',
    'Feature/ModerationLockTest.php',
    'Feature/PrivacyRetentionTest.php',
    'Feature/VisitorPrivacyTest.php',
);
/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/
expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});
/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/
function something()
{
    // ..
}
