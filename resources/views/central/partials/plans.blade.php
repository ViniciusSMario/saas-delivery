{{-- Vitrine de planos. Espera: $plans. O do meio fica em destaque. --}}
<div class="mt-10 grid gap-5 lg:grid-cols-3">
    @foreach ($plans as $plan)
        @php($featured = $loop->count === 3 ? $loop->index === 1 : $loop->first)
        <div @class(['relative flex flex-col rounded-3xl border bg-white p-7', 'border-brand-500 shadow-xl shadow-brand-600/10 ring-1 ring-brand-500' => $featured, 'border-stone-200' => ! $featured])>
            @if ($featured)
                <span class="absolute -top-3 left-7 rounded-full bg-brand-600 px-3 py-1 text-xs font-bold text-white">Mais escolhido</span>
            @endif
            <h3 class="text-lg font-bold">{{ $plan->name }}</h3>
            <p class="mt-1 min-h-10 text-sm text-stone-500">{{ $plan->description }}</p>
            <p class="mt-5"><span class="text-4xl font-extrabold tracking-tight">{{ $plan->price()->format() }}</span><span class="text-stone-500">/mês</span></p>
            <ul class="mt-6 flex-1 space-y-2.5 text-sm">
                @foreach ($plan->highlights() as $line)
                    <li class="flex items-start gap-2"><x-icon name="check" class="mt-0.5 size-4 text-emerald-600" stroke="2.6" /> {{ $line }}</li>
                @endforeach
            </ul>
            <a href="{{ route('signup.create', ['plano' => $plan->slug]) }}" @class(['mt-7 w-full', 'btn-primary' => $featured, 'btn-secondary' => ! $featured])>Testar 14 dias grátis</a>
        </div>
    @endforeach
</div>
