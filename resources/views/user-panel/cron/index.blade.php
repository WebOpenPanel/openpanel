@extends('user-layouts.app')

@section('title', 'Cron Jobs')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Cron Jobs</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Schedule</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Command</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($jobs as $job)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-mono text-gray-800">{{ $job['minute'] }} {{ $job['hour'] }} {{ $job['day'] }} {{ $job['month'] }} {{ $job['weekday'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600 font-mono truncate max-w-md">{{ $job['command'] }}</td>
                            <td class="px-6 py-4">
                                <form method="POST" action="{{ route('user.cron.destroy') }}" onsubmit="return confirm('Delete this cron job?')">
                                    @csrf
                                    <input type="hidden" name="line" value="{{ $job['line'] }}">
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm"><i class="fas fa-trash mr-1"></i>Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-8 text-center text-gray-500">No cron jobs found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Add Cron Job</h3>
        <form method="POST" action="{{ route('user.cron.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-5 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Minute</label>
                    <input type="text" name="minute" value="*" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm font-mono" required>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Hour</label>
                    <input type="text" name="hour" value="*" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm font-mono" required>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Day</label>
                    <input type="text" name="day" value="*" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm font-mono" required>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Month</label>
                    <input type="text" name="month" value="*" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm font-mono" required>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Weekday</label>
                    <input type="text" name="weekday" value="*" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm font-mono" required>
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Command</label>
                <input type="text" name="command" placeholder="/usr/bin/php /home/user/script.php" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 font-mono text-sm" required>
            </div>
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">Add Cron Job</button>
        </form>
    </div>
</div>
@endsection
