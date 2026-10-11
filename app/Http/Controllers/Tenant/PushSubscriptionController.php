<?php

namespace App\Http\Controllers\Tenant;

use App\Modules\Push\Models\PushSubscription;
use App\Modules\Push\PushEndpoint;
use App\Modules\Push\StaffAlerts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Liga e desliga as notificações neste celular (o navegador manda a inscrição de push). */
class PushSubscriptionController
{
    public function store(Request $request): Response
    {
        $data = $request->validate(PushEndpoint::RULES);

        abort_unless(PushEndpoint::isAllowed($data['endpoint']), 422, 'Serviço de notificações não reconhecido.');

        // Mesmo celular, outra pessoa logada: a inscrição passa a ser dela.
        PushSubscription::updateOrCreate(['endpoint' => $data['endpoint']], [
            'user_id' => Auth::guard('web')->id(),
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        // Ao sair da conta, este celular para de receber os avisos dela.
        $request->session()->put('push_endpoint', $data['endpoint']);

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $endpoint = (string) $request->input('endpoint');

        PushSubscription::where('endpoint', $endpoint)->where('user_id', Auth::guard('web')->id())->delete();
        $request->session()->forget('push_endpoint');

        return response()->noContent();
    }

    public function test(StaffAlerts $alerts): JsonResponse
    {
        $user = Auth::guard('web')->user();

        if (! PushSubscription::where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Ative as notificações neste celular primeiro.'], 422);
        }

        $alerts->test($user);

        return response()->json(['message' => 'Aviso de teste enviado.'], 202);
    }
}
