<?php

namespace WeDevs\WPKit\Tests\DataLayer;

use WeDevs\WPKit\DataLayer\QueryResult;
use WeDevs\WPKit\Tests\TestCase;

class QueryResultTest extends TestCase {

	private function make_items( int $count ): array {
		$items = [];
		for ( $i = 1; $i <= $count; $i++ ) {
			$items[] = (object) [ 'id' => $i, 'title' => "Item {$i}" ];
		}
		return $items;
	}

	public function test_accessors_return_correct_values(): void {
		$result = new QueryResult( $this->make_items( 3 ), 30, 10, 2, 3 );

		$this->assertCount( 3, $result->items() );
		$this->assertSame( 30, $result->total() );
		$this->assertSame( 10, $result->per_page() );
		$this->assertSame( 2, $result->current_page() );
		$this->assertSame( 3, $result->total_pages() );
	}

	public function test_has_more_returns_true_when_more_pages(): void {
		$result = new QueryResult( [], 30, 10, 1, 3 );

		$this->assertTrue( $result->has_more() );
	}

	public function test_has_more_returns_false_on_last_page(): void {
		$result = new QueryResult( [], 30, 10, 3, 3 );

		$this->assertFalse( $result->has_more() );
	}

	public function test_is_empty(): void {
		$empty = new QueryResult( [], 0, 10, 1, 0 );
		$full  = new QueryResult( $this->make_items( 1 ), 1, 10, 1, 1 );

		$this->assertTrue( $empty->is_empty() );
		$this->assertFalse( $full->is_empty() );
	}

	public function test_first_and_last(): void {
		$items  = $this->make_items( 3 );
		$result = new QueryResult( $items, 3, 10, 1, 1 );

		$this->assertSame( $items[0], $result->first() );
		$this->assertSame( $items[2], $result->last() );
	}

	public function test_first_returns_null_when_empty(): void {
		$result = new QueryResult( [], 0, 10, 1, 0 );

		$this->assertNull( $result->first() );
		$this->assertNull( $result->last() );
	}

	public function test_countable_interface(): void {
		$result = new QueryResult( $this->make_items( 5 ), 50, 10, 1, 5 );

		$this->assertCount( 5, $result );
		$this->assertSame( 5, count( $result ) );
	}

	public function test_iterable_interface(): void {
		$items  = $this->make_items( 3 );
		$result = new QueryResult( $items, 3, 10, 1, 1 );

		$collected = [];
		foreach ( $result as $item ) {
			$collected[] = $item;
		}

		$this->assertCount( 3, $collected );
		$this->assertSame( $items[0], $collected[0] );
	}

	public function test_to_array(): void {
		$items  = $this->make_items( 2 );
		$result = new QueryResult( $items, 20, 10, 1, 2 );

		$array = $result->to_array();

		$this->assertSame( $items, $array['items'] );
		$this->assertSame( 20, $array['total'] );
		$this->assertSame( 10, $array['per_page'] );
		$this->assertSame( 1, $array['current_page'] );
		$this->assertSame( 2, $array['total_pages'] );
	}

	public function test_from_array(): void {
		$result = QueryResult::from_array( [
			'items'        => $this->make_items( 2 ),
			'total'        => 20,
			'per_page'     => 10,
			'current_page' => 1,
			'total_pages'  => 2,
		] );

		$this->assertCount( 2, $result );
		$this->assertSame( 20, $result->total() );
		$this->assertTrue( $result->has_more() );
	}

	public function test_pluck(): void {
		$item1 = new class {
			public function get_name(): string { return 'Alice'; }
		};
		$item2 = new class {
			public function get_name(): string { return 'Bob'; }
		};

		$result = new QueryResult( [ $item1, $item2 ], 2, 10, 1, 1 );

		$this->assertSame( [ 'Alice', 'Bob' ], $result->pluck( 'get_name' ) );
	}
}
