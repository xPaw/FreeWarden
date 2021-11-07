<?php
declare(strict_types=1);

$stateString = file_get_contents( __DIR__ . '/state.json' );
$state = json_decode( $stateString, true );

for( $i = 1; $i < $argc; $i++ )
{
	$domain = $argv[ $i ];

	if( isset( $state[ $domain ] ) )
	{
		echo "{$domain} already exists" . PHP_EOL;
		continue;
	}

	echo "Added {$domain}" . PHP_EOL;

	$state[ $domain ] =
	[
		'domain' => $domain,
	];
}

ksort( $state );

file_put_contents( __DIR__ . '/state.json', json_encode( $state, JSON_PRETTY_PRINT ) );
