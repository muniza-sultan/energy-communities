<?php

namespace Tests\Feature\Rules;

use App\Models\GridOperator;
use App\Rules\KnownGridOperator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnownGridOperatorTest extends TestCase
{
    use RefreshDatabase;

    private function passes(string $code): bool
    {
        return Validator::make(['name' => $code], ['name' => [new KnownGridOperator]])->passes();
    }

    #[Test]
    public function it_accepts_a_code_of_an_existing_operator(): void
    {
        GridOperator::factory()->create(['identifier' => 'AT003000']);

        $this->assertTrue($this->passes('AT0030000000000000000000004711001'));
    }

    #[Test]
    public function it_rejects_an_unknown_operator(): void
    {
        GridOperator::factory()->create(['identifier' => 'AT003000']);

        $this->assertFalse($this->passes('AT0040000000000000000000004711001'));
    }

    #[Test]
    public function it_rejects_a_soft_deleted_operator(): void
    {
        GridOperator::factory()->create(['identifier' => 'AT003000'])->delete();

        $this->assertFalse($this->passes('AT0030000000000000000000004711001'));
    }
}
