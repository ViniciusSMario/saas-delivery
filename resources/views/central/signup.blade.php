@php($port = parse_url(config('app.url'), PHP_URL_PORT))
<x-layouts.site title="Criar conta">
    <section class="bg-gradient-to-b from-brand-50 to-white">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[1fr_26rem] lg:py-16">
            {{-- Endereço: digitando, o hífen do fim fica (senão não dá para digitar pizzaria-do...); ao sair do campo, sai. --}}
            <form method="POST" action="{{ route('signup.store') }}" class="card space-y-6 p-6 sm:p-8"
                  x-data="{
                      name: @js(old('name', '')),
                      slug: @js(old('slug', '')),
                      touched: @js((bool) old('slug')),
                      status: null,
                      timer: null,
                      plan: @js(old('plan', $selected)),
                      suggest() { if (! this.touched) { this.slug = this.slugify(this.name); this.check(); } },
                      slugify(s, typing = false) {
                          const slug = s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+/, '').slice(0, 40);
                          return typing ? slug : slug.replace(/-+$/, '');
                      },
                      check() {
                          clearTimeout(this.timer);
                          if (this.slug.length < 3) { this.status = null; return; }
                          this.status = 'checking';
                          this.timer = setTimeout(async () => {
                              try {
                                  const r = await (await fetch(@js(route('signup.check')) + '?slug=' + encodeURIComponent(this.slug))).json();
                                  this.status = r.available ? 'ok' : 'taken';
                              } catch (e) { this.status = null; }
                          }, 350);
                      },
                  }" x-init="if (slug) check()">
                @csrf
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight">Crie sua conta</h1>
                    <p class="mt-1 text-sm text-stone-500">14 dias grátis, sem cartão. Leva um minuto.</p>
                </div>

                {{-- Campo escondido contra robôs --}}
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

                <fieldset class="space-y-4">
                    <legend class="stat-label mb-2">Estabelecimento</legend>
                    <div>
                        <label for="name" class="label">Nome do estabelecimento</label>
                        <input id="name" name="name" type="text" class="input h-11" x-model="name" @input="suggest()" required maxlength="120" placeholder="Ex.: Pizzaria do João">
                        @error('name') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="slug" class="label">Endereço do seu sistema e do cardápio</label>
                        <div class="flex items-stretch overflow-hidden rounded-xl border border-stone-300 bg-white focus-within:border-brand-500 focus-within:ring-4 focus-within:ring-brand-100">
                            <span class="flex items-center bg-stone-50 pl-3 text-sm text-stone-500">http://</span>
                            <input id="slug" name="slug" type="text" class="min-w-0 flex-1 border-0 bg-transparent px-1 py-2.5 text-sm focus:ring-0 focus:outline-none" x-model="slug"
                                   @input="touched = true; slug = slugify(slug, true); check()" @blur="slug = slugify(slug); check()" required maxlength="40" placeholder="pizzaria-do-joao" autocomplete="off">
                            <span class="flex items-center bg-stone-50 pr-3 text-sm text-stone-500">.{{ $baseDomain }}{{ $port ? ':'.$port : '' }}</span>
                        </div>
                        <p class="hint" x-show="status === 'checking'">Verificando...</p>
                        <p class="mt-1.5 text-xs font-semibold text-emerald-700" x-show="status === 'ok'" x-cloak>✓ Disponível</p>
                        <p class="mt-1.5 text-xs font-semibold text-red-600" x-show="status === 'taken'" x-cloak>Este endereço já está em uso. Tente outro.</p>
                        @error('slug') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </fieldset>

                <fieldset class="space-y-4">
                    <legend class="stat-label mb-2">Seus dados de acesso</legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="owner_name" class="label">Seu nome</label>
                            <input id="owner_name" name="owner_name" type="text" class="input h-11" value="{{ old('owner_name') }}" required autocomplete="name">
                            @error('owner_name') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="phone" class="label">WhatsApp (opcional)</label>
                            <input id="phone" name="phone" type="tel" data-mask="phone" class="input h-11" value="{{ old('phone') }}" autocomplete="tel" placeholder="(11) 98888-7777">
                        </div>
                    </div>
                    <div>
                        <label for="email" class="label">E-mail</label>
                        <input id="email" name="email" type="email" class="input h-11" value="{{ old('email') }}" required autocomplete="email">
                        @error('email') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="password" class="label">Senha</label>
                            <input id="password" name="password" type="password" class="input h-11" required minlength="8" autocomplete="new-password">
                            @error('password') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="password_confirmation" class="label">Confirme a senha</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" class="input h-11" required autocomplete="new-password">
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="stat-label mb-2">Plano (pode trocar depois)</legend>
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach ($plans as $plan)
                            <label class="flex cursor-pointer flex-col rounded-2xl border-2 p-3.5 transition" :class="plan === @js($plan->slug) ? 'border-brand-600 bg-brand-50' : 'border-stone-200 hover:border-stone-300'">
                                <input type="radio" name="plan" value="{{ $plan->slug }}" x-model="plan" class="sr-only">
                                <span class="font-bold">{{ $plan->name }}</span>
                                <span class="text-sm text-stone-600">{{ $plan->price()->format() }}/mês</span>
                            </label>
                        @endforeach
                    </div>
                    @error('plan') <p class="field-error">{{ $message }}</p> @enderror
                </fieldset>

                <div>
                    <label class="flex items-start gap-2 text-sm text-stone-600">
                        <input type="checkbox" name="terms" value="1" class="checkbox mt-0.5" @checked(old('terms')) required>
                        <span>Li e aceito os termos de uso e a política de privacidade.</span>
                    </label>
                    @error('terms') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="btn-primary btn-lg w-full" :disabled="status === 'taken'">Criar minha conta grátis</button>
            </form>

            <aside class="space-y-4 lg:pt-6">
                <h2 class="text-lg font-bold">O que você ganha no teste</h2>
                @foreach ([
                    ['utensils', 'Cardápio online no seu endereço', 'Compartilhe o link no Instagram e no WhatsApp.'],
                    ['flame', 'Pedidos direto na cozinha', 'Com aviso sonoro e tempo de espera.'],
                    ['pos', 'PDV, mesas e caixa', 'Tudo integrado, do balcão ao salão.'],
                    ['shield', 'Seus dados protegidos', 'Backup diário e acesso por perfil de usuário.'],
                ] as [$icon, $title, $text])
                    <div class="flex gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white text-brand-600 shadow-sm ring-1 ring-stone-200"><x-icon :name="$icon" class="size-5" /></span>
                        <div><p class="font-semibold">{{ $title }}</p><p class="text-sm text-stone-500">{{ $text }}</p></div>
                    </div>
                @endforeach
            </aside>
        </div>
    </section>
</x-layouts.site>
