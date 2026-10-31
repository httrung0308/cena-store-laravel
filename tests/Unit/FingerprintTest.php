<?php

namespace Tests\Unit;

use App\Enums\PaymentMethod;
use App\Exceptions\DomainException;
use App\Support\CanonicalPayload;
use App\Support\ShoeSize;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

class FingerprintTest extends TestCase
{
    public function test_same_normalized_data_has_same_fingerprint_despite_recursive_key_order(): void
    {
        $first = ['cart_version' => 3, 'address' => ['ward_code' => '00001', 'line' => 'Hồ Chí Minh'], 'method' => PaymentMethod::Cod, 'size' => ShoeSize::normalize('040.5000'), 'at' => CarbonImmutable::parse('2026-10-31T10:00:00+07:00')];
        $second = ['at' => CarbonImmutable::parse('2026-10-31T03:00:00Z'), 'size' => '40.5', 'method' => 'COD', 'address' => ['line' => 'Hồ Chí Minh', 'ward_code' => '00001'], 'cart_version' => 3];
        $hash = CanonicalPayload::fingerprint($first);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $hash);
        $this->assertSame($hash, CanonicalPayload::fingerprint($second));
    }

    public function test_cart_address_payment_coupon_and_list_order_changes_remain_distinguishable(): void
    {
        $payload = ['cart_version' => 3, 'address' => ['line' => 'A'], 'payment_method' => 'COD', 'coupon' => null, 'lines' => [1, 2]];
        $hash = CanonicalPayload::fingerprint($payload);
        foreach (['cart_version' => 4, 'address' => ['line' => 'B'], 'payment_method' => 'VIETQR', 'coupon' => 'SALE', 'lines' => [2, 1]] as $key => $value) {
            $this->assertNotSame($hash, CanonicalPayload::fingerprint([...$payload, $key => $value]));
        }
        $this->assertNotSame(CanonicalPayload::fingerprint(['id' => 1]), CanonicalPayload::fingerprint(['id' => '1']));
        $this->assertNotSame(CanonicalPayload::fingerprint(['x' => null]), CanonicalPayload::fingerprint(['x' => false]));
    }

    #[DataProvider('invalidPayloads')]
    public function test_ambiguous_or_unserializable_data_is_rejected(array $payload): void
    {
        $this->expectException(DomainException::class);
        CanonicalPayload::fingerprint($payload);
    }

    public static function invalidPayloads(): iterable
    {
        yield 'float' => [['amount' => 40.5]];
        yield 'nonfinite float' => [['amount' => INF]];
        yield 'arbitrary object' => [['object' => new stdClass]];
        yield 'invalid utf8' => [['text' => "\xB1\x31"]];
        $deep = ['x' => 1];
        for ($index = 0; $index < 34; $index++) {
            $deep = ['child' => $deep];
        }
        yield 'excess depth' => [$deep];
    }
}
