<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * The guest QR page (/qr): Wi-Fi, contact card and link/text codes, drawn in the browser with the vendored
 * qr-code-styling. There is no POST route and the page reads no query data: a Wi-Fi password or a contact
 * detail typed there never reaches the server or its logs.
 */
class QrStudioController extends Controller
{
    public function __invoke(): View
    {
        return view('qr');
    }
}
