<?php

namespace App\Livewire\Tenant\Printing;

use App\Modules\Printing\Models\PrintJob;
use App\Modules\Printing\Models\PrintStation;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Estação de impressão: fica aberta no computador ligado à impressora térmica e
 * imprime sozinha os cupons dos setores escolhidos. Com o Chrome aberto em
 * --kiosk-printing, imprime sem mostrar a janela de impressão.
 */
#[Layout('components.layouts.app', ['wide' => true])]
#[Title('Estação de impressão')]
class Station extends Component
{
    /** @var list<int> */
    #[Url(as: 'setores')]
    public array $stationIds = [];

    public bool $running = false;

    public function toggleStation(int $id): void
    {
        $this->stationIds = in_array($id, $this->stationIds, true)
            ? array_values(array_diff($this->stationIds, [$id]))
            : [...$this->stationIds, $id];
    }

    /**
     * Pega os cupons pendentes destes setores (nenhuma outra estação pega os mesmos)
     * e devolve o HTML pronto para imprimir.
     *
     * @return array{ids: list<int>, html: string}
     */
    public function claim(): array
    {
        $this->authorize('orders.view');

        $jobs = DB::connection('tenant')->transaction(function () {
            $jobs = PrintJob::query()
                ->whereIn('print_station_id', $this->browserStations()->pluck('id'))
                ->waiting()
                ->orderBy('id')
                ->limit(10)
                ->lockForUpdate()
                ->get();

            PrintJob::whereKey($jobs->modelKeys())->update(['status' => 'printing', 'claimed_at' => now()]);

            return $jobs->load('station');
        });

        return [
            'ids' => $jobs->modelKeys(),
            'html' => $jobs->isEmpty() ? '' : view('printing.tickets', ['jobs' => $jobs])->render(),
        ];
    }

    /** @param  list<int>  $ids */
    public function confirm(array $ids): void
    {
        $this->authorize('orders.view');

        PrintJob::whereKey($ids)->where('status', 'printing')->update(['status' => 'printed', 'printed_at' => now()]);
    }

    public function reprint(int $jobId): void
    {
        $this->authorize('orders.view');

        PrintJob::whereKey($jobId)->update(['status' => 'pending', 'claimed_at' => null]);
    }

    private function browserStations()
    {
        return PrintStation::active()->where('mode', 'browser')->whereIn('id', $this->stationIds)->get();
    }

    public function render()
    {
        $stations = PrintStation::active()->orderBy('name')->get();
        $ids = $stations->where('mode', 'browser')->whereIn('id', $this->stationIds)->pluck('id');

        return view('livewire.tenant.printing.station', [
            'stations' => $stations,
            'pending' => PrintJob::whereIn('print_station_id', $ids)->waiting()->count(),
            'recent' => PrintJob::with('station')->whereIn('print_station_id', $ids)->latest('id')->limit(15)->get(),
        ]);
    }
}
