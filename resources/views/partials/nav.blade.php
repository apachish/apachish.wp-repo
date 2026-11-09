@php
    $tabs = [
        'wp-repo.panel.packages' => 'packages',
        'wp-repo.panel.connections' => 'connections',
        'wp-repo.panel.sites' => 'sites',
        'wp-repo.panel.logs' => 'logs',
    ];
@endphp
<nav class="flex flex-wrap gap-2 border-b border-slate-200 pb-3 dark:border-slate-700">
    @foreach ($tabs as $route => $key)
        <a href="{{ route($route,['api_key' =>  data_get($project,"api_key")]) }}" wire:navigate
           class="rounded-md px-3 py-1.5 text-sm font-medium {{ ($active ?? '') === $key ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
            {{ __('wp-repo::messages.'.$key) }}
        </a>
    @endforeach
</nav>
