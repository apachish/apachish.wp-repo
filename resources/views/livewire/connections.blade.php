<div dir="{{ in_array(app()->getLocale(), ['fa', 'ar', 'he']) ? 'rtl' : 'ltr' }}"
     class="mx-auto max-w-6xl space-y-5 p-6 text-slate-800 dark:text-slate-100">
    @include('wp-repo::partials.nav', ['active' => 'connections'])

    @if ($message)
        <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950 dark:text-emerald-200">{{ $message }}</div>
    @endif

    @if ($newSecret)
        <div class="space-y-2 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm dark:border-amber-700 dark:bg-amber-950">
            <p class="font-medium">{{ __('wp-repo::messages.webhook_secret') }} — {{ __('wp-repo::messages.api_key_once') }}</p>
            <code class="block break-all rounded bg-white px-3 py-2 font-mono text-xs dark:bg-slate-900" dir="ltr">{{ $newSecret }}</code>
            <p class="font-medium">{{ __('wp-repo::messages.webhook_url') }}</p>
            <code class="block break-all rounded bg-white px-3 py-2 font-mono text-xs dark:bg-slate-900" dir="ltr">{{ $newSecretUrl }}</code>
            <button wire:click="$set('newSecret', null)" class="text-xs underline">{{ __('wp-repo::messages.cancel') }}</button>
        </div>
    @endif

    <div class="flex justify-end">
        <button wire:click="create" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">{{ __('wp-repo::messages.add_connection') }}</button>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2 dark:border-slate-700 dark:bg-slate-900">
            <h2 class="text-base font-semibold sm:col-span-2">{{ $editingId ? __('wp-repo::messages.edit_connection') : __('wp-repo::messages.add_connection') }}</h2>

            <label class="space-y-1 text-sm">
                <span>{{ __('wp-repo::messages.name') }}</span>
                <input wire:model="form.name" type="text" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
                @error('form.name') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </label>

            <label class="space-y-1 text-sm">
                <span>{{ __('wp-repo::messages.provider') }}</span>
                <select wire:model.live="form.provider" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
                    <option value="github">GitHub</option>
                    <option value="gitlab">GitLab</option>
                    <option value="gitea">Gitea / Forgejo</option>
                </select>
            </label>

            <label class="space-y-1 text-sm">
                <span>{{ __('wp-repo::messages.token') }}</span>
                <input wire:model="form.token" type="password" autocomplete="off" dir="ltr"
                       placeholder="{{ $editingId ? __('wp-repo::messages.token_keep') : '' }}"
                       class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
                @error('form.token') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </label>

            <label class="space-y-1 text-sm">
                <span>{{ __('wp-repo::messages.api_url') }}</span>
                <input wire:model="form.api_url" type="url" dir="ltr" placeholder="https://git.example.com/api/v1"
                       class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
                @error('form.api_url') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </label>

            <div class="flex gap-2 sm:col-span-2">
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">{{ __('wp-repo::messages.save') }}</button>
                <button type="button" wire:click="$set('showForm', false)" class="rounded-md border border-slate-300 px-4 py-2 text-sm dark:border-slate-600">{{ __('wp-repo::messages.cancel') }}</button>
            </div>
        </form>
    @endif

    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-900">
                <tr>
                    <th class="px-4 py-2 text-start">{{ __('wp-repo::messages.name') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('wp-repo::messages.webhook_url') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('wp-repo::messages.packages') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('wp-repo::messages.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($connections as $connection)
                    <tr wire:key="conn-{{ $connection->id }}">
                        <td class="px-4 py-2">
                            <div class="font-medium">{{ $connection->name }}</div>
                            <div class="text-xs text-slate-500" dir="ltr">{{ $connection->provider }}@if ($connection->api_url) · {{ $connection->api_url }}@endif</div>
                        </td>
                        <td class="px-4 py-2"><input readonly dir="ltr" value="{{ $connection->webhookUrl() }}" onclick="this.select()" class="w-full min-w-64 rounded border border-slate-200 bg-slate-50 px-2 py-1 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"></td>
                        <td class="px-4 py-2">{{ $connection->packages_count }}</td>
                        <td class="space-x-3 px-4 py-2 text-xs rtl:space-x-reverse">
                            <button wire:click="edit({{ $connection->id }})" class="hover:underline">{{ __('wp-repo::messages.edit') }}</button>
                            <button wire:click="regenerateSecret({{ $connection->id }})" wire:confirm="{{ __('wp-repo::messages.confirm') }}" class="hover:underline">{{ __('wp-repo::messages.regenerate_secret') }}</button>
                            <button wire:click="delete({{ $connection->id }})" wire:confirm="{{ __('wp-repo::messages.confirm') }}" class="text-red-600 hover:underline">{{ __('wp-repo::messages.delete') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">{{ __('wp-repo::messages.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
