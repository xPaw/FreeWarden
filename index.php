<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/CompareArrays.php';
require __DIR__ . '/DomainMonitor.php';

$state = json_decode( file_get_contents( __DIR__ . '/state.json' ), true );
$newState = [];
$monitor = new DomainMonitor();

foreach( $state as $object )
{
	$whois = $monitor->GetWhois( $object[ 'domain' ] );
	$addresses = $monitor->GetAddresses( $object[ 'domain' ] );
	$certificates = $monitor->GetCertificates( $object[ 'domain' ], $addresses );

	$newState[ $object[ 'domain' ] ] =
	[
		'domain'       => $object[ 'domain' ],
		'whois'        => $whois,
		'addresses'    => $addresses,
		'certificates' => $certificates,
	];
}

$diff = CompareArrays::Diff( $state, $newState );
$diff = CompareArrays::Flatten( $diff );
print_r( $diff );

file_put_contents( __DIR__ . '/state.json', json_encode( $newState, JSON_PRETTY_PRINT ) );
