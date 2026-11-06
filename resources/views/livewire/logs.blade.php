<div dir="{{ in_array(app()->getLocale(), ['fa', 'ar', 'he']) ? 'rtl' : 'ltr' }}"
     class="mx-auto max-w-6xl space-y-5 p-6 text-slate-800 dark:text-slate-100">
    @include('wp-repo::partials.nav', ['active' => 'logs'])

    <div class="flex flex-wrap gap-3 text-sm">
        <select wire:model.live="status" class="rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-900">
            <option value="">{{ __('wp-repo::messages.status') }}: {{ __('wp-repo::messages.all') }}</option>
            <option value="ok">ok</option>
            <option value="failed">failed</option>
            <option value="downloaded">downloaded</option>
        </select>
        <select wire:model.live="method" class="rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-900">
            <option value="">{{ __('wp-repo::messages.method') }}: {{ __('wp-repo::messages.all') }}</option>
            <option value="pull">pull</option>
            <option value="push">push</option>
            <option value="sync">sync</option>
        </select>
        <select wire:model.live="siteId" class="rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-900">
            <option value="">{{ __('wp-repo::messages.site') }}: {{ __('wp-repo::messages.all') }}</option>
            @foreach ($sites as $s) <option value="{{ $s->id }}">{{ $s->domain }}</option> @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-900">
                <tr>
                    <th class="px-4 py-2 text-start">{{ __('wp-repo::messages.date') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('wp-repo::messages.site') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('wp-repo::messages.package') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('wp-repo::messages.from') }} → {{ __('wp-repo::messages.to') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('wp-repo::messages.method') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('wp-repo::messages.status') }}</th>
                    <th class="px-4 py-2 text-start">{{ __('wp-repo::messages.message') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($logs as $log)
                    <tr wire:key="log-{{ $log->id }}">
                        <td class="whitespace-nowrap px-4 py-2" dir="ltr">{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-2" dir="ltr">{{ $log->site?->domain ?? '—' }}</td>
                        <td class="px-4 py-2" dir="ltr">{{ $log->package?->slug ?? '—' }}</td>
                        <td class="px-4 py-2" dir="ltr">{{ $log->from_version ?? '—' }} → {{ $log->to_version ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $log->method }}</td>
                        <td class="px-4 py-2">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $log->status === 'failed' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200' }}">{{ $log->status }}</span>
                        </td>
                        <td class="max-w-xs truncate px-4 py-2 text-xs text-slate-500" title="{{ $log->message }}">{{ $log->message }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">{{ __('wp-repo::messages.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
</div>
