@extends('user-layouts.app')

@section('title', 'phpMyAdmin')

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
    <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-database text-orange-600 text-2xl"></i>
    </div>
    <h3 class="text-lg font-semibold text-gray-800 mb-2">phpMyAdmin</h3>
    <p class="text-gray-600 mb-6">Access phpMyAdmin to manage your databases directly.</p>
    <a href="{{ $pmaUrl }}" target="_blank" class="inline-flex items-center px-6 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
        <i class="fas fa-external-link-alt mr-2"></i>Open phpMyAdmin
    </a>
</div>
@endsection
