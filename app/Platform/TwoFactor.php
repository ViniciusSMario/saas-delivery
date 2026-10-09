<?php

namespace App\Platform;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

/** TOTP (Google Authenticator, Authy, 1Password...) da equipe do SaaS. */
class TwoFactor
{
    public function __construct(private readonly Google2FA $engine = new Google2FA)
    {
    }

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);

        return strlen($code) === 6 && $this->engine->verifyKey($secret, $code, 1) !== false;
    }

    public function currentCode(string $secret): string
    {
        return $this->engine->getCurrentOtp($secret);
    }

    public function qrCodeSvg(string $email, string $secret): string
    {
        $uri = $this->engine->getQRCodeUrl(config('app.name').' Admin', $email, $secret);

        return (new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd)))->writeString($uri);
    }
}
