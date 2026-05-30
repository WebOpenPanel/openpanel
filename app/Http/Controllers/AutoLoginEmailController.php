<?php

namespace App\Http\Controllers;

use App\Services\AutoLoginEmailService;
use Illuminate\Http\Request;

class AutoLoginEmailController extends Controller
{
    public function webmailLogin(Request $request)
    {
        $username = $request->input('username', '');
        if (empty($username)) return back()->with('error', 'Username required');
        $result = AutoLoginEmailService::generateAutoLoginToken($username);
        $webmailUrl = AutoLoginEmailService::getWebmailUrl();
        return redirect($webmailUrl . '?_autologin=1&sess=' . $result['token']);
    }
}
