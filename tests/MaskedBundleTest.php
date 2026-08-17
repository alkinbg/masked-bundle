<?php

declare(strict_types=1);

namespace Alkin\MaskedBundle\Tests;

use Alkin\MaskedBundle\MaskedBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

#[CoversClass(MaskedBundle::class)]
final class MaskedBundleTest extends TestCase
{
	public function testBundleExtendsSymfonyAbstractBundle(): void
	{
		$bundle = new MaskedBundle();

		self::assertInstanceOf(AbstractBundle::class, $bundle);
	}
}