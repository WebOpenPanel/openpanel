@extends('layouts.app')
@section('title', 'Quota Report')
@section('content')
<div class="space-y-4">
    <h2 class="text-lg font-semibold">Disk Quota Report</h2>
    <pre class="bg-gray-900 text-green-400 p-4 rounded text-xs overflow-auto">{{ $report }}</pre>
</div>
@endsection
