a<?php

class ShuttleSqlDumpParserToken {
	public $name;
	public $start_pos, $end_pos;
	public $length;

	function __construct($name, $position) {
		$this->name = $name;
		$this->length = strlen($name);
		$this->start_pos = $position;
		$this->end_pos = $this->start_pos + $this->length;
	}
}

class ShuttleSqlDumpParser {
	/**
	 * Location of the dump .sql file. Currently the class doesn't support .sql.gz files.
	 */
	private $file; 

	/**
	 * The query that is being extracted from the dump now. 
	 */
	private $query;

	function __construct($file) {
		$this->file = $file;
	}

	function split($callback) {

	}
}

define('DEFAULT_READ_BUFFER_SIZE', 65536);

function do_multi_sql($file_name, $callback="do_one_sql") {
	$query = '';
	$opening_token = '';
	$is_comment_context = false;

	while ($input_string = get_next_chunk($file_name)) {
		$opening_pos =- strlen($opening_token);
		$current_pos = 0;
		$i = strlen($input_string);

		while ( $i-- ) {
			if ( $opening_token ) {
				$closing_token = get_closing_token(
					$input_string,
					$opening_pos + strlen($opening_token),
					$opening_token
				);
				
				if ( $closing_token !== null ) {
					if ( $opening_token === '--' || $opening_token=='#' || $is_comment_context ) {
						$query .= substr(
							$input_string,
							$current_pos,
							$opening_pos - $current_pos
						);
					} else {
						$query .= substr(
							$input_string,
							$current_pos,
							$closing_token->end_pos - $current_pos
						);
					}

					$current_pos = $closing_token->end_pos;

					$opening_token = '';
					$opening_pos = 0;
				} else {
					$query .= substr($input_string, $current_pos);
					break;
				}

			} else {
				list($opening_token, $opening_pos) = get_opening_token($input_string, $current_pos);

				if ($opening_token === ';') {
					$query .= substr(
						$input_string,
						$current_pos,
						$opening_pos - $current_pos + 1
					);

					call_user_func($callback, $query);

					$query = '';
					$current_pos = $opening_pos + strlen($opening_token);
					$opening_token = '';
					$opening_pos = 0;

				} elseif(!$opening_token) {
					$query .= substr($input_string, $current_pos);
					break;
				} else {
					if ($opening_token === '/*' && substr($input_string, $opening_pos, 3) !== '/*!') {
						$is_comment_context = true;
					} else {
						$is_comment_context = false;
					}
				}
			}
		}
	}

	if ($query) {
		call_user_func($callback, $query);
		$query = '';
	}

	return true;
}

//read from insql var or file
function get_next_chunk($file_name, $buffer=DEFAULT_READ_BUFFER_SIZE) {
	static $file_handle;

	if (!$file_handle) {
		$file_handle = fopen($file_name, "r+b");

		if (!$file_handle) {
			throw new Exception("Can't open $file_name file. ");
		}
	}

	return fread($file_handle, $buffer);
}

function get_opening_token($input_string, $pos){
	$opening_token = null;
	$opening_pos = null;

	if ( preg_match('~(\/\*|^--|(?<=\s)--|#|\'|"|;)~', $input_string, $matches, PREG_OFFSET_CAPTURE, $pos) ) {
		$opening_token = $matches[1][0];
		$opening_pos = $matches[1][1];
	}

	return array($opening_token, $opening_pos);
}

function get_closing_token($input_string, $pos, $opening_token) {
	$closing_tokens = array(
		// opening token => closing token token
		'\''  => '(?<!\\\\)\'|(\\\\+)\'',
		'"'   => '(?<!\\\\)"',
		'/*'  => '\*\/',
		'#'   => '[\r\n]+',
		'--'  => '[\r\n]+',
	);

	if (!isset($closing_tokens[$opening_token])) {
		return null;
	}
	$closing_token_regex = $closing_tokens[$opening_token];

	if ( ! preg_match('~(' . $closing_token_regex . ')~', $input_string, $matches, PREG_OFFSET_CAPTURE, $pos ) ) {
		return null;
	}
	$closing_token_name = $matches[1][0];
	$closing_pos = $matches[1][1];
	
	$closing_token = new ShuttleSqlDumpParserToken($closing_token_name, $closing_pos);


	if (isset($matches[2][0])) {
		$sl = strlen($matches[2][0]);

		if ($opening_token === "'" && $sl) {
			if ($sl % 2) {
				$closing_token = get_closing_token(
					$input_string,
					$closing_token->end_pos,
					$opening_token
				);
			} else {
				$closing_pos += strlen($closing_token) - 1;
				$closing_token = "'";
			}
		}
	}

	return $closing_token;
}

function do_one_sql($query){
	static $query_counter = 1;
	$query = trim($query);

	echo trim("Query $query_counter: $query\n\n\n-----\n\n\n");
	$query_counter++;

	return 1;
}