<?php

namespace App\Http\Requests;

use App\Models\AbuseFlag;
use App\Models\Link;
use App\Models\RetiredSlug;
use App\Support\DeepLinks;
use App\Support\EncryptedNoteLink;
use App\Support\LinkLimits;
use App\Support\LinkRouting;
use App\Support\ReservedSlugs;
use App\Support\StoreLinks;
use App\Support\WhatsAppRotator;
use Hop\Safety\LinkSafety;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageLinkRequest extends FormRequest
{
    use ForgetsNoteKeysOnFailure;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $linkId = $this->route('link')?->id;
        // Folders/tags belong to the link owner (an admin may be editing).
        $ownerId = $this->route('link')?->user_id ?? $this->user()?->id;

        return [
            'target_url' => ['required', 'string', 'max:2000'],
            'slug' => [
                'nullable',
                'regex:/^[a-z0-9-]+$/i',
                'min:3',
                'max:48',
                Rule::unique('links', 'slug')->ignore($linkId),
                fn ($attribute, $value, $fail) => $value && RetiredSlug::isBlocked($value) && $value !== $this->route('link')?->slug
                    ? $fail('The slug has already been taken.') : null,
                ReservedSlugs::rule($this->route('link')?->slug),
            ],
            'status' => ['required', Rule::in([Link::STATUS_ACTIVE, Link::STATUS_INACTIVE]), function ($attribute, $value, $fail) use ($linkId) {
                $link = $this->route('link');
                if ($value === Link::STATUS_ACTIVE && $link?->status !== Link::STATUS_ACTIVE && $linkId && AbuseFlag::locks('link', $linkId)) {
                    $fail('This link was disabled by moderation. Contact support to appeal.');
                }
            }],
            'password' => ['nullable', 'string', 'min:6'],
            // Editing must not fail just because a link already expired.
            'expires_at' => $linkId ? ['nullable', 'date'] : ['nullable', 'date', 'after:now'],
            'redirect_type' => ['nullable', Rule::in(['301', '302', '307', '308'])],
            ...LinkLimits::rules(),
            ...LinkRouting::rules(),
            ...StoreLinks::rules(),
            ...WhatsAppRotator::rules(),
            ...DeepLinks::rules(),
            'content_warning' => ['nullable', Rule::in(['none', 'sensitive', 'adult'])],
            'warning_reason' => ['nullable', 'string', 'max:255'],
            'custom_title' => ['nullable', 'string', 'max:255'],
            'custom_description' => ['nullable', 'string', 'max:1000'],
            'custom_image_url' => ['nullable', 'url', 'max:500'],
            'folder_id' => [
                'nullable',
                Rule::exists('folders', 'id')->where(fn ($query) => $query->where('user_id', $ownerId)),
            ],
            'tags' => ['nullable', 'array'],
            'tags.*' => [
                Rule::exists('tags', 'id')->where(fn ($query) => $query->where('user_id', $ownerId)),
            ],
            'comment' => ['nullable', 'string', 'max:500'],
            'remove_password' => ['nullable', 'boolean'],
            'qr_fg_color' => ['nullable', 'string', 'max:7'],
            'qr_bg_color' => ['nullable', 'string', 'max:7'],
            'qr_logo_url' => ['nullable', 'url', 'max:500'],
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge(WhatsAppRotator::tidy($this->all()));
        // A rotator link has no destination field: it stands for the first number's wa.me address.
        $rows = $this->input('wa_numbers');
        if ($this->input('wa_mode') && is_array($rows) && is_array($rows[0] ?? null)) {
            $this->merge(['target_url' => WhatsAppRotator::url(WhatsAppRotator::normalize($rows[0]['number'] ?? null) ?? '')]);
        }
        $url = $this->input('target_url');
        if ($url && is_string($url) && ! preg_match('/^https?:\/\//i', $url)) {
            $this->merge([
                'target_url' => LinkRouting::address($url),
            ]);
        }
        // The app store form writes the routing rules itself (and replaces any that were sent).
        $this->merge(StoreLinks::tidy($this->all()));
        $this->merge(LinkRouting::tidy($this->all()));
        $this->merge(DeepLinks::tidy($this->all()));
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
            if (! $validator->errors()->has('fallback_url') && $problem = LinkLimits::fallbackProblem($this->input('fallback_url'))) {
                $validator->errors()->add('fallback_url', $problem);
            }
            if (StoreLinks::active($this->all())) {
                StoreLinks::check($validator, $this->all());
            } else {
                LinkRouting::check($validator, $this->all());
            }
            WhatsAppRotator::check($validator, $this->all());
            DeepLinks::check($validator, $this->all());
            if ($problem = LinkLimits::orderProblem($this->input('starts_at'), $this->input('expires_at'))) {
                $validator->errors()->add('expires_at', $problem);
            }
            if ($url && ! $validator->errors()->has('target_url')) {
                $reason = app(LinkSafety::class)->rejectionReason($url, implode(' ', [$this->input('slug'), $this->input('custom_title'), $this->input('custom_description')]));
                if ($reason) {
                    $validator->errors()->add('target_url', $reason);
                }
            }
            app(EncryptedNoteLink::class)->refuseIn($validator, $this->all(), ['warning_reason', 'custom_title', 'custom_description', 'custom_image_url', 'qr_logo_url', 'comment']);
        });
    }
}
