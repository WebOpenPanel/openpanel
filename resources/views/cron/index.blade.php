@extends('layouts.app')
@section('title', 'Cron Jobs')
@section('content')
<div class="space-y-4">
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-clock mr-2 text-indigo-500"></i>Add Cron Job</h3>
        <form method="POST" action="{{ route('cron.store') }}" class="space-y-3">
            @csrf
            <div class="flex flex-wrap gap-2">
                <div><label class="text-xs text-gray-500">Minute</label><input type="text" name="minute" value="*" class="block w-16 px-2 py-1.5 border rounded text-sm text-center"></div>
                <div><label class="text-xs text-gray-500">Hour</label><input type="text" name="hour" value="*" class="block w-16 px-2 py-1.5 border rounded text-sm text-center"></div>
                <div><label class="text-xs text-gray-500">Day</label><input type="text" name="day_of_month" value="*" class="block w-16 px-2 py-1.5 border rounded text-sm text-center"></div>
                <div><label class="text-xs text-gray-500">Month</label><input type="text" name="month" value="*" class="block w-16 px-2 py-1.5 border rounded text-sm text-center"></div>
                <div><label class="text-xs text-gray-500">Weekday</label><input type="text" name="day_of_week" value="*" class="block w-16 px-2 py-1.5 border rounded text-sm text-center"></div>
            </div>
            <div class="flex gap-3">
                <input type="text" name="command" required placeholder="/usr/bin/php /path/to/script.php" class="flex-1 px-3 py-2 border rounded-lg text-sm">
                <input type="text" name="comment" placeholder="Comment" class="w-48 px-3 py-2 border rounded-lg text-sm">
                <input type="text" name="user" value="root" class="w-24 px-3 py-2 border rounded-lg text-sm">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700"><i class="fas fa-plus mr-1"></i> Add</button>
            </div>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b"><tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Schedule</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Command</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">User</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($cronJobs as $job)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-2.5 text-sm font-mono text-gray-800">{{ $job->schedule }}</td>
                    <td class="px-5 py-2.5 text-sm text-gray-700 max-w-md truncate" title="{{ $job->command }}">{{ $job->command }}</td>
                    <td class="px-5 py-2.5 text-sm text-gray-600">{{ $job->user }}</td>
                    <td class="px-5 py-2.5">
                        <form method="POST" action="{{ route('cron.toggle', $job) }}" class="inline">@csrf
                            @if($job->enabled)
                                <button class="px-2 py-0.5 text-xs bg-green-100 text-green-800 rounded-full hover:bg-green-200">Enabled</button>
                            @else
                                <button class="px-2 py-0.5 text-xs bg-red-100 text-red-800 rounded-full hover:bg-red-200">Disabled</button>
                            @endif
                        </form>
                    </td>
                    <td class="px-5 py-2.5 text-right">
                        <form method="POST" action="{{ route('cron.destroy', $job) }}" onsubmit="return confirm('Delete?')" class="inline">@csrf @method('DELETE')
                            <button class="p-1.5 text-gray-400 hover:text-red-600"><i class="fas fa-trash text-sm"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-gray-400">No cron jobs.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $cronJobs->links() }}</div>
</div>
@endsection
