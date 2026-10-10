{{-- Cupons térmicos em HTML (estação de impressão). Uma página por cópia. --}}
@foreach ($jobs as $job)
    @for ($copy = 0; $copy < max(1, $job->station->copies); $copy++)
        <div class="ticket" style="--cols: {{ $job->station->columns }}">
            @foreach ($job->lines as $line)
                <div class="ticket-line ticket-{{ $line['style'] }} ticket-{{ $line['align'] }}">{{ $line['text'] !== '' ? $line['text'] : ' ' }}</div>
            @endforeach
        </div>
    @endfor
@endforeach
