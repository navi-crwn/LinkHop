<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A stand-alone QR code a member saved on the dashboard's "QR codes" page. The code itself is drawn in the browser;
 * the server keeps only what it carries (`payload`, encrypted at rest: it can hold a Wi-Fi password or contact
 * details, so it is never logged and never sent anywhere but back to its owner) and how it looks.
 */
class QrCode extends Model
{
    use HasUlids;

    public const TYPES = ['wifi', 'vcard', 'text'];

    public const DOTS = ['square', 'rounded', 'extra-rounded', 'dots', 'classy', 'classy-rounded'];

    public const CORNERS = ['square', 'extra-rounded', 'dot'];

    /** Saved codes per account, and the longest text one may carry (the page's own limit is 2000 bytes). */
    public const MAX_PER_USER = 50;

    public const MAX_PAYLOAD = 2500;

    protected $fillable = ['user_id', 'type', 'label', 'payload', 'style'];

    protected $hidden = ['payload', 'label'];

    protected function casts(): array
    {
        return ['label' => 'encrypted', 'payload' => 'encrypted', 'style' => 'array'];
    }

    /**
     * The label and the content, or null when this row cannot be opened (the app key changed and the old key is not
     * kept in APP_PREVIOUS_KEYS): one such row must not take down the page or the account export.
     *
     * @return array{label: string, payload: string}|null
     */
    public function opened(): ?array
    {
        try {
            return ['label' => (string) $this->label, 'payload' => (string) $this->payload];
        } catch (DecryptException) {
            return null;
        }
    }
}
