<?php

namespace App\Http\Requests;

use App\Support\EncryptedNoteLink;
use App\Support\LinkRouting;
use App\Support\ReservedSlugs;
use Hop\Safety\LinkSafety;
use Illuminate\Foundation\Http\FormRequest;

class ShortenLinkRequest extends FormRequest
{
    use ForgetsNoteKeysOnFailure;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_url' => ['required', 'string', 'max:2000'],
            'slug' => [
                'nullable',
                'regex:/^[a-z0-9-]+$/i',
                'min:3',
                'max:48',
                ReservedSlugs::rule(),
            ],
            'cf-turnstile-response' => ['nullable', 'string'],
            'content_warning' => ['nullable', 'in:none,sensitive,adult'],
            'warning_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation()
    {
        $url = $this->input('target_url');
        if ($url && is_string($url) && ! preg_match('/^https?:\/\//i', $url)) {
            $this->merge([
                'target_url' => LinkRouting::address($url),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug can only contain letters, numbers, and hyphens.',
            'target_url.url' => 'Invalid URL format. Please check the URL format.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $url = $this->input('target_url');
            if ($url && ! filter_var($url, FILTER_VALIDATE_URL)) {
                $validator->errors()->add('target_url', 'Invalid URL format. Please check the URL format.');
            }
            if ($url && ! $validator->errors()->has('target_url')) {
                $reason = app(LinkSafety::class)->rejectionReason($url, (string) $this->input('slug'));
                if ($reason) {
                    $validator->errors()->add('target_url', $reason);
                }
            }
            // The WhatsApp rotator is for accounts: a guest request that carries one is refused, never quietly dropped.
            if ($this->hasAny(['store_mode', 'store_links', 'routing_rules'])) {
                $validator->errors()->add('store_mode', __('Routing and app store links are for accounts. Sign in to use them.'));
            }
            if ($this->hasAny(['wa_mode', 'wa_numbers'])) {
                $validator->errors()->add('wa_mode', __('The WhatsApp rotator is for accounts. Sign in to use it.'));
            }
            app(EncryptedNoteLink::class)->refuseIn($validator, $this->all(), ['warning_reason']);
        });
    }
}
