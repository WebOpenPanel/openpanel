@extends('layouts.app')
@section('title', 'Live Monitor')
@section('content')
<div class="space-y-6">
    <h2 class="text-lg font-semibold">Live System Monitor</h2>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4" id="metrics">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500 uppercase">CPU Load</p>
            <p class="text-2xl font-bold" id="cpu">-</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500 uppercase">Memory</p>
            <p class="text-2xl font-bold" id="mem">-</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500 uppercase">Disk</p>
            <p class="text-2xl font-bold" id="disk">-</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500 uppercase">Connections</p>
            <p class="text-2xl font-bold" id="conn">-</p>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <p class="text-sm text-gray-600" id="uptime">-</p>
    </div>
</div>
<script>
function refresh() {
    fetch('{{ route("live-monitor.data") }}')
        .then(r => r.json())
        .then(d => {
            document.getElementById('cpu').textContent = d.cpu.toFixed(2);
            document.getElementById('mem').textContent = d.memory.percent + '%';
            document.getElementById('disk').textContent = d.disk.percent + '%';
            document.getElementById('conn').textContent = d.connections;
            document.getElementById('uptime').textContent = d.uptime;
        });
}
refresh();
setInterval(refresh, 3000);
</script>
@endsection
