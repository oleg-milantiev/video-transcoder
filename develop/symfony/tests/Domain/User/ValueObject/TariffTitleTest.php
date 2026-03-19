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
}

