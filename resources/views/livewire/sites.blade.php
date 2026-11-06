<div dir="{{ in_array(app()->getLocale(), ['fa', 'ar', 'he']) ? 'rtl' : 'ltr' }}"
     class="mx-auto max-w-6xl space-y-5 p-6 text-slate-800 dark:text-slate-100">
    @include('wp-repo::partials.nav', ['active' => 'sites'])

    @if ($message)
        <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950 dark:text-emerald-200">{{ $message }}</div>
    @endif

    @if ($newKey)
        <div class="space-y-2 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm dark:border-amber-700 dark:bg-amber-950">
            <p class="font-medium">{{ __('wp-repo::messages.api_key') }} — {{ __('wp-repo::messages.api_key_once') }}</p>
            <code class="block break-all rounded bg-white px-3 py-2 font-mono text-xs dark:bg-slate-900" dir="ltr">{{ $newKey }}</code>
            <button wire:click="$set('newKey', null)" class="text-xs underline">{{ __('wp-repo::messages.cancel') }}</button>
        </div>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-3">
        <form wire:submit="assign" class="flex flex-wrap items-end gap-2 text-sm">
            <label class="space-y-1">
                <span>{{ __('wp-repo::messages.site') }}</span>
                <select wire:model="assignSite" class="rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-900">
                    <option value="">—</option>
                    @foreach ($allSites as $s) <option value="{{ $s->id }}">{{ $s->domain }}</option> @endforeach
                </select>
            </label>
            <label class="space-y-1">
                <span>{{ __('wp-repo::messages.package') }}</span>
                <select wire:model="assignPackage" class="rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-900">
                    <option value="">—</option>
                    @foreach ($allPackages as $p) <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->slug }})</option> @endforeach
                </select>
            </label>
            <button class="rounded-md border border-slate-300 px-3 py-2 dark:border-slate-600">{{ __('wp-repo::messages.assign_package') }}</button>
        </form>

        <button wire:click="$toggle('showForm')" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">{{ __('wp-repo::messages.add_site') }}</button>
    </div>

    @if ($showForm)
        <form wire:submit="createSite" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2 dark:border-slate-700 dark:bg-slate-900">
            <label class="space-y-1 text-sm">
                <span>{{ __('wp-repo::messages.domain') }}</span>
                <input wire:model="domain" type="text" dir="ltr" placeholder="example.com" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
                @error('domain') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </label>
            <label class="space-y-1 text-sm">
                <span>{{ __('wp-repo::messages.wp_path') }}</span>
                <input wire:model="wp_path" type="text" dir="ltr" placeholder="/var/www/sites/example" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
                @error('wp_path') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </label>
            <div class="sm:col-span-2">
                <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">{{ __('wp-repo::messages.save') }}</button>
            </div>
        </form>
    @endif

    @forelse ($sites as $site)
        <section wire:key="site-{{ $site->id }}" class="rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
            <header class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                <div>
                    <h3 class="font-semibold" dir="ltr">{{ $site->domain }}</h3>
                    <p class="text-xs text-slate-500" dir="ltr">
                        WP {{ $site->wp_version ?? '—' }} · PHP {{ $site->php_version ?? '—' }} ·
                        {{ __('wp-repo::messages.last_check') }}: {{ $site->last_check_at?->diffForHumans() ?? __('wp-repo::messages.never') }}
                    </p>
                </div>
                <div class="space-x-3 text-sm rtl:space-x-reverse">
                    <button wire:click="updateAll({{ $site->id }})" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ __('wp-repo::messages.update_all') }}</button>
                    <button wire:click="regenerateKey({{ $site->id }})" wire:confirm="{{ __('wp-repo::messages.confirm') }}" class="hover:underline">{{ __('wp-repo::messages.regenerate') }}</button>
                    <button wire:click="deleteSite({{ $site->id }})" wire:confirm="{{ __('wp-repo::messages.confirm') }}" class="text-red-600 hover:underline">{{ __('wp-repo::messages.delete') }}</button>
                </div>
            </header>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($site->sitePackages as $sp)
                            <tr wire:key="sp-{{ $sp->id }}">
                                <td class="px-4 py-2">
                                    <div class="font-medium">{{ $sp->package->name }}</div>
                                    <div class="text-xs text-slate-500" dir="ltr">{{ $sp->package->slug }}</div>
                                </td>
                                <td class="px-4 py-2" dir="ltr">
                                    {{ __('wp-repo::messages.installed') }}: {{ $sp->installed_version ?? '—' }}
                                    <span class="ms-2 rounded-full px-2 py-0.5 text-xs {{ ['ok' => 'bg-emerald-100 text-emerald-800', 'failed' => 'bg-red-100 text-red-800', 'pending' => 'bg-slate-200 text-slate-700'][$sp->status] ?? 'bg-slate-200 text-slate-700' }}" @if ($sp->last_error) title="{{ $sp->last_error }}" @endif>{{ $sp->status }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-1" dir="ltr">
                                        <input wire:model="pins.{{ $sp->id }}" type="text" placeholder="{{ $sp->target_version ?? __('wp-repo::messages.target_version') }}"
                                               class="w-28 rounded-md border border-slate-300 bg-white px-2 py-1 text-xs dark:border-slate-600 dark:bg-slate-950">
                                        <button wire:click="pin({{ $sp->id }})" class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">{{ __('wp-repo::messages.pin') }}</button>
                                    </div>
                                    @error('pins.'.$sp->id) <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="space-x-3 px-4 py-2 text-xs rtl:space-x-reverse">
                                    <button wire:click="toggleAuto({{ $sp->id }})" class="hover:underline">{{ __('wp-repo::messages.auto_update') }}: {{ $sp->auto_update ? '✓' : '✗' }}</button>
                                    <button wire:click="updateNow({{ $sp->id }})" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ __('wp-repo::messages.update_now') }}</button>
                                    <button wire:click="removeAssignment({{ $sp->id }})" wire:confirm="{{ __('wp-repo::messages.confirm') }}" class="text-red-600 hover:underline">{{ __('wp-repo::messages.delete') }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-4 text-slate-500">{{ __('wp-repo::messages.none') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <p class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-slate-500 dark:border-slate-700">{{ __('wp-repo::messages.none') }}</p>
    @endforelse

    {{ $sites->links() }}
</div>
