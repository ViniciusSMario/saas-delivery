<div class="space-y-6">
    <div>
        <h1 class="page-title">Auditoria</h1>
        <p class="mt-1 text-sm text-stone-500">Quem fez o quê no sistema deste estabelecimento.</p>
    </div>

    <div class="card overflow-x-auto">
        <table class="table table-cards">
            <thead>
                <tr>
                    <th data-card="field">Quando</th>
                    <th>Quem</th>
                    <th data-card="title">O quê</th>
                    <th>Detalhes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr wire:key="log-{{ $log->id }}">
                        <td class="whitespace-nowrap text-stone-500">{{ $log->created_at->timezone($timezone)->format('d/m/Y H:i') }}</td>
                        <td>{{ $log->user?->name ?? 'Sistema' }}</td>
                        <td>
                            {{ $log->actionLabel() }}
                            @if ($log->subject_type)
                                <span class="text-xs text-stone-400">· {{ $log->subject_type }} #{{ $log->subject_id }}</span>
                            @endif
                        </td>
                        <td class="max-w-md">
                            @foreach ($log->changes ?? [] as $field => $change)
                                <p class="truncate text-xs text-stone-600">
                                    <span class="font-medium">{{ $field }}:</span>
                                    @if (is_array($change) && array_key_exists('para', $change))
                                        {{ json_encode($change['de'], JSON_UNESCAPED_UNICODE) }} → {{ json_encode($change['para'], JSON_UNESCAPED_UNICODE) }}
                                    @else
                                        {{ is_scalar($change) ? $change : json_encode($change, JSON_UNESCAPED_UNICODE) }}
                                    @endif
                                </p>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-ui.empty icon="list" title="Nenhum registro ainda" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
</div>
