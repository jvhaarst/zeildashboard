<?php
/**
 * Tests for RSC_Cache class
 */

class Test_RSC_Cache extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        // Clean up any test data
        delete_option( 'rsc_test_key' );
        delete_option( 'rsc_timestamp_test_key' );
        delete_option( 'rsc_test_timestamp' );
        delete_option( 'rsc_timestamp_test_timestamp' );
        delete_option( 'rsc_test_delete' );
        delete_option( 'rsc_timestamp_test_delete' );
        delete_option( 'rsc_old_data' );
        delete_option( 'rsc_timestamp_old_data' );
        delete_option( 'rsc_fresh_data' );
        delete_option( 'rsc_timestamp_fresh_data' );
        delete_option( 'rsc_nonexistent_key_xyz' );
        delete_option( 'rsc_nonexistent_key_never_set' );
        delete_option( 'rsc_timestamp_nonexistent_key_never_set' );
    }

    public function test_cache_stores_and_retrieves_data() {
        RSC_Cache::set( 'test_key', array( 'wind' => '12 kts' ) );
        $result = RSC_Cache::get( 'test_key' );
        $this->assertEquals( array( 'wind' => '12 kts' ), $result );
    }

    public function test_cache_returns_false_for_missing_key() {
        $result = RSC_Cache::get( 'nonexistent_key_xyz' );
        $this->assertFalse( $result );
    }

    public function test_cache_get_timestamp_returns_zero_for_missing_key() {
        $timestamp = RSC_Cache::get_timestamp( 'nonexistent_key_never_set' );
        $this->assertEquals( 0, $timestamp );
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
        // Directly update timestamp to simulate old data
        $old_time = time() - ( 2 * 60 * 60 );
        update_option( 'rsc_timestamp_old_data', $old_time );
        $is_stale = RSC_Cache::is_stale( 'old_data', 3600 ); // 1 hour TTL
        $this->assertTrue( $is_stale );
    }

    public function test_cache_is_not_stale_when_fresh() {
        RSC_Cache::set( 'fresh_data', 'value' );
        $is_stale = RSC_Cache::is_stale( 'fresh_data', 3600 );
        $this->assertFalse( $is_stale );
    }
}
