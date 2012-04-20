<?php
/**
 * vCard class for parsing a vCard and/or creating one
 *
 * @link https://github.com/nuovo/vCard-parser
 * @author Roberts Bruveris, Martins Pilsetnieks
 * @see RFC 2426, RFC 2425
 * @version 0.4
*/
	class vCard implements Countable, Iterator
	{
		const MODE_ERROR = 'error';
		const MODE_SINGLE = 'single';
		const MODE_MULTIPLE = 'multiple';

		const endl = "\n";

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

		private static $Spec_ElementTypes = array(
			'email' => array('internet', 'x400', 'pref'),
			'adr' => array('dom', 'intl', 'postal', 'parcel', 'home', 'work', 'pref'),
			'label' => array('dom', 'intl', 'postal', 'parcel', 'home', 'work', 'pref'),
			'tel' => array('home', 'msg', 'work', 'pref', 'voice', 'fax', 'cell', 'video', 'pager', 'bbs', 'modem', 'car', 'isdn', 'pcs')
		);

		private static $Spec_FileElements = array('photo', 'logo', 'sound');

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
				//throw new Exception('vCard: No content provided');
				// Not necessary anymore as possibility to create vCards is added
			}

			if (!$this -> Path && !$this -> RawData)
			{
				return true;
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
					$Encoding = false;

					if (count($KeyParts) > 1)
					{
						$Parameters = self::ParseParameters($Key, array_slice($KeyParts, 1));

						foreach ($Parameters as $ParamKey => $ParamValue)
						{
							switch ($ParamKey)
							{
								case 'encoding':
									$Encoding = $ParamValue;
									if ($ParamValue == 'b')
									{
										//$Value = base64_decode($Value);
									}
									elseif ($Parameters[1] == 'quoted-printable')
									{
										$Value = quoted_printable_decode($Value);
									}
									break;
								case 'charset':
									if ($Parameter[1] != 'utf-8' && $Parameter[1] != 'utf8')
									{
										$Value = mb_convert_encoding($Value, 'UTF-8', $Parameter[1]);
									}
									break;
								case 'type':
									$Type = $ParamValue;
									break;
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

					if (is_array($Value) && $Encoding)
					{
						$Value['Encoding'] = $Encoding;
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
				if (in_array($Key, self::$Spec_FileElements))
				{
					$Value = $this -> Data[$Key];
					foreach ($Value as $K => $V)
					{
						if (stripos($V['Value'], 'uri:') === 0)
						{
							$Value[$K]['Value'] = substr($V, 4);
							$Value[$K]['Encoding'] = 'uri';
						}
					}
					return $Value;
				}
				return $this -> Data[$Key];
			}
			elseif ($Key == 'Mode')
			{
				return $this -> Mode;
			}
			return null;
		}

		/**
		 * Saves an embedded file
		 *
		 * @param string Key
		 * @param int Index of the file, defaults to 0
		 * @param string Target path where the file should be saved, including the filename
		 *
		 * @return bool Operation status
		 */
		public function SaveFile($Key, $Index = 0, $TargetPath = '')
		{
			if (!isset($this -> Data[$Key]))
			{
				return false;
			}
			if (!isset($this -> Data[$Key][$Index]))
			{
				return false;
			}

			// Returing false if it is an image URL
			if (stripos($this -> Data[$Key][$Index]['Value'], 'uri:') === 0)
			{
				return false;
			}

			if (is_writable($TargetPath) || (!file_exists($TargetPath) && is_writable(dirname($TargetPath))))
			{
				$RawContent = $this -> Data[$Key][$Index]['Value'];
				if (isset($this -> Data[$Key][$Index]['Encoding']) && $this -> Data[$Key][$Index]['Encoding'] == 'b')
				{
					$RawContent = base64_decode($RawContent);
				}
				$Status = file_put_contents($TargetPath, $RawContent);
				return (bool)$Status;
			}
			else
			{
				throw new Exception('vCard: Cannot save file ('.$Key.'), target path not writable ('.$TargetPath.')');
			}
			return false;
		}

		/**
		 * Magic method for adding data to the vCard
		 *
		 * @param string Key
		 * @param string Method call arguments. First element is value.
		 *
		 * @return vCard Current object for method chaining
		 */
		public function __call($Key, $Arguments)
		{
			if (!isset($this -> Data[$Key]))
			{
				$this -> Data[$Key] = array();
			}

			$Value = isset($Arguments[0]) ? $Arguments[0] : false;

			if (!$Value)
			{
				return $this;
			}

			if (count($Arguments) > 1)
			{
				$Types = array_values(array_slice($Arguments, 1));

				if (isset(self::$Spec_StructuredElements[strtolower($Key)]) &&
					in_array($Arguments[1], self::$Spec_StructuredElements[strtolower($Key)])
				)
				{
					$LastElementIndex = 0;

					if (count($this -> Data[$Key]))
					{
						$LastElementIndex = count($this -> Data[$Key]) - 1;
					}

					if (isset($this -> Data[$Key][$LastElementIndex]))
					{
						if (empty($this -> Data[$Key][$LastElementIndex][$Types[0]]))
						{
							$this -> Data[$Key][$LastElementIndex][$Types[0]] = $Value;
						}
						else
						{
							$LastElementIndex++;
						}
					}

					if (!isset($this -> Data[$Key][$LastElementIndex]))
					{
						$this -> Data[$Key][$LastElementIndex] = array(
							$Types[0] => $Value
						);
					}
				}
				elseif (in_array($Key, array_keys(self::$Spec_ElementTypes)))
				{
					$this -> Data[$Key][] = array(
						'Value' => $Value,
						'Type' => $Types
					);
				}
			}
			else
			{
				$this -> Data[$Key][] = $Value;
			}

			return $this;
		}

		/**
		 * Magic method for getting vCard content out
		 *
		 * @return string Raw vCard content
		 */
		public function __toString()
		{
			$Text = 'BEGIN:VCARD'.self::endl;
			$Text .= 'VERSION:3.0'.self::endl;

			foreach ($this -> Data as $Key => $Values)
			{
				$KeyUC = strtoupper($Key);

				if (in_array($KeyUC, array('PHOTO', 'VERSION')))
				{
					continue;
				}

				foreach ($Values as $Index => $Value)
				{
					$Text .= $KeyUC;
					if (is_array($Value) && isset($Value['Type']))
					{
						$Text .= ';TYPE='.self::PrepareTypeStrForOutput($Value['Type']);
					}
					$Text .= ':';

					if (in_array($Key, array_keys(self::$Spec_StructuredElements)))
					{
						$PartArray = array();
						foreach (self::$Spec_StructuredElements[$Key] as $Part)
						{
							$PartArray[] = isset($Value[$Part]) ? $Value[$Part] : '';
						}
						$Text .= implode(';', $PartArray);
					}
					elseif (is_array($Value) && in_array($Key, array_keys(self::$Spec_ElementTypes)))
					{
						$Text .= $Value['Value'];
					}
					else
					{
						$Text .= $Value;
					}

					$Text .= self::endl;
				}
			}

			$Text .= 'END:VCARD'.self::endl;
			return $Text;
		}

		// !Helper methods

		private static function PrepareTypeStrForOutput($Type)
		{
			return implode(',', array_map('strtoupper', $Type));
		}

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

		/**
		 * @access private
		 */
		private static function ParseParameters($Key, array $RawParams = null)
		{
			if (!$RawParams)
			{
				return array();
			}

			// Parameters are split into (key, value) pairs
			$Parameters = array_map(function($Item)
			{
				return explode('=', strtolower($Item));
			},
			$RawParams);

			$Type = array();
			$Result = array();

			// And each parameter is checked whether anything can/should be done because of it
			foreach ($Parameters as $Index => $Parameter)
			{
				// Skipping empty elements
				if (!$Parameter)
				{
					continue;
				}

				// Handling type parameters without the explicit TYPE parameter name (2.1 valid)
				if (count($Parameter) == 1)
				{
					if (isset(self::$Spec_ElementTypes[$Key]) && in_array($Parameter, self::$Spec_ElementTypes[$Key]))
					{
						$Type[] = $Parameter;
					}
				}
				elseif (count($Parameter) > 2)
				{
					$TempTypeParams = self::ParseParameters($Key, explode(',', $RawParams[$Index]));
					if ($TempTypeParams['type'])
					{
						$Type = array_merge($Type, $TempTypeParams['type']);
					}
				}
				else
				{
					if ($Parameter[0] == 'encoding')
					{
						if (in_array($Parameter[1], array('quoted-printable', 'b')))
						{
							$Result['encoding'] = $Parameter[1];
						}
					}
					elseif ($Parameter[0] == 'charset')
					{
						$Result['charset'] = $Parameter[1];
					}
					elseif ($Parameter[0] == 'type')
					{
						$Type = array_merge($Type, explode(',', $Parameter[1]));
					}
				}
			}

			$Result['type'] = $Type;

			return $Result;
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