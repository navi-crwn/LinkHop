<?php

use App\Models\Link;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\LinkService;
use App\Support\WebhookGuard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/*
| A row saved before encrypted note links were refused, and not scrubbed yet, still redirects. Nothing new may copy its
| key: not the redirect cache, not a webhook. Made-up keys.
*/

const OR_KEY = 'FAKEKEYzzz999';

function orLegacy(User $user, array $more = []): Link
{
    $id = DB::table('links')->insertGetId($more + ['user_id' => $user->id, 'slug' => 'oldrow', 'target_url' => 'https://note.hel.ink/Ab3dEf#k='.OR_KEY, 'status' => 'active', 'redirect_type' => '302', 'created_at' => now(), 'updated_at' => now()]);

    return Link::findOrFail($id);
}

function orHook(User $user): void
{
    app()->bind(WebhookGuard::class, fn () => new class extends WebhookGuard
    {
        protected function resolveHost(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
    $user->webhookEndpoints()->create(['url' => 'https://hook.example/in', 'secret' => 'whsec_test']);
}

it('sends no key in the webhook when an old row is deleted', function () {
    Http::fake(['*' => Http::response('', 200)]);
    $user = User::factory()->create();
    orHook($user);
    $link = orLegacy($user, ['fallback_url' => 'https://n.hel.ink/Ab3dEf#k='.OR_KEY]);
    $this->actingAs($user)->delete("/links/{$link->id}");
    $delivery = WebhookDelivery::firstOrFail();
    expect($delivery->event)->toBe('link.deleted')
        ->and($delivery->payload)->not->toContain(OR_KEY)->toContain('note.hel.ink');
    Http::assertNotSent(fn ($request) => str_contains($request->body(), OR_KEY));
});

it('sends no key in a webhook for any other event of an old row', function () {
    Http::fake(['*' => Http::response('', 200)]);
    $user = User::factory()->create();
    orHook($user);
    $link = orLegacy($user);
    $link->update(['custom_title' => 'renamed']);
    $link->update(['status' => 'inactive']);
    expect(WebhookDelivery::pluck('payload')->implode(' '))->not->toContain(OR_KEY)->and(WebhookDelivery::count())->toBeGreaterThan(0);
});

it('does not change a webhook for an ordinary link', function () {
    Http::fake(['*' => Http::response('', 200)]);
    $user = User::factory()->create();
    orHook($user);
    $link = Link::create(['user_id' => $user->id, 'slug' => 'plainlink', 'target_url' => 'https://example.com/a#k=1', 'status' => 'active']);
    expect(json_decode(WebhookDelivery::firstOrFail()->payload, true)['data']['link']['target_url'])->toBe('https://example.com/a#k=1');
    expect($link->exists)->toBeTrue();
});

it('redirects an old row without keeping it in the redirect cache', function () {
    $user = User::factory()->create();
    orLegacy($user);
    $this->get('/l/oldrow')->assertStatus(302)->assertRedirect('https://note.hel.ink/Ab3dEf#k='.OR_KEY);
    expect(Cache::has(app(LinkService::class)->cacheKey('oldrow')))->toBeFalse();
});

it('keeps ordinary links in the redirect cache', function () {
    Link::create(['user_id' => User::factory()->create()->id, 'slug' => 'plainlink', 'target_url' => 'https://note.hel.ink/Ab3dEf', 'status' => 'active']);
    $this->get('/l/plainlink')->assertStatus(302);
    expect(Cache::has(app(LinkService::class)->cacheKey('plainlink')))->toBeTrue();
});
