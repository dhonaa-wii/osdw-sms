<?php

namespace App\Enums;

/**
 * The branding images the system displays, each stored as one row in
 * `branding_images`.
 */
enum BrandingImageKey: string
{
    case Logo = 'logo';
    case FooterBanner = 'footer_banner';

    public function label(): string
    {
        return match ($this) {
            self::Logo => 'OSDW logo',
            self::FooterBanner => 'Footer banner',
        };
    }
}
