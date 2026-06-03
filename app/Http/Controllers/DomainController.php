<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Subdomain;
use App\Models\DomainAlias;
use App\Models\UserAccount;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index(Request $request)
    {
        $query = Domain::with('userAccount');

        if ($request->filled('search')) {
            $query->where('domain', 'like', "%{$request->search}%");
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $domains = $query->latest()->paginate(20);
        return view('domains.index', compact('domains'));
    }

    public function create()
    {
        $accounts = UserAccount::where('suspended', 'no')->orderBy('domain')->get();
        return view('domains.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_account_id' => 'required|exists:user_accounts,id',
            'domain' => 'required|string|max:255|unique:domains,domain',
            'type' => 'required|in:addon,parked,subdomain',
            'document_root' => 'nullable|string',
        ]);

        $account = UserAccount::find($request->user_account_id);

        Domain::create([
            'user_account_id' => $account->id,
            'domain' => $request->domain,
            'type' => $request->type,
            'document_root' => $request->document_root ?? "/home/{$account->user->username}/domains/{$request->domain}",
            'ip_address' => $account->ip_address,
        ]);

        return redirect()->route('domains.index')
            ->with('success', "Domain '{$request->domain}' added successfully.");
    }

    public function show(Domain $domain)
    {
        $domain->load('userAccount');
        return view('domains.show', compact('domain'));
    }

    public function edit(Domain $domain)
    {
        return view('domains.edit', compact('domain'));
    }

    public function update(Request $request, Domain $domain)
    {
        $request->validate([
            'document_root' => 'nullable|string',
            'redirect_type' => 'nullable|in:none,301,302',
            'redirect_url' => 'nullable|url',
        ]);

        $domain->update($request->only([
            'document_root', 'redirect_type', 'redirect_url', 'custom_vhost_config',
        ]));

        return redirect()->route('domains.show', $domain)
            ->with('success', 'Domain updated successfully.');
    }

    public function destroy(Domain $domain)
    {
        $domain->delete();
        return redirect()->route('domains.index')
            ->with('success', 'Domain deleted successfully.');
    }

    public function subdomains()
    {
        $subdomains = Subdomain::with('userAccount')->latest()->paginate(20);
        return view('domains.subdomains', compact('subdomains'));
    }

    public function destroySubdomain(Subdomain $subdomain)
    {
        $subdomain->delete();
        return redirect()->route('subdomains')
            ->with('success', 'Subdomain deleted.');
    }

    public function aliases()
    {
        $aliases = DomainAlias::with('userAccount')->latest()->paginate(20);
        return view('domains.aliases', compact('aliases'));
    }

    public function destroyAlias(DomainAlias $alias)
    {
        $alias->delete();
        return redirect()->route('domain-aliases')
            ->with('success', 'Domain alias deleted.');
    }
}
