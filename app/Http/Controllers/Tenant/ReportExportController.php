<?php

namespace App\Http\Controllers\Tenant;

use App\Modules\Reports\SalesReport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exportação em CSV (separador ";" e BOM UTF-8, abre direto no Excel em pt-BR). */
class ReportExportController
{
    public function __invoke(Request $request, string $type, SalesReport $report): StreamedResponse
    {
        $valid = fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
        $from = $valid($request->query('from')) ? $request->query('from') : now()->toDateString();
        $to = $valid($request->query('to')) ? $request->query('to') : $from;
        [$start, $end] = $report->range($from, $to);
        $decimal = fn ($money) => $money ? number_format($money->cents / 100, 2, ',', '') : '';

        [$header, $rows] = match ($type) {
            'vendas' => [
                ['Dia', 'Pedidos', 'Ticket médio', 'Vendas'],
                $report->daily($start, $end)->map(fn ($d) => [$d['date']->format('d/m/Y'), $d['count'], $decimal($d['average']), $decimal($d['total'])]),
            ],
            'produtos' => [
                ['Produto', 'Quantidade', 'Faturamento', 'Custo (ficha)', 'Margem %'],
                $report->topProducts($start, $end, 1000)->map(fn ($p) => [$p['name'], $p['quantity'], $decimal($p['revenue']), $decimal($p['cost']), $p['margin'] !== null ? number_format($p['margin'], 1, ',', '') : '']),
            ],
            default => abort(404),
        };

        $filename = "{$type}-{$from}-a-{$to}.csv";

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';');

            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
