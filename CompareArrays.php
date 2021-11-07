<?php
declare(strict_types=1);

/**
 * Diffing multi dimensional arrays the easy way.
 *
 * GitHub: {@link https://github.com/xPaw/CompareArrays.php}
 * Website: {@link https://xpaw.me}
 *
 * @author Pavel Djundik
 * @license MIT
 */
class CompareArrays
{
	/**
	 * Flattens multi-dimensional array into one dimensional array,
	 * and turns keys into paths separated by $Separator (by default '/').
	 */
	public static function Flatten( array $Input, string $Separator = '/', string $Path = '' ) : array
	{
		$Data = [];

		if( $Path !== '' )
		{
			$Path .= $Separator;
		}

		foreach( $Input as $Key => $Value )
		{
			if( is_array( $Value ) )
			{
				foreach( self::Flatten( $Value, $Separator, $Path . $Key ) as $NewKey => $NewValue )
				{
					$Data[ $NewKey ] = $NewValue;
				}
			}
			else
			{
				$Data[ $Path . $Key ] = $Value;
			}
		}

		return $Data;
	}

	/**
	 * Compares two arrays and produces a new array of changes between these
	 * two arrays. New array will be same level deep as the input arrays,
	 * and the deepest value will be `ComparedValue`, which is an object
	 * describing the difference (added, removed, modified).
	 *
	 * Optionally, use CompareArrays::Flatten() function to turn diff array
	 * into a one dimensional array which will flatten keys into a single path.
	 */
	public static function Diff( array $Old, array $New ) : array
	{
		$Diff = [];

		if( $Old === $New )
		{
			return $Diff;
		}

		foreach( $Old as $Key => $Value )
		{
			if( !array_key_exists( $Key, $New ) )
			{
				$Diff[ $Key ] = self::Singular( ComparedValue::TYPE_REMOVED, $Value );

				continue;
			}

			$ValueNew = $New[ $Key ];

			// Force values to be proportional arrays
			$IsOldArray = is_array( $Value );
			$IsNewArray = is_array( $ValueNew );

			if( $IsOldArray && !$IsNewArray )
			{
				$IsNewArray = true;
				$ValueNew = [ $ValueNew ];
			}

			if( $IsNewArray )
			{
				if( !$IsOldArray )
				{
					$Value = [ $Value ];
				}

				$Temp = self::Diff( $Value, $ValueNew );

				if( !empty( $Temp ) )
				{
					$Diff[ $Key ] = $Temp;
				}

				continue;
			}

			if( $Value != $ValueNew )
			{
				$Diff[ $Key ] = new ComparedValue( ComparedValue::TYPE_MODIFIED, $Value, $ValueNew );
			}
		}

		foreach( $New as $Key => $Value )
		{
			if( !array_key_exists( $Key, $Old ) )
			{
				$Diff[ $Key ] = self::Singular( ComparedValue::TYPE_ADDED, $Value );
			}
		}

		return $Diff;
	}

	private static function Singular( string $Type, mixed $Value ) : ComparedValue|array
	{
		if( is_array( $Value ) )
		{
			$Diff = [];

			foreach( $Value as $Key => $Value2 )
			{
				$Diff[ $Key ] = self::Singular( $Type, $Value2 );
			}

			return $Diff;
		}

		if( $Type === ComparedValue::TYPE_REMOVED )
		{
			return new ComparedValue( $Type, $Value, null );
		}

		return new ComparedValue( $Type, null, $Value );
	}
}

class ComparedValue
{
	const TYPE_ADDED = 'added';
	const TYPE_REMOVED = 'removed';
	const TYPE_MODIFIED = 'modified';

	public mixed $OldValue;
	public mixed $NewValue;
	public string $Type;

	function __construct( string $Type, mixed $OldValue, mixed $NewValue )
	{
		$this->OldValue = $OldValue;
		$this->NewValue = $NewValue;
		$this->Type = $Type;
	}
}
