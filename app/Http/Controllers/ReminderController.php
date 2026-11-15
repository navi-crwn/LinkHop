<?php

namespace App\Http\Controllers;

use App\Support\Reminders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /reminders/dismiss {key}: the "Hide" button of a reminder card (hop-ui). The card stays away for a week. */
class ReminderController extends Controller
{
    public function dismiss(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', fn (string $attribute, mixed $value, \Closure $fail) => Reminders::known((string) $value) || $fail(__('validation.in', ['attribute' => $attribute]))],
        ]);
        $again = Reminders::dismiss($request->user(), $data['key']);

        return response()->json(['ok' => true, 'key' => $data['key'], 'shows_again_at' => $again->toIso8601ZuluString()]);
    }
}
