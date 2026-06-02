<?php
/**
 * Tests for RSC_Cache class
 */

class Test_RSC_Cache extends WP_UnitTestCase {

    public function test_cache_stores_and_retrieves_data() {
        RSC_Cache::set( 'test_key', array( 'wind' => '12 kts' ) );
        $result = RSC_Cache::get( 'test_key' );
        $this->assertEquals( array( 'wind' => '12 kts' ), $result );
    }

    public function test_cache_returns_false_for_missing_key() {
        $result = RSC_Cache::get( 'nonexistent_key_xyz' );
        $this->assertFalse( $result );
    }

    public function test_cache_stores_timestamp() {
        RSC_Cache::set( 'test_timestamp', array( 'value' => 'data' ) );
        $timestamp = RSC_Cache::get_timestamp( 'test_timestamp' );
        $this->assertIsInt( $timestamp );
        $this->assertGreater( $timestamp, 0 );
    }

    public function test_cache_delete() {
        RSC_Cache::set( 'test_delete', 'value' );
        RSC_Cache::delete( 'test_delete' );
        $result = RSC_Cache::get( 'test_delete' );
        $this->assertFalse( $result );
    }

    public function test_cache_is_stale() {
        RSC_Cache::set( 'old_data', 'value' );
        // Modify timestamp to 2 hours ago
        $old_time = time() - ( 2 * 60 * 60 );
        update_option( 'rsc_timestamp_old_data', $old_time );
        $is_stale = RSC_Cache::is_stale( 'old_data', 3600 ); // 1 hour TTL
        $this->assertTrue( $is_stale );
    }
}
