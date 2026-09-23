<?php

namespace Tests\Feature\Rules;

use App\Rules\MeterPointCode;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MeterPointCodeTest extends TestCase
{
    private function passes(mixed $value): bool
    {
        return Validator::make(['name' => $value], ['name' => [new MeterPointCode]])->passes();
    }

    #[Test]
    public function it_accepts_the_codes_from_the_brief(): void
    {
        $this->assertTrue($this->passes('AT0030000000000000000000004711001'));
        $this->assertTrue($this->passes('AT0030000000000000000000004711002'));
    }

    public static function invalidCodes(): array
    {
        return [
            '32 characters' => ['AT003000000000000000000000471100'],
            '34 characters' => ['AT00300000000000000000000047110011'],
            'lowercase' => ['at0030000000000000000000004711001'],
            'special character' => ['AT003000-000000000000000004711001'],
            'space' => ['AT003000 000000000000000004711001'],
            'trailing newline' => ["AT0030000000000000000000004711001\n"],
            'umlaut' => ['AT00300000000000000000000047110Ä1'],
            'integer' => [123],
        ];
    }

    #[Test]
    #[DataProvider('invalidCodes')]
    public function it_rejects_invalid_codes(mixed $value): void
    {
        $this->assertFalse($this->passes($value));
    }
}
