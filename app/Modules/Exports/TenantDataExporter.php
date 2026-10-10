<?php

namespace App\Modules\Exports;

use App\Modules\Establishment\Models\Establishment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Exporta os dados do estabelecimento num .zip: uma planilha CSV por assunto
 * (abre no Excel), as fotos e o logo, e um LEIAME. Valores em centavos viram reais.
 * Ficam de fora senhas, tokens e credenciais (configurações do WhatsApp etc.).
 */
class TenantDataExporter
{
    /** tabela => nome do arquivo */
    public const TABLES = [
        'establishment' => 'estabelecimento',
        'opening_hours' => 'horarios',
        'customers' => 'clientes',
        'customer_addresses' => 'clientes_enderecos',
        'orders' => 'pedidos',
        'order_items' => 'pedidos_itens',
        'order_item_addons' => 'pedidos_itens_adicionais',
        'order_payments' => 'pedidos_pagamentos',
        'order_status_history' => 'pedidos_historico',
        'categories' => 'cardapio_categorias',
        'products' => 'cardapio_produtos',
        'addon_groups' => 'cardapio_grupos_adicionais',
        'addons' => 'cardapio_adicionais',
        'combos' => 'cardapio_combos',
        'combo_items' => 'cardapio_combos_itens',
        'tables' => 'mesas',
        'commands' => 'comandas',
        'cash_registers' => 'caixas',
        'cash_sessions' => 'caixas_sessoes',
        'cash_movements' => 'caixas_movimentos',
        'financial_accounts' => 'financeiro_contas',
        'financial_categories' => 'financeiro_categorias',
        'financial_entries' => 'financeiro_lancamentos',
        'units' => 'estoque_unidades',
        'stock_items' => 'estoque_insumos',
        'stock_movements' => 'estoque_movimentos',
        'recipes' => 'estoque_fichas_tecnicas',
        'recipe_items' => 'estoque_fichas_itens',
        'delivery_zones' => 'entrega_bairros',
        'users' => 'usuarios',
        'roles' => 'perfis',
        'audit_logs' => 'auditoria',
        'whatsapp_messages' => 'whatsapp_mensagens',
    ];

    /** Colunas que nunca saem. */
    public const HIDDEN = ['password', 'remember_token', 'public_token', 'verification_token'];

    /** @return string caminho do .zip no disco "tenant" */
    public function build(int $exportId): string
    {
        $path = 'exports/dados-'.now()->format('Ymd-His').'-'.$exportId.'.zip';
        $disk = Storage::disk('tenant');
        $disk->makeDirectory('exports');

        $zip = new ZipArchive;

        if ($zip->open($disk->path($path), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar o arquivo da exportação.');
        }

        $db = DB::connection('tenant');
        $counts = [];

        foreach (self::TABLES as $table => $file) {
            if (! Schema::connection('tenant')->hasTable($table)) {
                continue;
            }

            $zip->addFromString("planilhas/{$file}.csv", $this->csv($db->table($table)->orderBy($this->orderColumn($table))->cursor(), $table));
            $counts[$file] = $db->table($table)->count();
        }

        foreach ($disk->allFiles() as $file) {
            if (! str_starts_with($file, 'exports/')) {
                $zip->addFile($disk->path($file), 'arquivos/'.$file);
            }
        }

        $zip->addFromString('LEIAME.txt', $this->readme($counts));
        $zip->close();

        return $path;
    }

    private function csv(iterable $rows, string $table): string
    {
        $columns = array_values(array_diff(Schema::connection('tenant')->getColumnListing($table), self::HIDDEN));
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // BOM: o Excel reconhece os acentos

        fputcsv($out, array_map(fn ($c) => str_ends_with($c, '_cents') ? substr($c, 0, -6).'_reais' : $c, $columns), ';');

        foreach ($rows as $row) {
            $row = (array) $row;
            fputcsv($out, array_map(function (string $column) use ($row) {
                $value = $row[$column] ?? null;

                if ($value !== null && str_ends_with($column, '_cents')) {
                    return number_format(((int) $value) / 100, 2, ',', '');
                }

                return is_bool($value) ? ($value ? 'sim' : 'não') : $value;
            }, $columns), ';');
        }

        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);

        return $content;
    }

    private function orderColumn(string $table): string
    {
        return Schema::connection('tenant')->hasColumn($table, 'id') ? 'id' : Schema::connection('tenant')->getColumnListing($table)[0];
    }

    private function readme(array $counts): string
    {
        $lines = [
            'Exportação de dados - '.(Establishment::current()->trade_name ?: tenant()->name),
            'Gerada em '.now()->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            '',
            'planilhas/: um arquivo CSV por assunto (separador ";", abre no Excel ou no Google Planilhas).',
            'Valores em dinheiro estão em reais (colunas terminadas em "_reais").',
            'arquivos/: fotos dos produtos e logo.',
            'Senhas, tokens e credenciais de integrações não são exportados.',
            '',
            'Registros por planilha:',
        ];

        foreach ($counts as $file => $count) {
            $lines[] = "  {$file}.csv: {$count}";
        }

        return implode("\r\n", $lines)."\r\n";
    }
}
