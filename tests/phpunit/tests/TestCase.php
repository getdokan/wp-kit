<?php

namespace WeDevs\WPKit\Tests;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Brain\Monkey;

/**
 * Base test case for WPKit unit tests.
 *
 * Sets up Brain\Monkey for WordPress function mocking and
 * Mockery for class mocking. All test classes should extend this.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
