<?php

namespace App\Http\Requests\Concerns;

use App\Support\EncryptedNoteLink;
use Illuminate\Contracts\Validation\Validator;

/**
 * A refused form is sent back with what the person typed (the session's old input). When what they typed is the
 * address of an encrypted note, that old input would carry its key into the sessions table. So the fragment is cut
 * out of such an address before the redirect, and the form comes back with the address minus the key.
 */
trait ForgetsNoteKeysOnFailure
{
    protected function failedValidation(Validator $validator)
    {
        // the handler flashes the application's request, not this form request's copy of it
        request()->merge(EncryptedNoteLink::scrubInput(request()->all()));

        parent::failedValidation($validator);
    }
}
