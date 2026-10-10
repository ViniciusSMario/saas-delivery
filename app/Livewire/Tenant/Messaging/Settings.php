<?php

namespace App\Livewire\Tenant\Messaging;

use App\Modules\Access\Audit;
use App\Modules\Customers\Models\Customer;
use App\Modules\Establishment\Models\Establishment;
use App\Modules\Establishment\Models\Setting;
use App\Modules\Messaging\Models\WhatsAppMessage;
use App\Modules\Messaging\Providers\SafeUrl;
use App\Modules\Messaging\WhatsApp;
use App\Modules\Messaging\WhatsAppSettings;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/** WhatsApp: provedor, credenciais, quais avisos mandar e o texto de cada um. */
#[Title('WhatsApp')]
class Settings extends Component
{
    public bool $enabled = false;

    public string $provider = 'manual';

    public string $evolutionUrl = '';

    public string $evolutionInstance = '';

    /** Em branco = mantém a chave salva. */
    public string $evolutionKey = '';

    public string $cloudPhoneId = '';

    public string $cloudToken = '';

    public string $cloudTemplate = '';

    public string $cloudLanguage = 'pt_BR';

    /** @var array<string, bool> */
    public array $events = [];

    /** @var array<string, string> */
    public array $templates = [];

    public string $testPhone = '';

    public function mount(): void
    {
        $c = WhatsAppSettings::credentials();

        $this->enabled = WhatsAppSettings::enabled();
        $this->provider = WhatsAppSettings::provider();
        $this->evolutionUrl = $c['evolution_url'] ?? '';
        $this->evolutionInstance = $c['evolution_instance'] ?? '';
        $this->cloudPhoneId = $c['cloud_phone_id'] ?? '';
        $this->cloudTemplate = $c['cloud_template'] ?? '';
        $this->cloudLanguage = $c['cloud_language'] ?? 'pt_BR';

        foreach (array_keys(WhatsAppSettings::EVENTS) as $event) {
            $this->events[$event] = WhatsAppSettings::eventEnabled($event);
            $this->templates[$event] = WhatsAppSettings::template($event);
        }
    }

    protected function rules(): array
    {
        return [
            'provider' => ['required', Rule::in(array_keys(WhatsAppSettings::PROVIDERS))],
            'evolutionUrl' => [Rule::requiredIf($this->enabled && $this->provider === 'evolution'), 'nullable', 'url:https', 'max:200'],
            'evolutionInstance' => [Rule::requiredIf($this->enabled && $this->provider === 'evolution'), 'nullable', 'string', 'max:80'],
            'evolutionKey' => ['nullable', 'string', 'max:200'],
            'cloudPhoneId' => [Rule::requiredIf($this->enabled && $this->provider === 'cloud'), 'nullable', 'regex:/^\d{5,30}$/'],
            'cloudToken' => ['nullable', 'string', 'max:500'],
            'cloudTemplate' => ['nullable', 'regex:/^[a-z0-9_]{1,100}$/'],
            'cloudLanguage' => ['nullable', 'regex:/^[a-z]{2}(_[A-Z]{2})?$/'],
            'templates.*' => ['required', 'string', 'max:600'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'evolutionUrl' => 'endereço da Evolution API', 'evolutionInstance' => 'instância', 'cloudPhoneId' => 'ID do número',
            'cloudTemplate' => 'nome do modelo', 'cloudLanguage' => 'idioma', 'templates.*' => 'texto do aviso',
        ];
    }

    public function save(): void
    {
        $this->authorize('settings.manage');
        $this->validate();

        if ($this->provider === 'evolution' && $this->evolutionUrl !== '') {
            try {
                SafeUrl::assert($this->evolutionUrl);
            } catch (Throwable $e) {
                $this->addError('evolutionUrl', $e->getMessage());

                return;
            }
        }

        $old = WhatsAppSettings::credentials();
        WhatsAppSettings::saveCredentials([
            'evolution_url' => trim($this->evolutionUrl),
            'evolution_instance' => trim($this->evolutionInstance),
            'evolution_key' => $this->evolutionKey !== '' ? trim($this->evolutionKey) : ($old['evolution_key'] ?? ''),
            'cloud_phone_id' => trim($this->cloudPhoneId),
            'cloud_token' => $this->cloudToken !== '' ? trim($this->cloudToken) : ($old['cloud_token'] ?? ''),
            'cloud_template' => trim($this->cloudTemplate),
            'cloud_language' => trim($this->cloudLanguage) ?: 'pt_BR',
        ]);

        Setting::write('whatsapp_enabled', $this->enabled);
        Setting::write('whatsapp_provider', $this->provider);
        Setting::write('whatsapp_events', array_map('boolval', $this->events));
        Setting::write('whatsapp_templates', array_map('trim', $this->templates));

        Audit::record('settings.updated', null, ['whatsapp' => $this->enabled ? WhatsAppSettings::PROVIDERS[$this->provider] : 'desligado']);
        $this->reset('evolutionKey', 'cloudToken');
        session()->flash('status', 'WhatsApp salvo.');
    }

    public function sendTest(WhatsApp $whatsapp): void
    {
        $this->authorize('settings.manage');
        $phone = Customer::normalizePhone($this->testPhone);

        if (! Customer::isValidPhone($phone)) {
            $this->addError('testPhone', 'Telefone inválido. Use DDD + número.');

            return;
        }

        if ($this->provider === 'manual' || $this->provider !== WhatsAppSettings::provider()) {
            $this->addError('testPhone', 'Salve um provedor de envio automático antes de testar.');

            return;
        }

        $name = Establishment::current()->trade_name ?: tenant()->name;
        $message = $whatsapp->sendNow($phone, "Mensagem de teste do {$name}: os avisos de pedido pelo WhatsApp estão funcionando.");

        $message->status === 'sent'
            ? session()->flash('status', 'Mensagem de teste enviada.')
            : $this->addError('testPhone', 'Não enviou: '.$message->error);
    }

    public function render()
    {
        $c = WhatsAppSettings::credentials();

        return view('livewire.tenant.messaging.settings', [
            'hasEvolutionKey' => ($c['evolution_key'] ?? '') !== '',
            'hasCloudToken' => ($c['cloud_token'] ?? '') !== '',
            'recent' => WhatsAppMessage::with('order')->latest('id')->limit(15)->get(),
        ]);
    }
}
