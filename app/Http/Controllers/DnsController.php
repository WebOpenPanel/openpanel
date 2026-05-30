<?php

namespace App\Http\Controllers;

use App\Models\DnsZone;
use App\Models\DnsRecord;
use App\Services\DnsService;
use Illuminate\Http\Request;

class DnsController extends Controller
{
    public function index(Request $request)
    {
        $query = DnsZone::withCount('records');
        if ($request->filled('search')) {
            $query->where('domain', 'like', "%{$request->search}%");
        }
        $zones = $query->latest()->paginate(20);
        $serverZones = DnsService::listZones();
        return view('dns.index', compact('zones', 'serverZones'));
    }

    public function create()
    {
        $nameservers = DnsService::getNameservers();
        return view('dns.create', compact('nameservers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|max:255|unique:dns_zones,domain',
            'ip' => 'nullable|ip',
        ]);

        $ns = DnsService::getNameservers();
        $ip = $request->ip ?? $ns['ns1_ip'];
        DnsService::addZoneForce($request->domain, $ip, 'admin@' . $request->domain);

        $zone = DnsZone::create($request->all());
        DnsRecord::create(['dns_zone_id' => $zone->id, 'name' => '@', 'type' => 'NS', 'value' => $ns['ns1'], 'ttl' => 14400]);
        DnsRecord::create(['dns_zone_id' => $zone->id, 'name' => '@', 'type' => 'NS', 'value' => $ns['ns2'], 'ttl' => 14400]);
        DnsRecord::create(['dns_zone_id' => $zone->id, 'name' => '@', 'type' => 'A', 'value' => $ip, 'ttl' => 14400]);

        return redirect()->route('dns.show', $zone)->with('success', "DNS zone for '{$zone->domain}' created.");
    }

    public function show(DnsZone $zone)
    {
        $records = $zone->records()->orderBy('type')->orderBy('name')->get();
        $zoneRecords = DnsService::getZoneRecords($zone->domain);
        return view('dns.show', compact('zone', 'records', 'zoneRecords'));
    }

    public function destroy(DnsZone $zone)
    {
        DnsService::deleteZone($zone->domain);
        $zone->records()->delete();
        $zone->delete();
        return redirect()->route('dns.index')->with('success', 'DNS zone deleted.');
    }

    public function addRecord(Request $request, DnsZone $zone)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:A,AAAA,CNAME,MX,TXT,NS,SRV,CAA,PTR',
            'value' => 'required|string',
            'ttl' => 'nullable|integer|min:60',
            'priority' => 'nullable|integer',
        ]);

        DnsRecord::create([
            'dns_zone_id' => $zone->id,
            'name' => $request->name,
            'type' => $request->type,
            'value' => $request->value,
            'ttl' => $request->ttl ?? 14400,
            'priority' => $request->priority,
        ]);

        DnsService::updateSerial($zone->domain);
        $zone->increment('serial');
        return back()->with('success', 'DNS record added.');
    }

    public function deleteRecord(DnsRecord $record)
    {
        $zone = $record->zone;
        $record->delete();
        DnsService::updateSerial($zone->domain);
        $zone->increment('serial');
        return back()->with('success', 'DNS record deleted.');
    }

    public function editRecord(Request $request, DnsRecord $record)
    {
        $request->validate([
            'value' => 'required|string',
            'ttl' => 'nullable|integer|min:60',
            'priority' => 'nullable|integer',
        ]);
        $record->update($request->only(['value', 'ttl', 'priority']));
        DnsService::updateSerial($record->zone->domain);
        $record->zone->increment('serial');
        return back()->with('success', 'DNS record updated.');
    }

    public function rebuildAll()
    {
        DnsService::rebuildAllZones();
        return back()->with('success', 'All DNS zones rebuilt.');
    }

    public function rebuildZone(DnsZone $zone)
    {
        DnsService::rebuildZone($zone->user ?? 'admin', $zone->domain);
        return back()->with('success', "Zone {$zone->domain} rebuilt.");
    }

    public function templates()
    {
        $templates = DnsService::getZoneTemplates();
        return view('dns.templates', compact('templates'));
    }

    public function nameservers()
    {
        $nameservers = DnsService::getNameservers();
        return view('dns.nameservers', compact('nameservers'));
    }

    public function saveNameservers(Request $request)
    {
        $request->validate([
            'ns1' => 'required|string',
            'ns2' => 'required|string',
            'ns1_ip' => 'required|ip',
            'ns2_ip' => 'required|ip',
        ]);
        DnsService::setNameservers($request->ns1, $request->ns2, $request->ns1_ip, $request->ns2_ip);
        return back()->with('success', 'Nameservers updated.');
    }

    public function addDkim(string $domain)
    {
        DnsService::addDkim($domain);
        return back()->with('success', "DKIM added for {$domain}.");
    }

    public function addDkimAll()
    {
        $results = DnsService::addDkimAll();
        return back()->with('success', 'DKIM added for all domains.');
    }

    public function addSpf(string $domain, Request $request)
    {
        $ip = $request->ip ?? file_get_contents('https://api.ipify.org') ?? '127.0.0.1';
        DnsService::addSpf($domain, $ip);
        return back()->with('success', "SPF record added for {$domain}.");
    }
}
