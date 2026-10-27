<?php

namespace App\Http\Controllers;

use App\Helpers\SocialUrlHelper;
use App\Models\AbuseFlag;
use App\Models\BioClick;
use App\Models\BioPage;
use App\Services\LinkSafety;
use App\Support\BioUploads;
use App\Support\CssValue;
use App\Support\QrPng;
use App\Support\ReservedSlugs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BioPageController extends Controller
{
    private const ANALYTICS_RANGES = [7, 30];

    public const MAX_PAGES = 3;

    public function index()
    {
        $bioPages = auth()->user()->bioPages()->withCount('links')->latest()->get();

        return view('bio.index', compact('bioPages'));
    }

    public function create()
    {
        return view('bio.create');
    }

    public function checkSlug(Request $request)
    {
        // Same rules as store() and update(), so "available" here means saving will accept it.
        $current = $request->filled('current') ? $request->user()->bioPages()->where('slug', $request->input('current'))->first() : null;
        $slug = strtolower((string) $request->input('slug'));
        $validator = validator(['slug' => $slug], ['slug' => $this->slugRules($current)]);

        return response()->json(['available' => $validator->passes(), 'message' => $validator->errors()->first('slug')]);
    }

    /**
     * Validation for a bio page slug; $current is the page being renamed, if any.
     * Members need at least `shortener.bio_slug_min_length` characters; LinkHop's
     * own admin accounts may keep shorter brand slugs. Existing short slugs stay valid.
     */
    private function slugRules(?BioPage $current = null): array
    {
        $min = auth()->user()?->isAdmin() ? 3 : (int) config('shortener.bio_slug_min_length', 5);

        return [
            'required',
            'string',
            'max:20',
            'regex:/^[a-z0-9]+$/',
            fn ($attribute, $value, $fail) => $value !== $current?->slug && strlen((string) $value) < $min
                ? $fail("The slug must be at least {$min} letters or numbers.") : null,
            Rule::unique('bio_pages', 'slug')->ignore($current?->id),
            // Reserved words are refused for new slugs; an existing slug is left alone.
            ReservedSlugs::rule($current?->slug),
        ];
    }

    public function generateQR(Request $request)
    {
        $data = $request->input('data');
        $size = (int) $request->input('size', 300);
        $size = max(50, min($size, 1000)); // Clamp to prevent memory-exhaustion DoS
        $color = $request->input('color', '000000');
        $bgcolor = $request->input('bgcolor', 'ffffff');
        $logoData = $request->input('logo'); // Base64 logo image
        if (! $data) {
            return response('Missing data parameter', 400);
        }
        if (! preg_match('/^[0-9a-fA-F]{6}$/', (string) $color) || ! preg_match('/^[0-9a-fA-F]{6}$/', (string) $bgcolor)) {
            return response('Invalid color parameter', 400);
        }
        if (strlen((string) $data) > 2048) {
            return response('Data too long', 400);
        }
        $rgb = fn (string $hex) => [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        try {
            // High error correction leaves room for the logo overlay.
            $qrString = QrPng::render((string) $data, $size, $rgb($color), $rgb($bgcolor));
        } catch (\Throwable $e) {
            report($e);

            return response('Error generating QR code', 500);
        }
        // Optional logo; anything too large or not an image is ignored (QrPng::logo).
        $logo = QrPng::logo(is_string($logoData) ? $logoData : null);
        $qrImage = $logo ? @imagecreatefromstring($qrString) : false;
        if (! $logo || ! $qrImage) {
            return response($qrString)->header('Content-Type', 'image/png');
        }
        $qrWidth = imagesx($qrImage);
        $qrHeight = imagesy($qrImage);
        // Logo covers 20% of the code, on a white square with 10px padding.
        $logoSize = (int) ($qrWidth * 0.2);
        $logoX = (int) (($qrWidth - $logoSize) / 2);
        $logoY = (int) (($qrHeight - $logoSize) / 2);
        $bgSize = $logoSize + 20;
        $bgX = (int) (($qrWidth - $bgSize) / 2);
        $bgY = (int) (($qrHeight - $bgSize) / 2);
        imagefilledrectangle($qrImage, $bgX, $bgY, $bgX + $bgSize, $bgY + $bgSize, imagecolorallocate($qrImage, 255, 255, 255));
        imagecopyresampled($qrImage, $logo, $logoX, $logoY, 0, 0, $logoSize, $logoSize, imagesx($logo), imagesy($logo));
        ob_start();
        imagepng($qrImage, null, 9);
        $finalQr = ob_get_clean();
        imagedestroy($qrImage);
        imagedestroy($logo);

        return response($finalQr)->header('Content-Type', 'image/png');
    }

    private function handleQrImage($settings, $oldSettings = null)
    {
        if (isset($settings['image']) && str_starts_with($settings['image'], 'data:image')) {
            $base64Image = $settings['image'];
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
                $data = substr($base64Image, strpos($base64Image, ',') + 1);
                $extension = strtolower($type[1]);
                $data = base64_decode($data, true);
                // Only store real images up to 1 MB.
                if (in_array($extension, ['jpg', 'jpeg', 'gif', 'png']) && $data !== false && strlen($data) <= 1048576 && @getimagesizefromstring($data)) {
                    $filename = 'qr-logos/'.uniqid().'.'.$extension;
                    Storage::disk('public')->put($filename, $data);
                    $settings['image'] = '/storage/'.$filename;
                }
            }
        }

        return $settings;
    }

    /**
     * Delete an uploaded bio file once nothing references it any more. Paths can
     * come from user input or be shared by duplicated pages, so never delete a
     * file another page or block still uses.
     */
    /**
     * Every uploaded file a page and its blocks point at.
     *
     * @return array<int, string|null>
     */
    /**
     * Reject abusive page text and unsafe social link values.
     */
    private function assertSafeContent(array $data): void
    {
        $safety = app(LinkSafety::class);
        $errors = [];
        $text = implode(' ', array_filter([$data['slug'] ?? null, $data['title'] ?? null, $data['bio'] ?? null, $data['seo_title'] ?? null, $data['seo_description'] ?? null]));
        if ($text !== '' && array_intersect($safety->assess(null, $text)['signals'], config('abuse.block_categories', [])) !== []) {
            $errors['title'] = 'This content is not allowed due to our security policy.';
        }
        foreach ((array) ($data['social_links'] ?? []) as $i => $social) {
            $value = trim((string) ($social['value'] ?? ''));
            if ($value === '') {
                continue;
            }
            // Check the URL the page will actually link to: some platforms turn a bare
            // "host.tld/path" handle into https://host.tld/path.
            $url = SocialUrlHelper::buildUrl((string) ($social['platform'] ?? ''), $value);
            if ($safety->scheme($url) !== null && ($reason = $safety->rejectionReason($url, '', LinkSafety::BIO_SCHEMES))) {
                $errors["social_links.{$i}.value"] = $reason;
            }
        }
        if (! empty($data['background_image']) && ! str_starts_with($data['background_image'], '/')
            && ($reason = $safety->rejectionReason($data['background_image']))) {
            $errors['background_image'] = $reason;
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function store(Request $request)
    {
        if (auth()->user()->bioPages()->count() >= self::MAX_PAGES) {
            return back()->withErrors(['slug' => 'You have reached the maximum limit of '.self::MAX_PAGES.' bio pages.']);
        }
        $request->merge(['slug' => strtolower($request->slug)]);
        $validated = $request->validate([
            'slug' => $this->slugRules(),
            'title' => 'required|string|max:'.BioPage::TITLE_MAX,
            'bio' => 'nullable|string|max:'.BioPage::BIO_MAX,
            'qr_settings' => 'nullable|json',
        ]);
        $this->assertSafeContent($validated);
        $qrSettings = $request->filled('qr_settings') ? json_decode($request->qr_settings, true) : null;
        if ($qrSettings) {
            $qrSettings = $this->handleQrImage($qrSettings);
        }
        $bioPage = auth()->user()->bioPages()->create([
            'slug' => $validated['slug'],
            'title' => $validated['title'],
            'bio' => $validated['bio'],
            'theme' => 'default',
            'qr_settings' => $qrSettings,
        ]);

        return redirect()->route('bio.edit', $bioPage)->with('success', 'Bio page created successfully!');
    }

    public function edit(BioPage $bioPage)
    {
        $this->authorize('update', $bioPage);
        $bioPage->load(['links' => function ($query) {
            $query->orderBy('order');
        }]);
        $brands = config('brands.platforms', []);

        // Use the new editor view
        return view('bio.editor.index', compact('bioPage', 'brands'));
    }

    public function update(Request $request, BioPage $bioPage)
    {
        $this->authorize('update', $bioPage);
        // Two editor tabs: the later save must not silently overwrite the earlier one (L2).
        $base = $request->input('base_updated_at');
        if (is_string($base) && $base !== '' && $bioPage->updated_at
            && strtotime($base) !== $bioPage->updated_at->getTimestamp()) {
            return response()->json([
                'message' => 'This page was changed in another tab or window. Reload the editor to get the latest version.',
                'conflict' => true,
            ], 409);
        }
        if ($request->has('slug')) {
            $request->merge(['slug' => strtolower($request->slug)]);
        }
        $validated = $request->validate([
            'slug' => ['sometimes', ...$this->slugRules($bioPage)],
            'title' => 'sometimes|required|string|max:'.BioPage::TITLE_MAX,
            'bio' => 'nullable|string|max:'.BioPage::BIO_MAX,
            'theme' => CssValue::keyword(50),
            'layout' => CssValue::keyword(50),
            'background_type' => 'nullable|in:color,gradient,image,solid',
            'background_value' => ['nullable', 'string', 'max:500', fn ($attribute, $value, $fail) => CssValue::isBackground($value) ? null : $fail('Use a colour, a gradient or an uploaded image.')],
            'background_color' => CssValue::color(20),
            'background_gradient' => ['nullable', 'string', 'max:255', 'regex:'.CssValue::GRADIENT],
            'background_image' => ['nullable', 'string', 'max:500', 'regex:'.CssValue::IMAGE],
            'text_color' => CssValue::color(50),
            'title_color' => CssValue::color(50),
            'bio_color' => CssValue::color(50),
            'link_bg_color' => CssValue::color(100),
            'link_text_color' => CssValue::color(50),
            'button_bg_color' => CssValue::color(100),
            'button_text_color' => CssValue::color(50),
            'button_shape' => CssValue::keyword(50),
            'button_shadow' => CssValue::keyword(50),
            'header_bg_color' => ['nullable', 'string', 'max:50', fn ($attribute, $value, $fail) => preg_match(CssValue::COLOR, (string) $value) || preg_match(CssValue::GRADIENT, (string) $value) ? null : $fail('Use a colour or a gradient.')],
            'font_family' => CssValue::keyword(50),
            'block_shape' => CssValue::keyword(20),
            'block_shadow' => CssValue::keyword(20),
            'social_icon_color' => CssValue::color(20),
            'social_placement' => 'nullable|in:top,bottom',
            'social_links' => 'nullable|array',
            'social_links.*' => 'nullable|array',
            'social_links.*.platform' => 'nullable|string',
            'social_links.*.value' => 'nullable|string',
            'social_links.*.enabled' => 'nullable|boolean',
            'socials' => 'nullable|array',
            'socials_position' => 'nullable|in:top,bottom',
            'social_icons_position' => 'nullable|in:below_bio,bottom_page',
            'avatar_shape' => 'nullable|in:circle,rounded,square',
            'badge' => 'nullable|in:verified,star,crown,fire',
            'badge_color' => CssValue::color(20),
            'seo_title' => 'nullable|string|max:47',
            'seo_description' => 'nullable|string|max:160',
            'seo_noindex' => 'nullable|boolean',
            'custom_css' => 'nullable|string',
            'is_published' => 'nullable|boolean',
            'is_adult_content' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
            'show_in_directory' => 'nullable|boolean',
            'hide_branding' => 'nullable|boolean',
            'google_analytics_id' => 'nullable|string|max:50',
            'facebook_pixel_id' => 'nullable|string|max:50',
            'tiktok_pixel_id' => ['nullable', 'regex:/^[A-Z0-9]{20,30}$/'],
            'avatar' => 'nullable|image|max:2048',
            'avatar_url' => 'nullable|string|max:255',
            'password_enabled' => 'nullable|boolean',
            'password' => 'nullable|string|max:50',
            'allow_indexing' => 'nullable|boolean',
            'qr_settings' => 'nullable|array',
            'hover_effect' => CssValue::keyword(30),
            'background_animation' => CssValue::keyword(30),
        ]);
        $this->assertSafeContent($validated);
        if (! empty($validated['is_published']) && ! $bioPage->is_published && AbuseFlag::locks('bio_page', $bioPage->id)) {
            throw ValidationException::withMessages(['is_published' => 'This page was disabled by moderation. Contact support to appeal.']);
        }
        // The avatar path is set by uploads only; clients may clear it but not point it elsewhere.
        if (array_key_exists('avatar_url', $validated) && $validated['avatar_url'] !== null && $validated['avatar_url'] !== $bioPage->avatar_url) {
            unset($validated['avatar_url']);
        }
        $oldFiles = [$bioPage->avatar_url, $bioPage->qr_settings['image'] ?? null, $bioPage->qr_settings['logo_url'] ?? null];
        if ($request->hasFile('avatar')) {
            $validated['avatar_url'] = $request->file('avatar')->store('avatars', 'public');
        }
        // socials is cast to array on the model; encoding it here stored a JSON string inside JSON (L1).
        if (isset($validated['qr_settings'])) {
            $validated['qr_settings'] = $this->handleQrImage($validated['qr_settings'], $bioPage->qr_settings);
        }
        // Ensure theme is not null (database doesn't allow null)
        if (array_key_exists('theme', $validated) && $validated['theme'] === null) {
            $validated['theme'] = 'default';
        }
        // Hash bio-page password on write; never store plaintext. An empty
        // submission leaves the existing password untouched.
        if (array_key_exists('password', $validated)) {
            if (filled($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            } else {
                unset($validated['password']);
            }
        }
        $bioPage->update($validated);
        BioUploads::deleteUnused($oldFiles);
        // Return JSON for AJAX requests
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Bio page updated successfully!',
                'bioPage' => $bioPage->fresh(),
            ]);
        }

        return back()->with('success', 'Bio page updated successfully!');
    }

    public function destroy(BioPage $bioPage)
    {
        $this->authorize('delete', $bioPage);
        $files = BioUploads::ofPage($bioPage);
        $bioPage->delete();
        BioUploads::deleteUnused($files);

        return redirect()->route('bio.index')->with('success', 'Bio page deleted successfully!');
    }

    public function analytics(BioPage $bioPage)
    {
        $this->authorize('view', $bioPage);
        // Only the ranges the page offers; click logs are kept 30 days (privacy policy).
        $days = (int) request('days', 30);
        $days = in_array($days, self::ANALYTICS_RANGES, true) ? $days : 30;
        $clicksData = BioClick::query()
            ->whereIn('bio_link_id', $bioPage->links->pluck('id'))
            ->where('clicked_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(clicked_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        $viewsChartLabels = [];
        $viewsChartData = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $viewsChartLabels[] = now()->subDays($i)->format('M d');
            $clickCount = $clicksData->firstWhere('date', $date);
            $viewsChartData[] = $clickCount ? $clickCount->count : 0;
        }
        // reorder(): the relation sorts by position, which would win over click_count.
        $topLinks = $bioPage->links()
            ->reorder()
            ->where('is_active', true)
            ->orderByDesc('click_count')
            ->limit(10)
            ->get();
        $countriesData = BioClick::query()
            ->whereIn('bio_link_id', $bioPage->links->pluck('id'))
            ->where('clicked_at', '>=', now()->subDays($days))
            ->whereNotNull('country')
            ->selectRaw('country, COUNT(*) as clicks')
            ->groupBy('country')
            ->orderByDesc('clicks')
            ->limit(10)
            ->get();
        $totalCountryClicks = $countriesData->sum('clicks');
        $topCountries = $countriesData->map(function ($item) use ($totalCountryClicks) {
            $item->percentage = $totalCountryClicks > 0 ? round(($item->clicks / $totalCountryClicks) * 100, 1) : 0;
            $item->country_name = $item->country;
            $item->flag = $this->getCountryFlag($item->country);

            return $item;
        });
        $devicesData = BioClick::query()
            ->whereIn('bio_link_id', $bioPage->links->pluck('id'))
            ->where('clicked_at', '>=', now()->subDays($days))
            ->whereNotNull('device')
            ->selectRaw('device, COUNT(*) as count')
            ->groupBy('device')
            ->orderByDesc('count')
            ->get();
        $deviceChartLabels = $devicesData->pluck('device')->map(function ($device) {
            return ucfirst($device);
        })->toArray();
        $deviceChartData = $devicesData->pluck('count')->toArray();
        $recentClicks = BioClick::query()
            ->with('bioLink')
            ->whereIn('bio_link_id', $bioPage->links->pluck('id'))
            ->orderByDesc('clicked_at')
            ->limit(20)
            ->get();
        $totalClicks = $bioPage->getTotalClicks();
        $ctr = $bioPage->view_count > 0 ? round(($totalClicks / $bioPage->view_count) * 100, 2) : 0;
        $stats = [
            'totalViews' => $bioPage->view_count,
            'totalClicks' => $totalClicks,
            'ctr' => $ctr,
            'activeLinks' => $bioPage->links()->where('is_active', true)->count(),
            'viewsChart' => [
                'labels' => $viewsChartLabels,
                'data' => $viewsChartData,
            ],
            'deviceChart' => [
                'labels' => $deviceChartLabels,
                'data' => $deviceChartData,
            ],
            'topLinks' => $topLinks,
            'topCountries' => $topCountries,
            'recentClicks' => $recentClicks,
        ];

        return view('bio.analytics', compact('bioPage', 'stats', 'days'));
    }

    private function getCountryFlag($countryCode)
    {
        $flags = [
            'US' => '🇺🇸', 'ID' => '🇮🇩', 'GB' => '🇬🇧', 'CA' => '🇨🇦', 'AU' => '🇦🇺',
            'DE' => '🇩🇪', 'FR' => '🇫🇷', 'JP' => '🇯🇵', 'CN' => '🇨🇳', 'IN' => '🇮🇳',
            'SG' => '🇸🇬', 'MY' => '🇲🇾', 'TH' => '🇹🇭', 'PH' => '🇵🇭', 'VN' => '🇻🇳',
        ];

        return $flags[$countryCode] ?? '🌍';
    }

    public function duplicate(BioPage $bioPage)
    {
        $this->authorize('view', $bioPage);
        if (auth()->user()->bioPages()->count() >= self::MAX_PAGES) {
            return back()->withErrors(['slug' => 'You have reached the maximum limit of '.self::MAX_PAGES.' bio pages.']);
        }
        // Slugs are alphanumeric only (max 20), so no "-copy" suffix.
        // Trim the base so the suffix always fits; truncating the suffix instead
        // repeated the same slug forever for long slugs.
        $counter = 1;
        do {
            $suffix = 'copy'.($counter > 1 ? $counter : '');
            $newSlug = substr($bioPage->slug, 0, 20 - strlen($suffix)).$suffix;
            $counter++;
        } while (BioPage::where('slug', $newSlug)->exists());
        $newBioPage = $bioPage->replicate(['view_count']);
        $newBioPage->slug = $newSlug;
        $newBioPage->title = $bioPage->title.' (Copy)';
        $newBioPage->save();
        foreach ($bioPage->links as $link) {
            $newLink = $link->replicate(['click_count', 'public_id']);
            $newLink->bio_page_id = $newBioPage->id;
            $newLink->save();
        }

        return redirect()->route('bio.edit', $newBioPage)->with('success', 'Bio page duplicated successfully!');
    }

    /**
     * Upload image for bio page blocks
     */
    public function uploadImage(Request $request, BioPage $bioPage)
    {
        $this->authorize('update', $bioPage);
        $request->validate([
            'image' => 'required|image|max:5120', // 5MB max
        ]);
        try {
            $path = $request->file('image')->store('bio-images', 'public');

            return response()->json([
                'success' => true,
                'path' => $path,
                'url' => '/storage/'.$path,
                'image_url' => '/storage/'.$path,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload avatar for bio page
     */
    public function uploadAvatar(Request $request, BioPage $bioPage)
    {
        $this->authorize('update', $bioPage);
        $request->validate([
            'avatar' => 'required|image|max:2048', // 2MB max
        ]);
        try {
            $oldAvatar = $bioPage->avatar_url;
            $path = $request->file('avatar')->store('avatars', 'public');
            $bioPage->update(['avatar_url' => $path]);
            BioUploads::deleteIfUnused($oldAvatar);

            return response()->json([
                'success' => true,
                'avatar_url' => $path, // Return just the path
                'full_url' => '/storage/'.$path,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload avatar: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload thumbnail for bio link block
     */
    public function uploadThumbnail(Request $request, BioPage $bioPage)
    {
        $this->authorize('update', $bioPage);
        $request->validate([
            'thumbnail' => 'required|image|max:1024', // 1MB max
        ]);
        try {
            $path = $request->file('thumbnail')->store('bio-thumbnails', 'public');

            return response()->json([
                'success' => true,
                'thumbnail_url' => '/storage/'.$path,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload thumbnail: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload custom icon for bio link block
     */
    public function uploadIcon(Request $request, BioPage $bioPage)
    {
        $this->authorize('update', $bioPage);
        $request->validate([
            'icon' => 'required|image|max:1024', // 1MB max
        ]);
        try {
            $path = $request->file('icon')->store('bio-icons', 'public');

            return response()->json([
                'success' => true,
                'icon_url' => '/storage/'.$path,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload icon: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload QR code logo
     */
    public function uploadQrLogo(Request $request, BioPage $bioPage)
    {
        $this->authorize('update', $bioPage);
        $request->validate([
            'logo' => 'required|image|max:1024', // 1MB max
        ]);
        try {
            $oldLogo = $bioPage->qr_settings['logo_url'] ?? null;
            $path = $request->file('logo')->store('qr-logos', 'public');
            $qrSettings = $bioPage->qr_settings ?? [];
            $qrSettings['logo_url'] = '/storage/'.$path;
            $bioPage->update(['qr_settings' => $qrSettings]);
            BioUploads::deleteIfUnused($oldLogo);

            return response()->json([
                'success' => true,
                'logo_url' => '/storage/'.$path,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload QR logo: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview bio page (for iframe in editor)
     */
    public function preview(BioPage $bioPage)
    {
        $this->authorize('view', $bioPage);
        $bioPage->load(['links' => function ($query) {
            $query->orderBy('order');
        }]);

        return view('bio.editor.preview', compact('bioPage'));
    }
}
