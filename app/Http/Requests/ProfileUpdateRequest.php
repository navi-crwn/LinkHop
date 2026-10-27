<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Username;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge(['username' => Username::normalize($this->input('username'))]);
        }
        if ($this->has('website')) {
            $w = trim((string) $this->input('website'));
            if ($w !== '' && ! preg_match('#^https?://#i', $w)) {
                $w = 'https://'.$w;
            }
            $this->merge(['website' => $w]);
        }
        $this->merge(['show_owner' => $this->boolean('show_owner', ! $this->has('_profile_form'))]);
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
            'username' => ['sometimes', 'required', 'string', 'max:30', function (string $attr, mixed $value, \Closure $fail) use ($user) {
                if ($why = Username::problem((string) $value, $user)) {
                    $fail($why);
                }
            }],
            'bio' => ['sometimes', 'nullable', 'string', 'max:300'],
            'website' => ['sometimes', 'nullable', 'string', 'max:200', 'url:http,https'],
            'location' => ['sometimes', 'nullable', 'string', 'max:100'],
            'show_owner' => ['sometimes', 'boolean'],
        ];
    }
}
