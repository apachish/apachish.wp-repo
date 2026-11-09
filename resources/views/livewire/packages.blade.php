<div dir="{{ in_array(app()->getLocale(), ['fa', 'ar', 'he']) ? 'rtl' : 'ltr' }}"
     class="mx-auto max-w-6xl space-y-5 p-6 text-slate-800 dark:text-slate-100">
    @include('wp-repo::partials.nav', ['active' => 'packages'])

    @if ($message)
        <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950 dark:text-emerald-200">{{ $message }}</div>
    @endif

    <div class="flex items-center justify-between gap-3">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('wp-repo::messages.search') }}"
               class="w-full max-w-xs rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900">
        <button wire:click="create" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
            {{ __('wp-repo::messages.add_package') }}
        </button>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2 dark:border-slate-700 dark:bg-slate-900">
            <h2 class="text-base font-semibold sm:col-span-2">
                {{ $editingId ? __('wp-repo::messages.edit_package') : __('wp-repo::messages.add_package') }}
            </h2>

            <label class="space-y-1 text-sm">
                <span>{{ __('wp-repo::messages.slug') }}</span>
                <input wire:model="form.slug" type="text" dir="ltr" @disabled($locked)
                       class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 disabled:opacity-60 dark:border-slate-600 dark:bg-slate-950">
                @error('form.slug') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </label>

            <label class="space-y-1 text-sm">
                <span>{{ __('wp-repo::messages.type') }}</span>
                <select wire:model="form.type" @disabled($locked)
                        class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 disabled:opacity-60 dark:border-slate-600 dark:bg-slate-950">
                    <option value="plugin">{{ __('wp-repo::messages.plugin') }}</option>
                    <option value="theme">{{ __('wp-repo::messages.theme') }}</option>
                </select>
            </label>

            <label class="space-y-1 text-sm">
                <span>{{ __('wp-repo::messages.name') }}</span>
                <input wire:model="form.name" type="text"
                       class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
                @error('form.name') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </label>

            <label class="space-y-1 text-sm">
                <span>{{ __('wp-repo::messages.repo') }} (owner/repo)</span>
                <input wire:model="form.repo" type="text" dir="ltr" placeholder="acme/my-plugin"
                       class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
                @error('form.repo') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </label>

            <label class="space-y-1 text-sm">
                <span>{{ __('wp-repo::messages.connection') }}</span>
                <select wire:model="form.wp_git_connection_id"
                        class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
                    <option value="">—</option>
                    @foreach ($connections as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->provider }})</option>
                    @endforeach
                </select>
                @if ($connections->isEmpty())
                    <p class="text-xs text-amber-600"><a href="{{ route('wp-repo.panel.connections',['api_key' =>  data_get($project,"api_key")]) }}" wire:navigate class="underline">{{ __('wp-repo::messages.no_connection_hint') }}</a></p>
                @endif
                @error('form.wp_git_connection_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </label>

            <div class="flex items-end gap-6 text-sm">
                <label class="flex items-center gap-2"><input wire:model="form.is_active" type="checkbox"> {{ __('wp-repo::messages.active') }}</label>
                <label class="flex items-center gap-2"><input wire:model="form.is_paid" type="checkbox"> {{ __('wp-repo::messages.paid') }}</label>
            </div>

            <div class="flex gap-2 sm:col-span-2">
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">{{ __('wp-repo::messages.save') }}</button>
                <button type="button" wire:click="$set('showForm', false)" class="rounded-md border border-slate-300 px-4 py-2 text-sm dark:border-slate-600">{{ __('wp-repo::messages.cancel') }}</button>
            </div>
        </form>
    @endif

    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
            <thead class="bg-slate-50 text-start dark:bg-slate-900">
                <tr class="text-start">
                    <th class="px-4 py-2 text-start">{{ __('wp-repo::messages.name') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('wp-repo::messages.repo') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('wp-repo::messages.latest_version') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('wp-repo::messages.status') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('wp-repo::messages.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($packages as $package)
                    <tr wire:key="pkg-{{ $package->id }}">
                        <td class="px-4 py-2">
                            <a href="{{ route('wp-repo.panel.package',['api_key' =>  data_get($project,"api_key"),"package"=>$package]) }}" wire:navigate class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ $package->name }}</a>
                            <div class="text-xs text-slate-500" dir="ltr">{{ $package->slug }} · {{ __('wp-repo::messages.'.$package->type) }}@if ($package->is_paid) · {{ __('wp-repo::messages.paid') }}@endif</div>
                        </td>
                        <td class="px-4 py-2" dir="ltr">{{ $package->repo }} <span class="text-xs text-slate-500">({{ $package->connection?->name ?? '—' }})</span></td>
                        <td class="px-4 py-2" dir="ltr">{{ $package->latestStable()?->version ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <button wire:click="toggle({{ $package->id }})"
                                    class="rounded-full px-2 py-0.5 text-xs {{ $package->is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200' : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200' }}">
                                {{ $package->is_active ? __('wp-repo::messages.active') : '—' }}
                            </button>
                        </td>
                        <td class="space-x-2 px-4 py-2 rtl:space-x-reverse">
                            <button wire:click="sync({{ $package->id }})" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ __('wp-repo::messages.sync') }}</button>
                            <button wire:click="edit({{ $package->id }})" class="hover:underline">{{ __('wp-repo::messages.edit') }}</button>
                            <button wire:click="delete({{ $package->id }})" wire:confirm="{{ __('wp-repo::messages.confirm') }}" class="text-red-600 hover:underline">{{ __('wp-repo::messages.delete') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">{{ __('wp-repo::messages.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $packages->links() }}
</div>
