{{-- Include in your sidebar: @include('wp-repo::partials.sidebar') --}}
@can('manage-wp-repo')
    <a href="{{ route('wp-repo.panel.packages') }}" wire:navigate
       class="flex items-center gap-2 rounded-md px-3 py-2 text-sm {{ request()->routeIs('wp-repo.panel.*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-white/10' }}">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
        <span>{{ __('wp-repo::messages.title') }}</span>
    </a>
@endcan
