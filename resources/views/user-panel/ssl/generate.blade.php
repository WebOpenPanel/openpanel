@extends('user-layouts.app')

@section('title', "Let's Encrypt")

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4"><i class="fas fa-lock mr-2 text-green-600"></i>Generate SSL Certificate</h3>
    <form method="POST" action="{{ route('user.ssl.request') }}" class="max-w-md">
        @csrf
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Select Domain</label>
            <select name="domain" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @foreach($domains as $d)
                    <option value="{{ $d }}">{{ $d }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
            <i class="fas fa-certificate mr-2"></i>Generate Let's Encrypt SSL
        </button>
    </form>
</div>
@endsection
