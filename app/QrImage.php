<?php

namespace App;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use SensitiveParameter;

class QrImage
{
    /** @return array{url: string, image: string} */
    public function forUrl(#[SensitiveParameter] string $url): array
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(320, 4), new SvgImageBackEnd));

        return ['url' => $url, 'image' => 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($url))];
    }
}
