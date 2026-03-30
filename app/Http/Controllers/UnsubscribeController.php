<?php

namespace App\Http\Controllers;

use App\Models\EmailUnsubscribe;
use Illuminate\Http\Request;

class UnsubscribeController extends Controller
{
    /**
     * Public API: Unsubscribe an email from marketing emails.
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($request->email));
        EmailUnsubscribe::unsubscribe($email);

        return response()->json([
            'message' => 'You have been unsubscribed from marketing emails.',
        ]);
    }

    /**
     * Public API: Check if email is unsubscribed (for frontend confirmation).
     */
    public function show(Request $request)
    {
        $email = $request->query('email');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['unsubscribed' => false]);
        }
        return response()->json([
            'unsubscribed' => EmailUnsubscribe::isUnsubscribed($email),
        ]);
    }
}
