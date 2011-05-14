<?php
   /**
	* vCard parser class, reads a vCard file and makes each of the values accessible as object members.
	*
	* @link https://github.com/nuovo/vCard-parser
	* @author Roberts Bruveris, Martins Pilsetnieks
	* @see RFC 2426, RFC 2425
	* @version 0.2
	*/
	class vCard implements Countable, Iterator
	{
		const MODE_ERROR = 'error';
		const MODE_SINGLE = 'single';
		const MODE_MULTIPLE = 'multiple';

		/**
		 * @var string Current object mode - error, single or multiple (for a single vCard within a file and multiple combined vCards)
		 */
		private $Mode;  //single, multiple, error

		private $Path = '';
		private $RawData = '';

		/**
		 * @var array Internal data container. Contains vCard objects for multiple vCards and just the data for single vCards.
		 */
		private $Data = array();

		/**
		 * @static Parts of structured elements according to the spec.
		 */
		private static $Spec_StructuredElements = array(
			'n' => array('LastName', 'FirstName', 'AdditionalNames', 'Prefixes', 'Suffixes'),
			'adr' => array('POBox', 'ExtendedAddress', 'StreetAddress', 'Locality', 'Region', 'PostalCode', 'Country'),
			'geo' => array('Latitude', 'Longitude'),
			'org' => array('Name', 'Unit1', 'Unit2')
		);
		private static $Spec_MultipleValueElements = array('nickname', 'categories');

		/**
		 * vCard constructor
		 *
		 * @param string Path to file, optional.
		 * @param string Raw data, optional.
		 *
		 * One of these parameters must be provided, otherwise an exception is thrown.
		 */
		public function __construct($Path = false, $RawData = false)
		{
			// Checking preconditions for the parser.
			// If path is given, the file should be accessible.
			// If raw data is given, it is taken as it is.
			// In both cases the real content is put in $this -> RawData
			if ($Path)
			{
				if (!is_readable($Path))
				{
					throw new Exception('vCard: Path not accessible ('.$Path.')');
				}

				$this -> Path = $Path;
				$this -> RawData = file_get_contents($this -> Path);
			}
			elseif ($RawData)
			{
				$this -> RawData = $RawData;
			}
			else
			{
				throw new Exception('vCard: No content provided');
			}

			// Counting the begin/end separators. If there aren't any or the count doesn't match, there is a problem with the file.
			// If there is only one, this is a single vCard, if more, multiple vCards are combined.
			$vCardBeginCount = substr_count($this -> RawData, 'BEGIN:VCARD');
			$vCardEndCount = substr_count($this -> RawData, 'END:VCARD');

			if (($vCardBeginCount != $vCardEndCount) || !$vCardBeginCount)
			{
				$this -> Mode = vCard::MODE_ERROR;
				throw new Exception('vCard: invalid vCard');
			}

			$this -> Mode = $vCardBeginCount == 1 ? vCard::MODE_SINGLE : vCard::MODE_MULTIPLE;

			// Removing/changing inappropriate newlines, i.e., all CRs or multiple newlines are changed to a single newline
			$this -> RawData = str_replace("\r", "\n", $this -> RawData);
			$this -> RawData = preg_replace('{(\n+)}', "\n", $this -> RawData);

			// In multiple card mode the raw text is split at card beginning markers and each
			//	fragment is parsed in a separate vCard object.
			if ($this -> Mode == self::MODE_MULTIPLE)
			{
				$this -> RawData = explode('BEGIN:VCARD', $this -> RawData);
				$this -> RawData = array_filter($this -> RawData);

				foreach ($this -> RawData as $SinglevCardRawData)
				{
					// Prepending "BEGIN:VCARD" to the raw string because we exploded on that one.
					// If there won't be the BEGIN marker in the new object, it will fail.

					$SinglevCardRawData = 'BEGIN:VCARD'."\n".$SinglevCardRawData;
					$this -> Data[] = new vCard(false, $SinglevCardRawData);
				}
			}
			else
			{
				// Joining multiple lines that are split with a hard wrap and indicated by an equals sign at the end of line
				$this -> RawData = str_replace("=\n", '', $this -> RawData);

				// Joining multiple lines that are split with a soft wrap (space or tab on the beginning of the next line
				$this -> RawData = str_replace(array("\n ", "\n\t"), '', $this -> RawData);

				$Lines = explode("\n", $this -> RawData);

				foreach ($Lines as $Line)
				{
					// Lines without colons are skipped because, most likely, they contain no data.
					if (strpos($Line, ':') === false)
					{
						continue;
					}

					// Each line is split into two parts. The key contains the element name and additional parameters, if present,
					//	value is just the value
					list($Key, $Value) = explode(':', $Line, 2);

					// Key is transformed to lowercase because, even though the element and parameter names are written in uppercase,
					//	it is quite possible that they will be in lower- or mixed case.
					$Key = strtolower(trim(self::Unescape($Key)));

					// These two lines can be skipped as they aren't necessary at all.
					if ($Key == 'begin' || $Key == 'end')
					{
						continue;
					}

					$Value = trim(self::Unescape($Value));
					$Type = array();

					// Here additional parameters are parsed
					$KeyParts = explode(';', $Key);
					$Key = $KeyParts[0];

					if (count($KeyParts) > 1)
					{
						// Parameters are split into (key, value) pairs
						$Parameters = array_map(function($Item)
						{
							return explode('=', strtolower($Item));
						},
						array_slice($KeyParts, 1));

						// And each parameter is checked whether anything can/should be done because of it
						foreach ($Parameters as $Parameter)
						{
							if (count($Parameter) != 2)
							{
								continue;
							}

							if ($Parameter[0] == 'encoding')
							{
								if ($Parameter[1] == 'quoted-printable')
								{
									$Value = quoted_printable_decode($Value);
								}
							}
							elseif ($Parameter[0] == 'charset')
							{
								if ($Parameter[1] != 'utf-8' && $Parameter[1] != 'utf8')
								{
									$Value = mb_convert_encoding($Value, 'UTF-8', $Parameter[1]);
								}
							}
							elseif ($Parameter[0] == 'type')
							{
								$Type = explode(',', $Parameter[1]);
							}
						}
					}

					// Values are parsed according to their type
					if (isset(self::$Spec_StructuredElements[$Key]))
					{
						$Value = self::ParseStructuredValue($Value, $Key);
						if ($Type)
						{
							$Value['Type'] = $Type;
						}
					}
					else
					{
						if (in_array($Key, self::$Spec_MultipleValueElements))
						{
							$Value = self::ParseMultipleTextValue($Value, $Key);
						}

						if ($Type)
						{
							$Value = array(
								'Value' => $Value,
								'Type' => $Type
							);
						}
					}

					if (!isset($this -> Data[$Key]))
					{
						$this -> Data[$Key] = array();
					}

					$this -> Data[$Key][] = $Value;
				}
			}
		}

		/**
		 * Magic method to get the various vCard values as object members, e.g.
		 *	a call to $vCard -> N gets the "N" value
		 *
		 * @param string Key
		 *
		 * @return mixed Value
		 */
		public function __get($Key)
		{
			if (isset($this -> Data[$Key]))
			{
				return $this -> Data[$Key];
			}
			elseif ($Key == 'Mode')
			{
				return $this -> Mode;
			}
			return null;
		}

		// !Helper methods

	 	/**
		 * Removes the escaping slashes from the text.
		 *
		 * @access private
		 *
		 * @param string Text to prepare.
		 *
		 * @return string Resulting text.
		 */
		private static function Unescape($Text)
		{
			return str_replace(array('\:', '\;', '\,', "\n"), array(':', ';', ',', ''), $Text);
		}

		/**
		 * Separates the various parts of a structured value according to the spec.
		 *
		 * @access private
		 *
		 * @param string Raw text string
		 * @param string Key (e.g., N, ADR, ORG, etc.)
		 *
		 * @return array Parts in an associative array.
		 */
		private static function ParseStructuredValue($Text, $Key)
		{
			$Text = array_map('trim', explode(';', $Text));

			$Result = array();
			for ($i = 0; $i < count($Text) && $i < count(self::$Spec_StructuredElements[$Key]); $i++)
			{
				$Result[self::$Spec_StructuredElements[$Key][$i]] = $Text[$i];
			}
			return $Result;
		}

		/**
		 * @access private
		 */
		private static function ParseMultipleTextValue($Text)
		{
			return explode(',', $Text);
		}

		// !Interface methods

		// Countable interface
		public function count()
		{
			switch ($this -> Mode)
			{
				case self::MODE_ERROR:
					return 0;
					break;
				case self::MODE_SINGLE:
					return 1;
					break;
				case self::MODE_MULTIPLE:
					return count($this -> Data);
					break;
			}
			return 0;
		}

		// Iterator interface
		public function rewind()
		{
			reset($this -> Data);
		}

		public function current()
		{
			return current($this -> Data);
		}

		public function next()
		{
			return next($this -> Data);
		}

		public function valid()
		{
			return ($this -> current() !== false);
		}

		public function key()
		{
			return key($this -> Data);
		}
	}
?>