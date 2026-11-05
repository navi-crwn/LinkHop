<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Support\EncryptedNoteLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LinkCommentController extends Controller
{
    public function store(Request $request, Link $link): RedirectResponse
    {
        abort_unless($link->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:500', EncryptedNoteLink::rule()],
        ]);
        $link->comments()->create([
            'body' => $data['body'],
            'user_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Comment added successfully.');
    }
}
