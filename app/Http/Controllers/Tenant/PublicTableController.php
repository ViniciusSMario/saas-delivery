<?php

namespace App\Http\Controllers\Tenant;

use App\Modules\Establishment\Models\Establishment;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tables\TableOrdering;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pedido pela mesa (QR code): fechamento do pedido, envio para a comanda e chamado do garçom.
 * O cardápio da mesa é o PublicMenuController::table.
 */
class PublicTableController
{
    public function __construct(private readonly TableOrdering $ordering) {}

    public function checkout(Request $request, string $token): View
    {
        $table = $this->table($token);
        $tableUrl = Str::beforeLast($request->url(), '/pedido');

        return view('public-menu.table-checkout', [
            'establishment' => Establishment::current(),
            'table' => $table,
            'state' => $this->ordering->state($table),
            'tableUrl' => $tableUrl,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $table = $this->table($token);
        $tableUrl = Str::beforeLast($request->url(), '/pedido');

        try {
            $order = $this->ordering->place(
                $table,
                PublicOrderController::cartLines($request),
                is_string($request->input('name')) ? $request->input('name') : null,
                is_string($request->input('notes')) ? $request->input('notes') : null,
            );
        } catch (ValidationException $e) {
            return redirect($tableUrl.'/pedido')->withInput($request->except('cart'))->withErrors($e->errors());
        }

        return redirect($tableUrl.'?enviado='.$order->number.'#pedidos');
    }

    public function call(Request $request, string $token): RedirectResponse
    {
        $table = $this->table($token);
        $reason = (string) $request->input('reason');
        abort_unless(array_key_exists($reason, TableOrdering::CALL_REASONS), 422);

        $this->ordering->call($table, $reason);

        return redirect(Str::beforeLast($request->url(), '/chamar').'#pedidos')->with('table_call', $reason);
    }

    private function table(string $token): DiningTable
    {
        return $this->ordering->find($token) ?? abort(404);
    }
}
