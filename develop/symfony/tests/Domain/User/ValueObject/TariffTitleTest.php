<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\TariffTitle;
use PHPUnit\Framework\TestCase;

final class TariffTitleTest extends TestCase
{
	public function testCreatesValidTitle(): void
	{
		$title = new TariffTitle('  Pro Plan  ');

		$this->assertSame('Pro Plan', $title->value());
		$this->assertSame('Pro Plan', (string) $title);
	}

	public function testThrowsOnEmptyTitle(): void
	{
		$this->expectException(\DomainException::class);

		new TariffTitle('   ');
	}

	public function testThrowsOnTooLongTitle(): void
	{
		$this->expectException(\DomainException::class);

		new TariffTitle(str_repeat('a', 256));
	}

	public function testEquals(): void
	{
		$a = new TariffTitle('Pro Plan');
		$b = new TariffTitle('Pro Plan');
		$c = new TariffTitle('Free Plan');

		$this->assertTrue($a->equals($b));
		$this->assertFalse($a->equals($c));
	}
}

