<?php
/**
 * Performance benchmarks and load tests
 *
 * @package RSL_Licensing
 */

namespace RSL\Tests\Performance;

use RSL\Tests\TestCase;
use RSL_License;
use RSL_Server;
use RSL_OAuth_Client;
use WP_REST_Request;

/**
 * Test performance characteristics and benchmarks
 *
 * @group performance
 * @group benchmarks
 */
class TestPerformanceBenchmarks extends TestCase {

	/**
	 * Performance threshold constants (in milliseconds)
	 */
	const TOKEN_GENERATION_THRESHOLD   = 100;
	const LICENSE_VALIDATION_THRESHOLD = 50;
	const XML_GENERATION_THRESHOLD     = 200;
	const DATABASE_QUERY_THRESHOLD     = 10;
	const API_RESPONSE_THRESHOLD       = 200;

	/**
	 * License handler instance
	 *
	 * @var RSL_License
	 */
	private $license_handler;

	/**
	 * Server instance
	 *
	 * @var RSL_Server
	 */
	private $server;

	/**
	 * OAuth client instance
	 *
	 * @var RSL_OAuth_Client
	 */
	private $oauth_client;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		$this->license_handler = new RSL_License();
		$this->server          = new RSL_Server();
		$this->oauth_client    = RSL_OAuth_Client::get_instance();

		// Warm up the system
		$this->warm_up_system();
	}

	/**
	 * Warm up system to get consistent benchmarks
	 */
	private function warm_up_system() {
		// Create a test license to warm up database connections
		$warmup_license_id = $this->create_test_license( array( 'name' => 'Warmup License' ) );
		$this->license_handler->get_license( $warmup_license_id );
		$this->license_handler->delete_license( $warmup_license_id );
	}

	/**
	 * Benchmark license creation performance
	 *
	 * @covers RSL_License::create_license
	 */
	public function test_license_creation_performance() {
		$iterations = 100;
		$total_time = 0;

		for ( $i = 0; $i < $iterations; $i++ ) {
			$start_time = microtime( true );

			$license_id = $this->license_handler->create_license(
				array(
					'name'         => "Performance Test License {$i}",
					'description'  => 'Performance testing license',
					'content_url'  => "/perf-test-{$i}",
					'payment_type' => 'free',
				)
			);

			$end_time       = microtime( true );
			$execution_time = ( $end_time - $start_time ) * 1000; // Convert to milliseconds
			$total_time    += $execution_time;

			$this->assertIsInt( $license_id );
			$this->assertLessThan( 50, $execution_time, "License creation took too long: {$execution_time}ms" );
		}

		$average_time = $total_time / $iterations;
		$this->assertLessThan( 20, $average_time, "Average license creation time too slow: {$average_time}ms" );

		echo "\nLicense Creation Performance:\n";
		echo '  Average time: ' . number_format( $average_time, 2 ) . "ms\n";
		echo '  Total time: ' . number_format( $total_time, 2 ) . "ms for {$iterations} operations\n";
	}

	/**
	 * Benchmark license retrieval performance
	 *
	 * @covers RSL_License::get_license
	 */
	public function test_license_retrieval_performance() {
		// Create test licenses
		$license_ids = array();
		for ( $i = 0; $i < 50; $i++ ) {
			$license_ids[] = $this->create_test_license( array( 'name' => "Retrieval Test {$i}" ) );
		}

		$iterations = 1000;
		$total_time = 0;

		for ( $i = 0; $i < $iterations; $i++ ) {
			$license_id = $license_ids[ array_rand( $license_ids ) ];

			$start_time = microtime( true );
			$license    = $this->license_handler->get_license( $license_id );
			$end_time   = microtime( true );

			$execution_time = ( $end_time - $start_time ) * 1000;
			$total_time    += $execution_time;

			$this->assertIsArray( $license );
			$this->assertLessThan(
				self::DATABASE_QUERY_THRESHOLD,
				$execution_time,
				"License retrieval took too long: {$execution_time}ms"
			);
		}

		$average_time = $total_time / $iterations;
		$this->assertLessThan( 5, $average_time, "Average license retrieval time too slow: {$average_time}ms" );

		echo "\nLicense Retrieval Performance:\n";
		echo '  Average time: ' . number_format( $average_time, 2 ) . "ms\n";
		echo '  Total time: ' . number_format( $total_time, 2 ) . "ms for {$iterations} operations\n";
	}

	/**
	 * Benchmark XML generation performance
	 *
	 * @covers RSL_License::generate_rsl_xml
	 */
	public function test_xml_generation_performance() {
		$license_data = array(
			'id'               => 1,
			'name'             => 'XML Performance Test License',
			'description'      => 'This is a comprehensive license for performance testing with various fields populated',
			'content_url'      => '/xml-performance-test',
			'payment_type'     => 'purchase',
			'amount'           => 199.99,
			'currency'         => 'USD',
			'permits_usage'    => 'train-ai,ai-use,search,ai-summarize',
			'permits_user'     => 'commercial,non-commercial',
			'permits_geo'      => 'US,CA,GB,AU',
			'prohibits_usage'  => 'train-genai',
			'prohibits_user'   => '',
			'prohibits_geo'    => 'CN,RU',
			'warranty'         => 'ownership,authority,accuracy',
			'disclaimer'       => 'as-is,no-warranty,limitation-liability',
			'copyright_holder' => 'Performance Test Organization',
			'copyright_type'   => 'organization',
			'contact_email'    => 'test@example.com',
			'contact_url'      => 'https://example.com/contact',
			'terms_url'        => 'https://example.com/terms',
			'schema_url'       => 'https://schema.org/CreativeWork',
		);

		$iterations = 500;
		$total_time = 0;

		for ( $i = 0; $i < $iterations; $i++ ) {
			$start_time = microtime( true );
			$xml        = $this->license_handler->generate_rsl_xml( $license_data );
			$end_time   = microtime( true );

			$execution_time = ( $end_time - $start_time ) * 1000;
			$total_time    += $execution_time;

			$this->assertValidRslXml( $xml );
			$this->assertLessThan(
				self::XML_GENERATION_THRESHOLD,
				$execution_time,
				"XML generation took too long: {$execution_time}ms"
			);
		}

		$average_time = $total_time / $iterations;
		$this->assertLessThan( 100, $average_time, "Average XML generation time too slow: {$average_time}ms" );

		echo "\nXML Generation Performance:\n";
		echo '  Average time: ' . number_format( $average_time, 2 ) . "ms\n";
		echo '  Total time: ' . number_format( $total_time, 2 ) . "ms for {$iterations} operations\n";
		echo '  Average XML size: ' . number_format( strlen( $xml ) / 1024, 2 ) . " KB\n";
	}

	/**
	 * Benchmark JWT token generation performance
	 *
	 * @covers RSL_Server::jwt_encode_payload
	 */
	public function test_jwt_token_generation_performance() {
		$payload = array(
			'iss'     => 'http://example.org',
			'aud'     => 'example.org',
			'sub'     => 'performance-test-client',
			'iat'     => time(),
			'exp'     => time() + 3600,
			'lic'     => 1,
			'scope'   => 'train-ai,ai-use',
			'pattern' => '/performance-test',
		);

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'jwt_encode_payload' );

		$iterations = 1000;
		$total_time = 0;

		for ( $i = 0; $i < $iterations; $i++ ) {
			$start_time = microtime( true );
			$token      = $method->invoke( $this->server, $payload );
			$end_time   = microtime( true );

			$execution_time = ( $end_time - $start_time ) * 1000;
			$total_time    += $execution_time;

			$this->assertIsString( $token );
			$this->assertLessThan(
				self::TOKEN_GENERATION_THRESHOLD,
				$execution_time,
				"JWT generation took too long: {$execution_time}ms"
			);
		}

		$average_time = $total_time / $iterations;
		$this->assertLessThan( 50, $average_time, "Average JWT generation time too slow: {$average_time}ms" );

		echo "\nJWT Token Generation Performance:\n";
		echo '  Average time: ' . number_format( $average_time, 2 ) . "ms\n";
		echo '  Total time: ' . number_format( $total_time, 2 ) . "ms for {$iterations} operations\n";
	}

	/**
	 * Benchmark JWT token validation performance
	 *
	 * @covers RSL_Server::jwt_decode_token
	 */
	public function test_jwt_token_validation_performance() {
		// Generate test token
		$token = $this->generate_test_jwt(
			array(
				'sub' => 'validation-test-client',
				'lic' => 1,
				'exp' => time() + 3600,
			)
		);

		// Use reflection to access private method
		$reflection = new \ReflectionClass( $this->server );
		$method     = $reflection->getMethod( 'jwt_decode_token' );

		$iterations = 1000;
		$total_time = 0;

		for ( $i = 0; $i < $iterations; $i++ ) {
			$start_time = microtime( true );
			$payload    = $method->invoke( $this->server, $token );
			$end_time   = microtime( true );

			$execution_time = ( $end_time - $start_time ) * 1000;
			$total_time    += $execution_time;

			$this->assertIsArray( $payload );
			$this->assertLessThan(
				self::LICENSE_VALIDATION_THRESHOLD,
				$execution_time,
				"JWT validation took too long: {$execution_time}ms"
			);
		}

		$average_time = $total_time / $iterations;
		$this->assertLessThan( 25, $average_time, "Average JWT validation time too slow: {$average_time}ms" );

		echo "\nJWT Token Validation Performance:\n";
		echo '  Average time: ' . number_format( $average_time, 2 ) . "ms\n";
		echo '  Total time: ' . number_format( $total_time, 2 ) . "ms for {$iterations} operations\n";
	}

	/**
	 * Benchmark OAuth client validation performance
	 *
	 * @covers RSL_OAuth_Client::validate_client
	 */
	public function test_oauth_client_validation_performance() {
		$client_data = $this->oauth_client->create_client( 'Performance Test Client' );

		$iterations = 500;
		$total_time = 0;

		for ( $i = 0; $i < $iterations; $i++ ) {
			$start_time = microtime( true );
			$result     = $this->oauth_client->validate_client(
				$client_data['client_id'],
				$client_data['client_secret']
			);
			$end_time   = microtime( true );

			$execution_time = ( $end_time - $start_time ) * 1000;
			$total_time    += $execution_time;

			$this->assertTrue( $result );
			$this->assertLessThan(
				100,
				$execution_time,
				"OAuth validation took too long: {$execution_time}ms"
			);
		}

		$average_time = $total_time / $iterations;
		$this->assertLessThan( 50, $average_time, "Average OAuth validation time too slow: {$average_time}ms" );

		echo "\nOAuth Client Validation Performance:\n";
		echo '  Average time: ' . number_format( $average_time, 2 ) . "ms\n";
		echo '  Total time: ' . number_format( $total_time, 2 ) . "ms for {$iterations} operations\n";
	}

	/**
	 * Benchmark API endpoint response times
	 *
	 * @covers RSL_Server REST API endpoints
	 */
	public function test_api_endpoint_performance() {
		// Set up REST server
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init' );

		$license_id = $this->create_test_license(
			array(
				'name'         => 'API Performance License',
				'payment_type' => 'free',
			)
		);

		$endpoints = array(
			array( 'GET', '/rsl/v1/licenses' ),
			array( 'GET', "/rsl/v1/licenses/{$license_id}" ),
			array( 'POST', '/rsl/v1/validate', array( 'content_url' => 'http://example.org/test' ) ),
		);

		foreach ( $endpoints as $endpoint_config ) {
			[$method, $route] = $endpoint_config;
			$body             = $endpoint_config[2] ?? null;

			$iterations = 100;
			$total_time = 0;

			for ( $i = 0; $i < $iterations; $i++ ) {
				$request = new WP_REST_Request( $method, $route );

				if ( $body ) {
					$request->set_header( 'Content-Type', 'application/json' );
					$request->set_body( json_encode( $body ) );
				}

				$start_time = microtime( true );
				$response   = $wp_rest_server->dispatch( $request );
				$end_time   = microtime( true );

				$execution_time = ( $end_time - $start_time ) * 1000;
				$total_time    += $execution_time;

				$this->assertLessThanOrEqual( 400, $response->get_status() );
				$this->assertLessThan(
					self::API_RESPONSE_THRESHOLD,
					$execution_time,
					"API endpoint {$method} {$route} took too long: {$execution_time}ms"
				);
			}

			$average_time = $total_time / $iterations;
			$this->assertLessThan(
				100,
				$average_time,
				"Average API response time too slow for {$method} {$route}: {$average_time}ms"
			);

			echo "\nAPI Endpoint Performance ({$method} {$route}):\n";
			echo '  Average time: ' . number_format( $average_time, 2 ) . "ms\n";
		}
	}

	/**
	 * Test concurrent request handling
	 *
	 * @covers RSL_Server concurrent request handling
	 */
	public function test_concurrent_request_simulation() {
		$license_id = $this->create_test_license(
			array(
				'name'         => 'Concurrent Test License',
				'payment_type' => 'free',
			)
		);

		$concurrent_requests = 50;
		$start_time          = microtime( true );

		// Simulate concurrent requests (in actual concurrent environment, use proper threading)
		$results = array();
		for ( $i = 0; $i < $concurrent_requests; $i++ ) {
			$request = new WP_REST_Request( 'POST', '/rsl-olp/v1/token' );
			$request->set_param( 'license_id', $license_id );
			$request->set_param( 'resource', 'http://example.org/concurrent-test' );
			$request->set_param( 'client', "concurrent-client-{$i}" );

			$request_start = microtime( true );
			$response      = $this->server->olp_issue_token( $request );
			$request_end   = microtime( true );

			$results[] = array(
				'response' => $response,
				'time'     => ( $request_end - $request_start ) * 1000,
			);
		}

		$end_time   = microtime( true );
		$total_time = ( $end_time - $start_time ) * 1000;

		// Analyze results
		$successful_requests = 0;
		$total_request_time  = 0;
		$max_time            = 0;
		$min_time            = PHP_FLOAT_MAX;

		foreach ( $results as $result ) {
			if ( ! is_wp_error( $result['response'] ) ) {
				++$successful_requests;
			}

			$total_request_time += $result['time'];
			$max_time            = max( $max_time, $result['time'] );
			$min_time            = min( $min_time, $result['time'] );
		}

		$success_rate         = ( $successful_requests / $concurrent_requests ) * 100;
		$average_request_time = $total_request_time / $concurrent_requests;

		$this->assertGreaterThan( 95, $success_rate, "Success rate too low: {$success_rate}%" );
		$this->assertLessThan( 500, $average_request_time, "Average request time too slow: {$average_request_time}ms" );

		echo "\nConcurrent Request Performance:\n";
		echo "  Total requests: {$concurrent_requests}\n";
		echo "  Successful requests: {$successful_requests}\n";
		echo '  Success rate: ' . number_format( $success_rate, 1 ) . "%\n";
		echo '  Total time: ' . number_format( $total_time, 2 ) . "ms\n";
		echo '  Average request time: ' . number_format( $average_request_time, 2 ) . "ms\n";
		echo '  Min request time: ' . number_format( $min_time, 2 ) . "ms\n";
		echo '  Max request time: ' . number_format( $max_time, 2 ) . "ms\n";
		echo '  Requests per second: ' . number_format( $concurrent_requests / ( $total_time / 1000 ), 2 ) . "\n";
	}

	/**
	 * Test memory usage during operations
	 *
	 * @covers Memory usage optimization
	 */
	public function test_memory_usage_optimization() {
		$initial_memory = memory_get_usage( true );
		$peak_memory    = $initial_memory;

		// Perform memory-intensive operations
		$licenses = array();
		for ( $i = 0; $i < 100; $i++ ) {
			$license_id = $this->create_test_license(
				array(
					'name'        => "Memory Test License {$i}",
					'description' => str_repeat( 'Memory test description ', 100 ), // ~2.5KB per license
				)
			);

			$license_data = $this->license_handler->get_license( $license_id );
			$xml          = $this->license_handler->generate_rsl_xml( $license_data );

			$licenses[] = array(
				'id'   => $license_id,
				'data' => $license_data,
				'xml'  => $xml,
			);

			$current_memory = memory_get_usage( true );
			$peak_memory    = max( $peak_memory, $current_memory );

			// Force garbage collection periodically
			if ( $i % 10 === 0 ) {
				gc_collect_cycles();
			}
		}

		$final_memory    = memory_get_usage( true );
		$memory_increase = $final_memory - $initial_memory;
		$peak_increase   = $peak_memory - $initial_memory;

		// Memory usage should be reasonable (less than 50MB for 100 licenses)
		$max_acceptable_memory = 50 * 1024 * 1024; // 50MB
		$this->assertLessThan(
			$max_acceptable_memory,
			$memory_increase,
			'Memory usage too high: ' . number_format( $memory_increase / 1024 / 1024, 2 ) . 'MB'
		);

		echo "\nMemory Usage Analysis:\n";
		echo '  Initial memory: ' . number_format( $initial_memory / 1024 / 1024, 2 ) . " MB\n";
		echo '  Final memory: ' . number_format( $final_memory / 1024 / 1024, 2 ) . " MB\n";
		echo '  Peak memory: ' . number_format( $peak_memory / 1024 / 1024, 2 ) . " MB\n";
		echo '  Memory increase: ' . number_format( $memory_increase / 1024 / 1024, 2 ) . " MB\n";
		echo '  Peak increase: ' . number_format( $peak_increase / 1024 / 1024, 2 ) . " MB\n";
		echo '  Average per license: ' . number_format( $memory_increase / 100 / 1024, 2 ) . " KB\n";
	}

	/**
	 * Test database query optimization
	 *
	 * @covers Database query performance
	 */
	public function test_database_query_optimization() {
		global $wpdb;

		// Create test data
		for ( $i = 0; $i < 1000; $i++ ) {
			$this->create_test_license(
				array(
					'name'        => "DB Test License {$i}",
					'content_url' => "/db-test-{$i}",
					'active'      => ( $i % 10 === 0 ) ? 0 : 1, // 10% inactive
				)
			);
		}

		// Test query performance
		$query_tests = array(
			'get_all_licenses'    => function () {
				return $this->license_handler->get_licenses();
			},
			'get_active_licenses' => function () {
				return $this->license_handler->get_licenses( array( 'active' => 1 ) );
			},
			'get_single_license'  => function () use ( $wpdb ) {
				$random_id = $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}rsl_licenses ORDER BY RAND() LIMIT 1" );
				return $this->license_handler->get_license( $random_id );
			},
		);

		foreach ( $query_tests as $test_name => $test_function ) {
			$iterations        = 50;
			$total_time        = 0;
			$query_count_start = $wpdb->num_queries;

			for ( $i = 0; $i < $iterations; $i++ ) {
				$start_time = microtime( true );
				$result     = $test_function();
				$end_time   = microtime( true );

				$execution_time = ( $end_time - $start_time ) * 1000;
				$total_time    += $execution_time;

				$this->assertNotEmpty( $result, "Query test {$test_name} returned empty result" );
			}

			$query_count_end       = $wpdb->num_queries;
			$queries_per_iteration = ( $query_count_end - $query_count_start ) / $iterations;
			$average_time          = $total_time / $iterations;

			$this->assertLessThan(
				100,
				$average_time,
				"Database query too slow for {$test_name}: {$average_time}ms"
			);

			echo "\nDatabase Query Performance ({$test_name}):\n";
			echo '  Average time: ' . number_format( $average_time, 2 ) . "ms\n";
			echo '  Queries per operation: ' . number_format( $queries_per_iteration, 2 ) . "\n";
		}
	}

	/**
	 * Performance regression test
	 *
	 * @covers Overall performance regression
	 */
	public function test_performance_regression() {
		// Baseline performance metrics (adjust based on your requirements)
		$benchmarks = array(
			'license_creation'  => 20, // milliseconds
			'license_retrieval' => 5,
			'xml_generation'    => 100,
			'jwt_generation'    => 50,
			'jwt_validation'    => 25,
			'api_response'      => 100,
		);

		$results = array();

		// Quick performance check for each operation
		foreach ( $benchmarks as $operation => $threshold ) {
			$start_time = microtime( true );

			switch ( $operation ) {
				case 'license_creation':
					$this->create_test_license( array( 'name' => 'Regression Test License' ) );
					break;

				case 'license_retrieval':
					$license_id = $this->create_test_license( array( 'name' => 'Retrieval Test' ) );
					$this->license_handler->get_license( $license_id );
					break;

				case 'xml_generation':
					$license_data = array(
						'id'          => 1,
						'name'        => 'XML Test',
						'content_url' => '/test',
					);
					$this->license_handler->generate_rsl_xml( $license_data );
					break;

				case 'jwt_generation':
					$payload    = array(
						'sub' => 'test',
						'lic' => 1,
						'exp' => time() + 3600,
					);
					$reflection = new \ReflectionClass( $this->server );
					$method     = $reflection->getMethod( 'jwt_encode_payload' );
					$method->invoke( $this->server, $payload );
					break;

				case 'jwt_validation':
					$token      = $this->generate_test_jwt( array( 'sub' => 'test' ) );
					$reflection = new \ReflectionClass( $this->server );
					$method     = $reflection->getMethod( 'jwt_decode_token' );
					$method->invoke( $this->server, $token );
					break;

				case 'api_response':
					global $wp_rest_server;
					if ( ! $wp_rest_server ) {
						$wp_rest_server = new \WP_REST_Server();
						do_action( 'rest_api_init' );
					}
					$request = new WP_REST_Request( 'GET', '/rsl/v1/licenses' );
					$wp_rest_server->dispatch( $request );
					break;
			}

			$end_time              = microtime( true );
			$execution_time        = ( $end_time - $start_time ) * 1000;
			$results[ $operation ] = $execution_time;

			$this->assertLessThan(
				$threshold,
				$execution_time,
				"Performance regression detected in {$operation}: {$execution_time}ms > {$threshold}ms threshold"
			);
		}

		echo "\nPerformance Regression Test Results:\n";
		foreach ( $results as $operation => $time ) {
			$threshold = $benchmarks[ $operation ];
			$status    = $time < $threshold ? 'PASS' : 'FAIL';
			echo "  {$operation}: " . number_format( $time, 2 ) . "ms (threshold: {$threshold}ms) [{$status}]\n";
		}
	}
}
