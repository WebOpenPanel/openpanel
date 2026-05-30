@extends('layouts.app')
@section('title', $domain->domain)
@section('content')
<div class="max-w-2xl space-y-4">
    <div class="flex items-center gap-3">
        <a href="{{ route('domains.index') }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
        <h2 class="text-lg font-bold text-gray-900">{{ $domain->domain }}</h2>
        <span class="px-2 py-0.5 text-xs bg-gray-100 rounded-full">{{ $domain->type }}</span>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <form method="POST" action="{{ route('domains.update', $domain) }}" class="space-y-4">
            @csrf @method('PUT')
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Document Root</label>
                <input type="text" name="document_root" value="{{ old('document_root', $domain->document_root) }}" class="w-full px-3 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Redirect Type</label>
                    <select name="redirect_type" class="w-full px-3 py-2.5 border rounded-lg text-sm">
                        <option value="none" {{ $domain->redirect_type=='none'?'selected':'' }}>None</option>
                        <option value="301" {{ $domain->redirect_type=='301'?'selected':'' }}>301 Permanent</option>
                        <option value="302" {{ $domain->redirect_type=='302'?'selected':'' }}>302 Temporary</option>
                    </select>
                </div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Redirect URL</label>
                    <input type="text" name="redirect_url" value="{{ old('redirect_url', $domain->redirect_url) }}" class="w-full px-3 py-2.5 border rounded-lg text-sm">
                </div>
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Custom VHost Config</label>
                <textarea name="custom_vhost_config" rows="6" class="w-full px-3 py-2.5 border rounded-lg text-sm font-mono">{{ old('custom_vhost_config', $domain->custom_vhost_config) }}</textarea>
            </div>
            <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700"><i class="fas fa-save mr-2"></i> Update</button>
        </form>
    </div>
</div>
@endsection
