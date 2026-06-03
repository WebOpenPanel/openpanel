@extends('layouts.app')
@section('title', 'Terminal')
@section('content')
<div class="space-y-4">
    <h2 class="text-lg font-semibold">Web Terminal</h2>
    <div class="bg-gray-900 rounded-lg p-4">
        <div id="output" class="font-mono text-sm text-green-400 h-96 overflow-auto whitespace-pre-wrap">Welcome to OpenPanel Terminal\n$ </div>
        <div class="flex mt-2">
            <span class="text-green-400 font-mono mr-2">$</span>
            <input id="cmd" type="text" class="flex-1 bg-transparent text-green-400 font-mono text-sm outline-none" autofocus autocomplete="off">
        </div>
    </div>
</div>
<script>
document.getElementById('cmd').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        const cmd = this.value.trim();
        if (!cmd) return;
        const output = document.getElementById('output');
        output.textContent += cmd + '\n';
        this.value = '';
        fetch('{{ route("terminal.execute") }}', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
            body: JSON.stringify({command: cmd})
        }).then(r => r.json()).then(data => {
            output.textContent += data.output + '\n$ ';
            output.scrollTop = output.scrollHeight;
        });
    }
});
</script>
@endsection
