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

file_put_contents( __DIR__ . '/state.json', json_encode( $newState, JSON_PRETTY_PRINT ) );

unset( $state, $monitor );

$time = time();
$messages = [];

foreach( $diff as $path => $change )
{
	$message = ucfirst( $change->Type ) . ' ' . $path . ': ';

	switch( $change->Type )
	{
		case ComparedValue::TYPE_MODIFIED: $message .= $change->OldValue . ' to ' . $change->NewValue; break;
		case ComparedValue::TYPE_REMOVED: $message .= $change->OldValue; break;
		case ComparedValue::TYPE_ADDED: $message .= $change->NewValue; break;
	}

	$messages[] = $message;
}

foreach( $newState as $object )
{
	$days = GetDaysBetweenTimestamps( $object[ 'whois' ][ 'expires' ] ?? 0, $time );

	if( $days <= 14 )
	{
		$messages[] =
		[
			'domain' => $object[ 'domain' ],
			'message' => 'Domain expires in less than ' . $days . ' days',
		];
	}

	foreach( $object[ 'certificates' ] as $certificate )
	{
		$days = GetDaysBetweenTimestamps( $certificate[ 'notAfter' ], $time );

		if( $days <= 14 )
		{
			$messages[] =
			[
				'domain' => $object[ 'domain' ],
				'message' => 'Certificate expires in less than ' . $days . ' days',
			];
		}
	}
}

if( !empty( $messages ) )
{
	$singleMessage = '';

	foreach( $messages as $msg )
	{
		if( is_array( $msg ) )
		{
			$singleMessage .= $msg[ 'domain' ] . ' - ' . $msg[ 'message' ] . PHP_EOL;
		}
		else
		{
			$singleMessage .= $msg . PHP_EOL;
		}
	}

	echo $singleMessage;
}

function GetDaysBetweenTimestamps( $a, $b )
{
	$a -= $a % 86400;
	$b -= $b % 86400;

	return ( $a - $b ) / 86400;
}
