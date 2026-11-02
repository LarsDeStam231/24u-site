<?php

namespace Tests\Unit;

use App\QrImage;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use PHPUnit\Framework\TestCase;

class QrImageTest extends TestCase
{
    public function test_png_contains_the_original_qr_matrix_and_a_four_module_quiet_zone(): void
    {
        $url = 'https://wensstichtingdrenthe.nl/doneren';
        $matrix = Encoder::encode($url, ErrorCorrectionLevel::L())->getMatrix();

        $png = (new QrImage)->pngForUrl($url);

        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        $image = getimagesizefromstring($png);
        $this->assertSame('image/png', $image['mime']);
        $this->assertSame(($matrix->getWidth() + 8) * 8, $image[0]);
        $this->assertSame($image[0], $image[1]);
        $compressed = '';
        for ($offset = 8; $offset < strlen($png);) {
            $length = unpack('N', substr($png, $offset, 4))[1];
            $type = substr($png, $offset + 4, 4);
            $data = substr($png, $offset + 8, $length);
            $this->assertSame(hash('crc32b', $type.$data, true), substr($png, $offset + 8 + $length, 4));
            if ($type === 'IDAT') {
                $compressed .= $data;
            }
            $offset += 12 + $length;
        }
        $pixels = gzuncompress($compressed);
        $rowLength = $image[0] + 1;
        $this->assertSame($rowLength * $image[1], strlen($pixels));
        $this->assertSame(str_repeat("\0".str_repeat("\xff", $image[0]), 32), substr($pixels, 0, $rowLength * 32));
        $this->assertSame(substr($pixels, 0, $rowLength * 32), substr($pixels, -$rowLength * 32));

        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            $rowOffset = (32 + $y * 8) * $rowLength;
            $row = substr($pixels, $rowOffset, $rowLength);
            $this->assertSame("\0".str_repeat("\xff", 32), substr($row, 0, 33));
            $this->assertSame(str_repeat("\xff", 32), substr($row, -32));
            $this->assertSame(str_repeat($row, 8), substr($pixels, $rowOffset, $rowLength * 8));
            for ($x = 0; $x < $matrix->getWidth(); $x++) {
                $expected = $matrix->get($x, $y) === 1 ? "\0" : "\xff";
                $this->assertSame(str_repeat($expected, 8), substr($row, 33 + $x * 8, 8));
            }
        }
    }
}
