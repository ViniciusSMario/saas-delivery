<?php

namespace App\Modules\Access\Models;

use App\Tenancy\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends TenantModel
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'auth.login' => 'Entrou no sistema',
            'auth.logout' => 'Saiu do sistema',
            'auth.password_reset' => 'Definiu uma nova senha',
            'user.created' => 'Criou usuário',
            'user.updated' => 'Alterou usuário',
            'user.invited' => 'Enviou convite',
            'role.created' => 'Criou perfil',
            'role.updated' => 'Alterou perfil',
            'role.deleted' => 'Excluiu perfil',
            'establishment.updated' => 'Alterou dados do estabelecimento',
            'settings.updated' => 'Alterou configurações',
            'opening_hours.updated' => 'Alterou horário de funcionamento',
            'category.created' => 'Criou categoria',
            'category.updated' => 'Alterou categoria',
            'category.deleted' => 'Excluiu categoria',
            'product.created' => 'Criou produto',
            'product.updated' => 'Alterou produto',
            'product.deleted' => 'Excluiu produto',
            'addon_group.created' => 'Criou grupo de adicionais',
            'addon_group.updated' => 'Alterou grupo de adicionais',
            'addon_group.deleted' => 'Excluiu grupo de adicionais',
            'combo.created' => 'Criou combo',
            'combo.updated' => 'Alterou combo',
            'combo.deleted' => 'Excluiu combo',
            'order.cancelled' => 'Cancelou pedido',
            'order.paid' => 'Marcou pedido como pago',
            'cash.opened' => 'Abriu o caixa',
            'cash.withdrawal' => 'Fez sangria',
            'cash.deposit' => 'Fez suprimento',
            'cash.closed' => 'Fechou o caixa',
            'cash.register_created' => 'Cadastrou caixa',
            'rush.delay' => 'Aumentou a previsão (hora do sufoco)',
            'rush.delay_cleared' => 'Voltou a previsão ao normal',
            'rush.paused' => 'Pausou os pedidos online',
            'rush.resumed' => 'Retomou os pedidos online',
            'customer.winback_opt_out' => 'Marcou cliente para não chamar mais',
            'command.closed' => 'Fechou conta',
            'command.cancelled' => 'Cancelou comanda',
            'command.transferred' => 'Trocou comanda de mesa',
            'command.merged' => 'Juntou comandas',
            'customer.updated' => 'Alterou cliente',
            'customer.anonymized' => 'Apagou dados pessoais de cliente (LGPD)',
            'finance.created' => 'Lançou conta',
            'finance.updated' => 'Alterou conta',
            'finance.paid' => 'Baixou conta (pagou/recebeu)',
            'finance.cancelled' => 'Cancelou conta',
            'finance.account_created' => 'Cadastrou conta financeira',
            'stock_item.created' => 'Cadastrou insumo',
            'stock_item.updated' => 'Alterou insumo',
            'recipe.updated' => 'Alterou ficha técnica',
            'table.created' => 'Cadastrou mesa',
            'table.updated' => 'Alterou mesa',
            default => $this->action,
        };
    }
}
