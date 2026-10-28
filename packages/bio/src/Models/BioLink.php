<?php

namespace Hop\Bio\Models;

use App\Models\Link;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BioLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'bio_page_id',
        'type',
        'title',
        'url',
        'content',
        'description',
        'icon',
        'thumbnail_url',
        'brand',
        'btn_bg_color',
        'btn_text_color',
        'btn_border_color',
        'btn_icon_invert',
        'custom_icon',
        'settings',
        'is_active',
        'order',
        'click_count',
        // Advanced block fields
        'embed_url',
        'countdown_date',
        'countdown_label',
        'map_address',
        'map_zoom',
        'vcard_name',
        'vcard_phone',
        'vcard_email',
        'vcard_company',
        'faq_items',
        'html_content',
        'entrance_animation',
        'attention_animation',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'btn_icon_invert' => 'boolean',
        'order' => 'integer',
        'click_count' => 'integer',
        'settings' => 'array',
        'faq_items' => 'array',
        'countdown_date' => 'datetime',
        'map_zoom' => 'integer',
    ];

    protected static function booted(): void
    {
        // Unguessable id for public click URLs (never expose the sequential id).
        static::creating(fn (BioLink $link) => $link->public_id ??= Str::random(12));
    }

    public function bioPage(): BelongsTo
    {
        return $this->belongsTo(BioPage::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(BioClick::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function recordClick(array $data): BioClick
    {
        $click = $this->clicks()->create([
            'clicked_at' => now(),
            'country' => $data['country'] ?? null,
            'device' => $data['device'] ?? null,
            'browser' => $data['browser'] ?? null,
            'referrer' => $data['referrer'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
        ]);
        $this->incrementClicks();

        return $click;
    }

    public function incrementClicks(): void
    {
        $this->increment('click_count');
    }

    /** Human name of each block type, for lists that show blocks outside the editor. */
    public const TYPE_NAMES = [
        'link' => 'Link', 'image' => 'Image', 'text' => 'Text', 'header' => 'Header',
        'divider' => 'Divider', 'spacer' => 'Spacer', 'video' => 'Video', 'youtube' => 'YouTube',
        'music' => 'Music', 'spotify' => 'Spotify', 'soundcloud' => 'SoundCloud', 'vcard' => 'Contact card',
        'html' => 'HTML', 'countdown' => 'Countdown', 'map' => 'Map', 'faq' => 'FAQ',
        'email' => 'Email', 'code' => 'Code',
    ];

    public function typeName(): string
    {
        return self::TYPE_NAMES[$this->type] ?? Str::headline($this->type ?: 'block');
    }

    /**
     * Main line for a block in admin lists. Many block types (text, divider, embeds, FAQ…)
     * have no title, so fall back to what the block actually holds.
     */
    public function displayTitle(): string
    {
        if (filled($this->title)) {
            return $this->title;
        }

        return match ($this->type) {
            'text' => $this->excerpt($this->content),
            'vcard' => $this->vcard_name,
            'countdown' => $this->countdown_label,
            'map' => $this->map_address,
            'faq' => $this->faq_items[0]['question'] ?? null,
            'html' => $this->excerpt($this->html_content),
            default => null,
        } ?: $this->typeName();
    }

    /** Second line: the destination or a short summary of the block's content. */
    public function displaySummary(): ?string
    {
        $detail = match ($this->type) {
            'link', 'email', 'image' => $this->url,
            'video', 'youtube', 'music', 'spotify', 'soundcloud' => $this->embed_url ?: $this->url,
            'text' => filled($this->title) ? $this->excerpt($this->content) : null,
            'vcard' => collect([$this->vcard_phone, $this->vcard_email, $this->vcard_company])->filter()->implode(' · '),
            'countdown' => $this->countdown_date?->format('j M Y, H:i'),
            'map' => filled($this->title) ? $this->map_address : null,
            'faq' => ($n = count($this->faq_items ?? [])) ? $n.' '.Str::plural('question', $n) : null,
            'code' => $this->content,
            default => $this->url,
        };
        if ($this->type === 'link') {
            return $detail ?: null;
        }

        return collect([$this->typeName(), $detail ?: null])->filter()->implode(' · ');
    }

    /** Web address the summary line should link to, when the block points somewhere. */
    public function displaySummaryUrl(): ?string
    {
        $target = match ($this->type) {
            'video', 'youtube', 'music', 'spotify', 'soundcloud' => $this->embed_url ?: $this->url,
            'link', 'image' => $this->url,
            default => null,
        };

        return $target && preg_match('#^https?://#i', $target) ? $target : null;
    }

    private function excerpt(?string $html): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $text === '' ? null : Str::limit($text, 60);
    }
}
