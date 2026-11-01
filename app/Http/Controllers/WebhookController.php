<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\WebhookEndpoint;
use App\Support\WebhookGuard;
use App\Support\Webhooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Webhook endpoints in Settings: add, switch on/off, new secret, test, delete. The log of deliveries is shown with each endpoint. */
class WebhookController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2000'],
            'events' => ['nullable', 'array'],
            'events.*' => [Rule::in(Webhooks::EVENTS)],
        ]);
        if ($user->webhookEndpoints()->count() >= Webhooks::MAX_ENDPOINTS) {
            return $this->back()->withErrors(['url' => __('You can have up to :max webhooks. Delete one first.', ['max' => Webhooks::MAX_ENDPOINTS])]);
        }
        $url = trim($data['url']);
        if ($problem = app(WebhookGuard::class)->inspect($url)[0]) {
            return $this->back()->withInput()->withErrors(['url' => WebhookGuard::message($problem)]);
        }
        $secret = Webhooks::newSecret();
        $events = array_values(array_intersect(Webhooks::EVENTS, (array) ($data['events'] ?? [])));
        $endpoint = $user->webhookEndpoints()->create([
            'url' => $url, 'secret' => $secret,
            // every event ticked (or none) means "all", also the ones added later
            'events' => $events === [] || count($events) === count(Webhooks::EVENTS) ? null : $events,
        ]);
        ActivityLog::log('created', 'Webhook', $endpoint->id, 'Added a webhook for '.$endpoint->displayHost());

        return $this->back()->with('webhook_secret', ['id' => $endpoint->id, 'secret' => $secret])->with('status', __('Webhook added. Copy the secret now: it is shown only once.'));
    }

    public function update(Request $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->own($request, $endpoint);
        $active = $request->boolean('is_active');
        // switching it back on starts the count of failures again
        $endpoint->forceFill(['is_active' => $active, 'failure_streak' => $active ? 0 : $endpoint->failure_streak])->save();

        return $this->back()->with('status', $active ? __('Webhook switched on.') : __('Webhook switched off.'));
    }

    public function rotate(Request $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->own($request, $endpoint);
        $secret = Webhooks::newSecret();
        $endpoint->update(['secret' => $secret]);
        ActivityLog::log('updated', 'Webhook', $endpoint->id, 'Made a new secret for the webhook to '.$endpoint->displayHost());

        return $this->back()->with('webhook_secret', ['id' => $endpoint->id, 'secret' => $secret])->with('status', __('New secret made. Copy it now: it is shown only once, and the old one no longer works.'));
    }

    public function test(Request $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->own($request, $endpoint);
        Webhooks::ping($endpoint);

        return $this->back()->with('status', __('Test event sent. The result is in the log below.'));
    }

    public function destroy(Request $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->own($request, $endpoint);
        $endpoint->delete();
        ActivityLog::log('deleted', 'Webhook', $endpoint->id, 'Deleted the webhook to '.$endpoint->displayHost());

        return $this->back()->with('status', __('Webhook deleted.'));
    }

    private function own(Request $request, WebhookEndpoint $endpoint): void
    {
        abort_unless($endpoint->user_id === $request->user()->id, 404);
    }

    private function back(): RedirectResponse
    {
        return redirect()->route('settings')->with('active_tab', 'webhooks');
    }
}
