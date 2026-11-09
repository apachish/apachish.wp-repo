<div dir="{{ in_array(app()->getLocale(), ['fa', 'ar', 'he']) ? 'rtl' : 'ltr' }}"
     class="mx-auto max-w-6xl space-y-5 p-6 text-slate-800 dark:text-slate-100">
    @include('wp-repo::partials.nav', ['active' => 'packages'])

    @if ($message)
        <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950 dark:text-emerald-200">{{ $message }}</div>
    @endif

    <div class="flex items-start justify-between gap-3">
        <div>
            <a href="{{ route('wp-repo.panel.packages',['api_key' =>  data_get($project,"api_key")]) }}" wire:navigate class="text-xs text-slate-500 hover:underline">← {{ __('wp-repo::messages.back') }}</a>
            <h1 class="text-xl font-semibold">{{ $package->name }}</h1>
            <p class="text-xs text-slate-500" dir="ltr">{{ $package->slug }} · {{ $package->repo }} ({{ $package->git_provider }})</p>
        </div>
        <button wire:click="sync" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">{{ __('wp-repo::messages.sync') }}</button>
    </div>

    <h2 class="text-base font-semibold">{{ __('wp-repo::messages.versions') }}</h2>

    <div class="space-y-3">
        @forelse ($versions as $version)
            <div wire:key="ver-{{ $version->id }}" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-sm font-semibold" dir="ltr">{{ $version->version }}</span>
                        @if ($version->is_stable)
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">{{ __('wp-repo::messages.stable') }}</span>
                        @endif
                        <span class="text-xs text-slate-500">{{ optional($version->released_at)->format('Y-m-d H:i') }}</span>
                    </div>
                    <div class="space-x-3 text-sm rtl:space-x-reverse">
                        <button wire:click="setStable({{ $version->id }}, {{ $version->is_stable ? 'false' : 'true' }})" class="hover:underline">{{ __('wp-repo::messages.stable') }}: {{ $version->is_stable ? '✓' : '✗' }}</button>
                        <button wire:click="deleteVersion({{ $version->id }})" wire:confirm="{{ __('wp-repo::messages.confirm') }}" class="text-red-600 hover:underline">{{ __('wp-repo::messages.delete') }}</button>
                    </div>
                </div>
                <p class="mt-1 break-all font-mono text-xs text-slate-500" dir="ltr">sha256 {{ $version->checksum }}</p>
                <p class="mt-1 text-xs text-slate-500" dir="ltr">WP ≥ {{ $version->requires_wp ?? '—' }} · tested {{ $version->tested_wp ?? '—' }} · PHP ≥ {{ $version->requires_php ?? '—' }}</p>
                @if ($version->changelog)
                    <details class="mt-2 text-sm">
                        <summary class="cursor-pointer text-indigo-600 dark:text-indigo-400">{{ __('wp-repo::messages.changelog') }}</summary>
                        <pre class="mt-2 max-h-56 overflow-auto whitespace-pre-wrap rounded-md bg-slate-50 p-3 text-xs dark:bg-slate-950" dir="ltr">{{ $version->changelog }}</pre>
                    </details>
                @endif
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-slate-500 dark:border-slate-700">{{ __('wp-repo::messages.none') }}</p>
        @endforelse
    </div>
</div>
