<?php

namespace App;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
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

    /**
     * Render an email-compatible PNG without requiring GD or Imagick.
     */
    public function pngForUrl(string $url): string
    {
        $matrix = Encoder::encode($url, ErrorCorrectionLevel::L())->getMatrix();
        $scale = 8;
        $margin = 4 * $scale;
        $size = $matrix->getWidth() * $scale + 2 * $margin;
        $whiteMargin = str_repeat("\xff", $margin);
        $whiteRows = str_repeat("\0".str_repeat("\xff", $size), $margin);
        $pixels = $whiteRows;

        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            $row = $whiteMargin;
            for ($x = 0; $x < $matrix->getWidth(); $x++) {
                $row .= str_repeat($matrix->get($x, $y) === 1 ? "\0" : "\xff", $scale);
            }
            $pixels .= str_repeat("\0".$row.$whiteMargin, $scale);
        }
        $pixels .= $whiteRows;

        return "\x89PNG\r\n\x1a\n"
            .$this->pngChunk('IHDR', pack('NNC5', $size, $size, 8, 0, 0, 0, 0))
            .$this->pngChunk('IDAT', gzcompress($pixels))
            .$this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.hash('crc32b', $type.$data, true);
    }
}
