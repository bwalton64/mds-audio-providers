<?php
/**
 * Standalone checks for plugin debug logging and cache inspection.
 *
 * Run with: php tests/logging.php
 */

if ( isset( $argv[1] ) ) {
    define( 'ABSPATH', __DIR__ );
    if ( 'undefined' !== $argv[1] ) {
        define( 'WP_DEBUG', 'on' === $argv[1] );
    }
    if ( 'undefined' !== $argv[2] ) {
        define( 'WP_DEBUG_LOG', 'path' === $argv[2] ? $argv[3] : 'on' === $argv[2] );
    }
    ini_set( 'error_log', $argv[3] );

    require __DIR__ . '/../src/Core/Logging.php';

    $result = MDS\Bandcamp\Core\Logging::log( 'error', 'Fetch failed', array( 'endpoint' => 'album' ) );
    if ( 'on' === $argv[1] && in_array( $argv[2], array( 'on', 'path' ), true ) ) {
        if ( ! $result ) {
            exit( 1 );
        }
        if ( MDS\Bandcamp\Core\Logging::log( 'warning', 'Bad context', array( 'invalid' => fopen( 'php://memory', 'r' ) ) ) ) {
            exit( 1 );
        }
    } elseif ( $result ) {
        exit( 1 );
    }
    exit( 0 );
}

$cases = array(
    array( 'undefined', 'undefined', false ),
    array( 'off', 'on', false ),
    array( 'on', 'off', false ),
    array( 'on', 'undefined', false ),
    array( 'on', 'on', true ),
    array( 'on', 'path', true ),
);

foreach ( $cases as $case ) {
    $log_file = tempnam( sys_get_temp_dir(), 'mds-log-test-' );
    if ( false === $log_file ) {
        throw new RuntimeException( 'Could not create temporary log file.' );
    }
    $command = array( PHP_BINARY, __FILE__, $case[0], $case[1], $log_file );
    $process = proc_open( $command, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
    if ( ! is_resource( $process ) ) {
        unlink( $log_file );
        throw new RuntimeException( 'Could not start logging test.' );
    }
    $output = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
    fclose( $pipes[1] );
    fclose( $pipes[2] );
    $status = proc_close( $process );
    $log    = file_get_contents( $log_file );
    unlink( $log_file );

    $expected = '[MDS Bandcamp Integration] [ERROR] Fetch failed {"endpoint":"album"}';
    $valid = $case[2]
        ? false !== strpos( $log, $expected ) && false !== strpos( $log, '[Context could not be encoded]' )
        : '' === $log;
    if ( 0 !== $status || ! $valid ) {
        throw new RuntimeException( 'Logging test failed for ' . implode( ', ', array_slice( $case, 0, 2 ) ) . ': ' . $output . $log );
    }
}

define( 'ABSPATH', __DIR__ );

function wp_json_encode( $data, $flags = 0 )
{
    return json_encode( $data, $flags );
}

function wp_date( $format, $timestamp )
{
    return gmdate( $format, $timestamp );
}

function maybe_serialize( $value )
{
    return is_array( $value ) || is_object( $value ) ? serialize( $value ) : $value;
}

function size_format( $bytes )
{
    return $bytes . ' B';
}

final class Test_WPDB
{
    public $options = 'wp_options';
    public $prepared_args = array();

    public function esc_like( $value )
    {
        return addcslashes( $value, '_%\\' );
    }

    public function prepare( $query, ...$args )
    {
        $this->prepared_args = $args;
        return $query;
    }

    public function get_results( $query )
    {
        return array(
            (object) array( 'option_name' => '_transient_mds_bandcamp__album_123', 'option_value' => 'sample' ),
            (object) array( 'option_name' => '_transient_timeout_mds_bandcamp__album_123', 'option_value' => '1700000000' ),
        );
    }
}

$wpdb = new Test_WPDB();
require __DIR__ . '/../src/Core/Cache.php';
$transients = json_decode( MDS\Bandcamp\Core\Cache::get_transients_list( 'mds_bandcamp' ), true );
if (
    count( $transients ) !== 1
    || $transients[0]['endpoint'] !== 'album'
    || $transients[0]['id'] !== '123'
    || $transients[0]['size_bytes'] !== 6
    || $transients[0]['expires'] !== gmdate( 'Y-m-d H:i:s', 1700000000 )
    || $wpdb->prepared_args[0] !== '\_transient\_mds\_bandcamp%'
    || $wpdb->prepared_args[1] !== '\_transient\_timeout\_mds\_bandcamp%'
) {
    throw new RuntimeException( 'Cache inspection test failed.' );
}

MDS\Bandcamp\Core\Cache::get_transients_list( '' );
if (
    $wpdb->prepared_args[0] !== '\_transient\_%'
    || $wpdb->prepared_args[1] !== '\_transient\_timeout\_%'
) {
    throw new RuntimeException( 'Unfiltered cache inspection test failed.' );
}

echo "Logging configuration and cache inspection tests passed.\n";
