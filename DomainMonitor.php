<?php

class DomainMonitor
{
	public function GetWhois( string $hostname ) : array
	{
		$whoisParser = new Novutec\WhoisParser\Parser();
		$whois = $whoisParser->lookup( $hostname );

		$nameservers = $whois->nameserver;
		sort( $nameservers );

		// it's supposed to be a boolean, but it's not always a boolean
		if( is_string( $whois->dnssec ) )
		{
			$whois->dnssec = strtoupper( $whois->dnssec[ 0 ] ) !== 'U'; // unsigned
		}

		$whois =
		[
			'nameservers' => $nameservers,
			'created' => strtotime( $whois->created ),
			'expires' => strtotime( $whois->expires ),
			'changed' => strtotime( $whois->changed ),
			'dnssec' => $whois->dnssec,
		];

		return $whois;
	}

	public function GetAddresses( string $hostname ) : array
	{
		$records = dns_get_record( $hostname , DNS_A | DNS_AAAA );
		$addresses = [];

		foreach( $records as $record )
		{
			$address = '';

			switch( $record[ 'type' ] )
			{
				case 'A'   : $address = $record[ 'ip' ]; break;
				case 'AAAA': $address = '[' . $record[ 'ipv6' ] . ']'; break;
			}

			$addresses[] = $address;
		}

		sort( $addresses );

		return $addresses;
	}

	public function GetCertificates( string $hostname, array $addresses ) : array
	{
		$certificates = [];
		$alreadySeen = [];

		foreach( $addresses as $address )
		{
			$certificateStream = self::FetchCertificate( $address, $hostname );
			$certificate = openssl_x509_parse( $certificateStream, false );

			if( isset( $alreadySeen[ $certificate[ 'serialNumberHex' ] ] ) )
			{
				continue;
			}

			$subjectAltName = explode( ', ', $certificate[ 'extensions' ][ 'subjectAltName' ] );
			sort( $subjectAltName );

			$alreadySeen[ $certificate[ 'serialNumberHex' ] ] = true;
			$certificates[] =
			[
				'name' => $certificate[ 'name' ],
				'commonName' => $certificate[ 'subject' ][ 'commonName' ],
				'subjectAltName' => $subjectAltName,
				'notBefore' => $certificate[ 'validFrom_time_t' ],
				'notAfter' => $certificate[ 'validTo_time_t' ],
				'serialNumberHex' => $certificate[ 'serialNumberHex' ],
			];
		}

		usort( $certificates, function( $a, $b )
		{
			return strcmp( $a[ 'serialNumberHex' ], $b[ 'serialNumberHex' ] );
		} );

		return $certificates;
	}

	public function FetchCertificate( string $address, string $hostname )
	{
		$context = stream_context_create( [
			'ssl' =>
			[
				'peer_name'         => $hostname,
				'capture_peer_cert' => true,
				'allow_self_signed' => true,
				'verify_peer'       => false,
				'verify_peer_name'  => false,
			]
		] );

		$stream = @stream_socket_client( 'ssl://' . $address . ':443', $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context );

		if( $stream === false )
		{
			return null;
		}

		$streamParams = stream_context_get_params( $stream );
		fclose( $stream );

		if( empty( $streamParams[ 'options' ][ 'ssl' ][ 'peer_certificate' ] ) )
		{
			return null;
		}

		return $streamParams[ 'options' ][ 'ssl' ][ 'peer_certificate' ];
	}
}
