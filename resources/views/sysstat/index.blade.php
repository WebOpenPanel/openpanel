@extends('layouts.app')
@section('title', 'SysStat / SAR')
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-chart-bar mr-2"></i>SysStat / SAR</h1>
    @if(session('success'))<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{!! nl2br(e(session('success'))) !!}</div>@endif
    @if(session('error'))<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{!! nl2br(e(session('error'))) !!}</div>@endif

    @if(!$installed)
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <p class="text-yellow-800">sysstat is not installed. Required for system activity reports.</p>
        <form action="{{ route('sysstat.install') }}" method="POST" class="mt-3">@csrf<button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">Install sysstat</button></form>
    </div>
    @else
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">System Reports</h2>
        <div class="flex gap-4 flex-wrap">
            <a href="{{ route('sysstat.report', ['type' => 'cpu']) }}" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">CPU Report</a>
            <a href="{{ route('sysstat.report', ['type' => 'memory']) }}" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">Memory Report</a>
            <a href="{{ route('sysstat.report', ['type' => 'disk']) }}" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">Disk Report</a>
            <a href="{{ route('sysstat.report', ['type' => 'network']) }}" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">Network Report</a>
        </div>
    </div>
    @if($stats)
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Recent Stats</h2>
        <pre class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-auto text-sm font-mono" style="max-height:400px">{{ $stats }}</pre>
    </div>
    @endif
    @endif
</div>
@endsection
