<?php

/*
  This routine is responsible for converting lists of parameters to and from the
   binary strings expected by, and sent from, the client.

   The format of the binary strings is derived at run-time from a copy of the
   client's own data files.  These are not distributed with this server!

   These should be placed within ./resources/<version>mag/

   (c)2016 AmandaJones - AltAway Project.


*/


class Trans {

static	$tdata = array();
private static $debug = false;

	// Initialise with specified data.
	private function __construct($dat = null ){}
	
	// Create trans data...

	static function initialise($resourcefolder, $clientversion){

		if (DEBUG) echo 'Loading resources for version ' . $clientversion . PHP_EOL;

		self::$tdata = array();

		// Object data.


		self::$tdata['objects'] = self::_readAndProcess($resourcefolder,$clientversion, 'cd01', '_fetchItemData');
		self::$tdata['cmd'] = self::_readAndProcess($resourcefolder,$clientversion, 'cd04', '_fetchItemData');


		
	}

	private static function _readAndProcess($folder, $version, $prefix, $callback){

		$fnum = 0;
		$td = array();

		while (file_exists(
			$objDefnFile = "{$folder}/".  $version ."mag/{$prefix}" . str_pad((string)$fnum, 4, '0', STR_PAD_LEFT) . ".dat" ) ) {

			if (DEBUG > 1) echo "  Parsing {$objDefnFile}\n";
			$td = $td + self::$callback(file_get_contents($objDefnFile));
			$fnum++;

		}
		if (self::$debug) echo $objDefnFile;
		echo "  $prefix - ".count($td).' items loaded.'.PHP_EOL;
		return $td;
	}


	/*
	   this was done as a callback because I thought we might need different functions for
	   differnt cd* files - turns out you can tell which resType it is from the data itself.
	*/
	private static function _fetchItemData($data){

		$eoi = strlen($data);	// initial default end of index...
		$iptr = 8;

		$p = array();

		do {
			$idxrec = substr($data, $iptr, 16);
				if (self::$debug) echo bin2hex($idxrec);
			$i = unpack('nresType/nnumber/Nsomething/Npointer/Nlength', $idxrec);
			//	print_r($i);
			$msgnum = $i['number'];
			$msgptr = $i['pointer'];
			$msglen = $i['length'];

			if (self::$debug) echo "\nIndexPtr:{$iptr}\nResType:{$i['resType']}\nMsgnum: {$msgnum}\nMsgptr:{$msgptr}\nMsglen:{$msglen}\n";

			if ($msgnum == -1 || $msgnum == 65535) {
				break;
			}

			if ($msgptr < $eoi)	// end of index is start of first data block in the file.
				$eoi = $msgptr; // this is usually 0x408 but who can tell if this may change.

			$msg = substr($data, $msgptr, $msglen);

//			$test = unpack('ntest',substr($msg, 2, 2));
//			if ($test['test'] == $msgnum) {	// objects have the msg num repeated as first pair of bytes.
			switch ($i['resType']) {
				case 1:
					// object definitions
					$itemType = substr($msg, 10, strpos($msg,chr(0),10)-1);
					$itemDefn = substr($msg, strpos($msg,'bu2',10));
					// i have a funny feeling about the varying amount of data between those two.  Defaults?
					// Plus.. what if the first fieldis not  bu2 !

					$p[$msgnum] = array('name' => $itemType, 'def' => self::_convert_format($itemDefn));

					if (self::$debug) echo "ItemType:{$itemType}\nDefn:{$itemDefn}\n";
					//if (self::$debug) break 2;
					break;
				case 4:
					// command definitions. These launch straight into the list of parameters
					$itemDefn = substr($msg, 2);
					$p[$msgnum] = self::_convert_format($itemDefn);
					if (self::$debug) echo "Defn:{$itemDefn}\n";
//					if (self::$debug) break 2;
					break;
				default:
				;
			} // switch}


			// I am not happy about this. We really need to define the format!


			$iptr += 16;

			} while ($iptr < $eoi );

		return $p;
	}




	/*
	   This function makes an attempt to decode the string used as a data format definition
	   within the client into something that pack()/unpack() can use.

	   Params:	$defn	- e.g.  bu2what;bu2target_noid;bu1colors,16;;

	   Return:  Array - 'pack' - pack string
	   'unpack' - unpack string
	   'fields' - array of field names (in order)

	*/
	private static function _convert_format($defn){

		$fs = explode(';',$defn);
		$ss = '';
		$us = '';
		$fields = array();
		$linelen = 0;

		foreach ($fs as $f) {
			//		echo $f;
			if (substr($f,0,1) == 'b') {
				$type = substr($f,1,1);
				$len = (int)substr($f,2);
				if ($i = strpos($f,',')) {						// bu1contentsVector,cvsize
					$count = (int)substr($f,$i+1);
					if ($count == 0) {
						$count = '{$'.substr($f,$i+1).'}';
					}
					$name = substr(substr($f,0,$i), 2+($len ? 1 : 0));
				} else {
					$name = substr($f,2+($len ? 1 : 0));
					$count = FALSE;
				}

				switch ($type . ( $len ? $len : '')) {
					case 'c':
					case 'u1':
						$s = 'a';
						$l = 1;
						break;
					case 'i2':
					case 'u2':
						$s = 'n';
						$l = 2;
						break;
					case 'i4':
					case 'u4':
						$s = 'N';
						$l = 4;
						break;
					case 's':			// null (chr0) terminated string..
						$s = 'a*x';		// works for pack() ... needs next fn to unpack...
						$l = 1;
						break;
//					default:
//					;
				} // switch

				if ($count) {
					if (strpos('-aAhH@',$s) === FALSE) {
						if ($count[0] == '{')
							$fields[] = "{$name}*";
						else
							$l *= $count;
							for($i=1; $i<=$count; $i++)
								$fields[] = "{$name}{$i}";
					} else {
						$fields[] = $name;
					}
				} else {
					$fields[] = $name;
				}

				if ($count) {
					$s .= $count;
				}
				if ($us) {
					$us .= '/';
				}
				$us .= $s . $name;
				$ss .= $s;
				$linelen += $l;
			}
		}

		return array('pack' => $ss,
				'unpack' => $us,
				'fields' => $fields,
				'length' => $linelen);
	}


	/*
	   Special unpack that copes with zero-terminated strings.

	   These will be spotted by the unpack string having the sequence a*xname

	*/
	private static function _unpack($format, $data) {
//		echo "inside custom unpack\n";
//		echo "{$format}\n";
//		echo bin2hex($data)."\n";
		if (strpos($format,'a*x') === false)
			return unpack($format,$data);

		$fields = explode('/',$format);
		$i = 0;
		$r = array();
		foreach ($fields as &$f) {
//			echo "{$i}:{$f}\n";
			if (strlen($f)) {
				switch ($f{0}) {
					case 'n':
						$l = 2;
						break;
					case 'N':
						$l = 4;
						break;
					case 'a':
						$l = 1;
						if (substr($f,0,3) == 'a*x') {
							$j = strpos($data, chr(0), $i) - $i + 1;
							$f = 'Z' . $j . substr($f,3);
						}
						break;
					default:
						$l = 1;
				} // switch
			}
			$c = (int)substr($f,1);
			$i += $l * ($c ? $c : 1);
		}
		$format = implode('/',$fields);
//		echo "{$format}\n";
		return unpack($format,$data);
	}




	/*
	 Main conversion routines called to translate binary data that is sent to/from
	 the client from/to a much nicer associated array.

	 (If a field is not specified within $params, it will be encoded as zero/blank)

	*/
	public static function cmdToClient($cmdnum,$params){
		if (!isset(self::$tdata['cmd'][$cmdnum]))
			return false;

		$ps = self::$tdata['cmd'][$cmdnum]['pack'];
		// create an array of empty records in the right order
		$allparams = array_fill_keys(self::$tdata['cmd'][$cmdnum]['fields'],null);

		// translate {$fred} into value of fred!
		if ($i = strpos($ps,'{$')) {
			$var = substr($ps,$i+2, strpos($ps,'}',$i)-$i-2);
			//			echo ">$var<";
			$ps = str_replace('{$'.$var.'}',$params[$var],$ps);

			// if it's a numeric, rather than a string,
			// we need to instead treat it as multiple parameters...
			$code = $ps[$i-1];
			if ($code == 'n' || $code == 'N') {
				// f... it's not $var./.// and we don't know what it IS here..
				// have to assume there is just one combination...
				foreach (self::$tdata['cmd'][$cmdnum]['fields'] as $field) {
					if (substr($field,-1,1) == '*') {
						unset($allparams[$field]);
						$field = substr($field,0,-1);
						for ($j=0; $j<$params[$var]; $j++) {
							$allparams["{$field}{$j}"] = null;
						}
						break;
					}
				}
			}
		}

		
		
		// copy in (just) the data we need from the params..
		array_walk($allparams, function(&$value, $key) use ($params) {
			if (isset($params[$key])) {
				$value = $params[$key];

				if (defined('CLIENTCHARSET') && CLIENTCHARSET !== 'UTF-8' && in_array($key, TEXTFIELDS)) {
					$value = iconv('UTF-8', CLIENTCHARSET, $value);
				}

			}
		});


		// http://stackoverflow.com/a/29919335
		// gets around pack() needing fixed list of params, not an array of said.
		// modified to drop keys, just in case!!
		return call_user_func_array("pack", array_merge(array($ps), array_values($allparams)));
	}


	public static function clientToCmd($cmdnum,$params){
		if (!isset(self::$tdata['cmd'][$cmdnum]))
			return false;

		$us = self::$tdata['cmd'][$cmdnum]['unpack'];

		while (($i=strpos($us,'{$'))!== false) {		// we've got a  fieldname,{$fieldname} construct
			$shorter = substr($us,0,$i-1);
			$field = substr($us,$i+2,strpos($us,'}',$i)-$i-2);
			$r = self::_unpack($shorter, $params);
			if (!isset($r[$field])) {
				echo("Cannot find {$field} in " . print_r($r,true));
				return false;
			}
			$us = str_replace('{$'.$field.'}',$r[$field],$us);
//			echo "{$us}\n";
		}
		$params = self::_unpack($us, $params);
		if (defined('CLIENTCHARSET') && CLIENTCHARSET !== 'UTF-8') {
			foreach ($params as $key => &$value) {
				if (in_array($key, TEXTFIELDS)) {
					$value = iconv(CLIENTCHARSET, 'UTF-8', $value);
				}
			}
		}		
		return $params;
	}

	public static function objectToClient($objType,$params){
		if (!isset(self::$tdata['objects'][$objType])) return false;

		$ps = self::$tdata['objects'][$objType]['def']['pack'];

		$allparams = array_fill_keys(self::$tdata['objects'][$objType]['def']['fields'],null);
//		$full = array_merge($empty, array_filter($params, function($key) {
//			return isset(self::$tdata["objects"][$objType]["def"]["fields"][$key]);
//		} ));

		array_walk($allparams, function(&$value, $key) use ($params) {
			if (isset($params[$key])) {
				$value = $params[$key];
			} else {
				// if key is validExits2 look for validExits[1] (0 based)
				$l = strcspn($key, '0123456789');	// "position1" = 8
				if ($l) {
					if (strlen($key) - $l) {			// "position1" = 9-8 = true
						$prefix = substr($key,0,$l);
						$sub = substr($key,$l);
						if (isset($params[$prefix]) && is_array($params[$prefix]) && isset($params[$prefix][$sub - 1])) {
							$value = $params[$prefix][$sub - 1];
						}
					}
				}
			}
			if (defined('CLIENTCHARSET') && CLIENTCHARSET !== 'UTF-8' && in_array($key, TEXTFIELDS)) {
				$value = iconv('UTF-8', CLIENTCHARSET, $value);
			}
		});

		return call_user_func_array("pack", array_merge(array($ps), array_values($allparams)));
	}

	//TODO might yet need to do the {$fred} fiddle as per cleintToCmd()
	public static function clientToObject($objType,$params){
		if (!isset(self::$tdata['objects'][$objType])) return false;

		$us = self::$tdata['objects'][$objType]['def']['unpack'];
		return self::_unpack($us, $params);
	}

	public static function getObjectFields($objType) {
		return self::$tdata['objects'][$objType]['def']['fields'];
	}
}
