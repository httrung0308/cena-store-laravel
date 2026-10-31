<?php

namespace Tests\Unit;

use App\Support\ShoeSizeSequence;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ShoeSizeSequenceTest extends TestCase
{
    public function test_fractional_size_sequence_and_leading_zero_are_canonical(): void
    {
        $this->assertSame(['40', '40.5', '41'], ShoeSizeSequence::between('040', '41'));
        $this->assertSame(['40.5'], ShoeSizeSequence::between('40.5', '40.5'));
    }

    public function test_reversed_size_interval_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ShoeSizeSequence::between('41', '40');
    }
}
