<?php

namespace App\Http\Controllers;

use App\Services\ThemeService;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function index()
    {
        $currentTheme = ThemeService::getCurrentTheme();
        $currentLang = ThemeService::getCurrentLanguage();
        $themes = ThemeService::listThemes();
        $languages = ThemeService::listLanguages();
        return view('themes.index', compact('currentTheme', 'currentLang', 'themes', 'languages'));
    }

    public function setTheme(Request $request)
    {
        $request->validate(['theme' => 'required|string']);
        ThemeService::setTheme($request->theme);
        return back()->with('success', "Theme set to '{$request->theme}'.");
    }

    public function setLanguage(Request $request)
    {
        $request->validate(['language' => 'required|string']);
        ThemeService::setLanguage($request->language);
        return back()->with('success', "Language set to '{$request->language}'.");
    }

    public function editLanguage(Request $request)
    {
        $request->validate(['lang' => 'required|string']);
        $content = ThemeService::getLanguageContent($request->lang);
        return view('themes.edit_lang', ['lang' => $request->lang, 'content' => $content]);
    }

    public function saveLanguage(Request $request)
    {
        $request->validate(['lang' => 'required|string', 'content' => 'required|string']);
        ThemeService::saveLanguageContent($request->lang, $request->content);
        return back()->with('success', "Language '{$request->lang}' saved.");
    }
}
