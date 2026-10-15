<?php

namespace App\Http\Controllers;

use App\Models\DomainBlacklist;
use Illuminate\Http\Request;

class AdminDomainBlacklistController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('q')->trim()->toString();
        $domains = DomainBlacklist::query()
            ->when($search !== '', fn ($q) => $q->where('domain', 'like', "%{$search}%"))
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();
        $categories = [
            'phishing' => 'Phishing',
            'porn' => 'Pornography',
            'malware' => 'Malware',
            'spam' => 'Spam',
            'scam' => 'Scam',
            'illegal' => 'Illegal Content',
            'violence' => 'Violence',
            'hate' => 'Hate Speech',
            'other' => 'Other',
        ];

        return view('admin-domain-blacklist', compact('domains', 'categories', 'search'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'domain' => 'required|string',
            'match_type' => 'required|in:exact,wildcard',
            'category' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);
        $domains = array_filter(array_map('trim', explode("\n", $request->domain)));
        $addedCount = 0;
        $errors = [];
        foreach ($domains as $domainInput) {
            $domain = strtolower($domainInput);
            $domain = preg_replace('/^https?:\/\//', '', $domain);
            $domain = preg_replace('/^www\./', '', $domain);
            $domain = rtrim($domain, '/');
            $existing = DomainBlacklist::where('domain', $domain)->first();
            if ($existing) {
                $errors[] = "Already blacklisted: {$domain}";

                continue;
            }
            DomainBlacklist::create([
                'domain' => $domain,
                'match_type' => $request->match_type,
                'category' => $request->category,
                'notes' => $request->notes,
            ]);
            $addedCount++;
        }
        if ($addedCount > 0 && count($errors) === 0) {
            return back()->with('status', "{$addedCount} domain(s) added to blacklist successfully.");
        } elseif ($addedCount > 0 && count($errors) > 0) {
            return back()->with('status', "{$addedCount} domain(s) added. Errors: ".implode(', ', $errors));
        } else {
            return back()->withErrors(['domain' => implode(', ', $errors)]);
        }
    }

    public function destroy(DomainBlacklist $blacklist)
    {
        $blacklist->delete();

        return back()->with('status', 'Domain removed from blacklist.');
    }
}
