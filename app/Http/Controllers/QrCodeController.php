<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Models\QrCode;
use App\Support\EncryptedNoteLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The dashboard's "QR codes" page: the QR codes of the member's short links and the stand-alone codes (Wi-Fi,
 * contact card, link or text) the member saved. Every query is scoped to the signed-in member, and a code of
 * someone else is answered exactly like one that does not exist (404). The payload of a code can be a Wi-Fi
 * password: it is only validated and stored, never logged, and the page that shows it is no-store.
 */
class QrCodeController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $saved = QrCode::where('user_id', $userId)->latest()->latest('id')->get();
        $links = Link::where('user_id', $userId)->whereNotNull('qr_code_path')->latest('updated_at')->limit(60)->get();

        return view('qr-codes', ['saved' => $saved, 'links' => $links, 'max' => QrCode::MAX_PER_USER]);
    }

    /** POST /qr-codes (JSON): save the code the page just drew. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(QrCode::TYPES)],
            'label' => ['nullable', 'string', 'max:80', EncryptedNoteLink::rule()],
            'payload' => ['required', 'string', 'max:'.QrCode::MAX_PAYLOAD, EncryptedNoteLink::rule()],
            'style' => ['required', 'array'],
            'style.fg' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'style.bg' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'style.dots' => ['required', Rule::in(QrCode::DOTS)],
            'style.corners' => ['required', Rule::in(QrCode::CORNERS)],
        ]);
        // A Wi-Fi or contact code must look like one, so a request made by hand cannot store arbitrary text under a type.
        $prefix = ['wifi' => 'WIFI:', 'vcard' => 'BEGIN:VCARD'][$data['type']] ?? null;
        if ($prefix !== null && ! str_starts_with($data['payload'], $prefix)) {
            return response()->json(['message' => __('Could not save the QR code (:status).', ['status' => 422])], 422);
        }
        $userId = (int) $request->user()->getAuthIdentifier();
        if (QrCode::where('user_id', $userId)->count() >= QrCode::MAX_PER_USER) {
            return response()->json(['message' => __('You can keep up to :count saved QR codes. Delete one to save another.', ['count' => QrCode::MAX_PER_USER])], 422);
        }
        $label = trim((string) ($data['label'] ?? ''));
        $qr = QrCode::create([
            'user_id' => $userId,
            'type' => $data['type'],
            'label' => $label !== '' ? $label : $this->typeName($data['type']),
            'payload' => $data['payload'],
            'style' => [
                'fg' => strtolower($data['style']['fg']), 'bg' => strtolower($data['style']['bg']),
                'dots' => $data['style']['dots'], 'corners' => $data['style']['corners'],
            ],
        ]);
        $request->session()->flash('status', __('QR code saved.'));

        return response()->json(['id' => $qr->id], 201);
    }

    /** PATCH /qr-codes/{id}: a new label. */
    public function update(Request $request, string $id): RedirectResponse
    {
        $qr = $this->owned($request, $id);
        $data = $request->validate(['label' => ['required', 'string', 'max:80', EncryptedNoteLink::rule()]]);
        $qr->update(['label' => trim($data['label'])]);

        return redirect()->route('qr-codes.index')->with('status', __('QR code renamed.'));
    }

    /** DELETE /qr-codes/{id} */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->owned($request, $id)->delete();

        return redirect()->route('qr-codes.index')->with('status', __('QR code deleted.'));
    }

    private function owned(Request $request, string $id): QrCode
    {
        return QrCode::where('user_id', (int) $request->user()->getAuthIdentifier())->whereKey($id)->firstOrFail();
    }

    private function typeName(string $type): string
    {
        return ['wifi' => __('Wi-Fi'), 'vcard' => __('Contact card'), 'text' => __('Link or text')][$type];
    }
}
