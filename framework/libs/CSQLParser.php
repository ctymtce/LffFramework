<?php
/**
 * desc: sql解析器
 *
 *
*/
class CSQLParser{
	#
	# the main public interface is very simple
	#
	public $tokens = array();
	public $tables = array();

	public function parse($sql)
	{
		$this->tokens = $this->lex($sql);
		$ret = $this->walk($this->tokens);

		$this->tables = $ret['tables'];
		return $this->tables;
	}

	public function lex($sql)
    {
		$pos = 0;
		$len = strlen($sql);

		$tokens = array();

		while ($pos < $len){

			# [space]
			# [newline]
			if (preg_match('!\s+!A', $sql, $m, 0, $pos)){
				$pos += strlen($m[0]);
				continue;
			}

			# [comment]
			if (preg_match('!--!A', $sql, $m, 0, $pos)){
				$p2 = strpos($sql, "\n", $pos);
				if ($p2 === false){
					$pos = $len;
				}else{
					$pos = $p2+1;
				}
				continue;
			}
			if (preg_match('!/\\*!A', $sql, $m, 0, $pos)){
				$p2 = strpos($sql, "*/", $pos);
				if ($p2 === false){
					$pos = $len;
				}else{
					$pos = $p2+2;
				}
				continue;
			}

			# [regular identifier]
			# [key word]
			if (preg_match('![[:alpha:]][[:alnum:]_]*!A', $sql, $m, 0, $pos)){
				$tokens[] = substr($sql, $pos, strlen($m[0]));
				$pos += strlen($m[0]);
				continue;
			}

			# backtick quoted field
			if (substr($sql, $pos, 1) == '`'){
				$p2 = strpos($sql, "`", $pos+1);
				if ($p2 === false){
					$pos = $len;
				}else{
					$tokens[] = substr($sql, $pos, 1+$p2-$pos);
					$pos = $p2+1;
				}
				continue;
			}

			# [unsigned numeric literal]
			# [unsigned integer> [ [period] [ [unsigned integer] ] ]
			# [period] [unsigned integer]
			# [unsigned integer] ::= [digit]...
			if (preg_match('!(\d+\.?\d*|\.\d+)!A', $sql, $m, 0, $pos)){
				$tokens[] = substr($sql, $pos, strlen($m[0]));
				$pos += strlen($m[0]);
				continue;
			}

			# [approximate numeric literal> :: [mantissa] E [exponent]
			# [national character string literal]
			# [bit string literal]
			# [hex string literal]

			# [character string literal]
			if ($sql[$pos] == "'" || $sql[$pos] == '"'){
				$c = $pos+1;
				$q = $sql[$pos];
				while ($c < strlen($sql)){
					if ($sql[$c] == '\\'){
						$c += 2;
						continue;
					}
					if ($sql[$c] == $q){
						$slen = $c + 1 - $pos;
						$tokens[] = substr($sql, $pos, $slen);
						$pos += $slen;
						break;
					}
					$c++;
				}
				continue;
			}

			# [date string]
			# [time string]
			# [timestamp string]
			# [interval string]
			# [delimited identifier]
			# [SQL special character]
			# [not equals operator]
			# [greater than or equals operator]
			# [less than or equals operator]
			# [concatenation operator]
			# [double period]
			# [left bracket]
			# [right bracket]

			$tokens[] = substr($sql, $pos, 1);
			$pos++;
		}

		return $tokens;
	}


	function walk($tokens){
		#
		# split into statements
		#

		$tokens = $this->collapse_tokens($tokens);

		$statements = array();
		$temp = array();
		foreach ($tokens as $t){
			if ($t == ';'){
				if (count($temp)) $statements[] = $temp;
				$temp = array();
			}else{
				$temp[] = $t;
			}
		}
		if (count($temp)) $statements[] = $temp;


		#
		# find CREATE TABLE statements
		#

		$tables = array();

		foreach ($statements as $s){

			if ($s[0] == 'CREATE TABLE'){

				array_shift($s);

				$table = $this->parse_create_table($s);
				$tables[$table['name']] = $table;
			}

			if ($s[0] == 'CREATE TEMPORARY TABLE'){

				array_shift($s);

				$table = $this->parse_create_table($s);
				$table['props']['temp'] = true;
				$tables[$table['name']] = $table;
			}
		}

		return array(
			'tables' => $tables,
		);
	}


	function parse_create_table($tokens){

		if ($tokens[0] == 'IF NOT EXISTS'){
			array_shift($tokens);
		}


		#
		# name
		#

		$name = $this->decode_identifier(array_shift($tokens));


		#
		# CREATE TABLE x LIKE y
		#

		if ($this->next_tokens($tokens, 'LIKE')){
			array_shift($tokens);
			$old_name = $this->decode_identifier(array_shift($tokens));

			return array(
				'name'	=> $name,
				'like'	=> $old_name,
			);
		}


		#
		# create_definition
		#

		$fields = array();

		if ($this->next_tokens($tokens, '(')){
			array_shift($tokens);
			$ret = $this->parse_create_definition($tokens);
			$fields = $ret['fields'];
			$indexes = $ret['indexes'];
		}

		$props = $this->parse_table_props($tokens);


		$table = array(
			'name'		=> $name,
			'fields'	=> $fields,
			'indexes'	=> $indexes,
			'props'		=> $props,
		);

		if (count($tokens)) $table['more'] = $tokens;

		return $table;
	}


	function next_tokens($tokens){

		$args = func_get_args();
		array_shift($args);

		$i = 0;
		foreach ($args as $v){
			if (strtoupper($tokens[$i]) != $v)return false;
			$i++;
		}
		return true;
	}

	function parse_create_definition(&$tokens){

		$fields = array();
		$indexes = array();

		while ($tokens[0] != ')'){

			$these_tokens = $this->slice_until_next_field($tokens);

			$this->parse_field_or_key($these_tokens, $fields, $indexes);
		}

		array_shift($tokens); # closing paren

		return array(
			'fields'	=> $fields,
			'indexes'	=> $indexes,
		);
	}

	function parse_field_or_key(&$tokens, &$fields, &$indexes){

		#
		# parse a single create_definition
		#

		$has_constraint = false;
		$constraint = null;


		#
		# constraints can come before a few different things
		#

		if ($tokens[0] == 'CONSTRAINT'){

			$has_constraint = true;

			if ($tokens[1] == 'PRIMARY KEY'
				|| $tokens[1] == 'UNIQUE'
				|| $tokens[1] == 'UNIQUE KEY'
				|| $tokens[1] == 'UNIQUE INDEX'
				|| $tokens[1] == 'FOREIGN KEY'){
				array_shift($tokens);
			}else{
				array_shift($tokens);
				$constraint = array_shift($tokens);
			}
		}


		switch ($tokens[0]){

			#
			# named indexes
			#
			# INDEX		[index_name]	[index_type] (index_col_name,...) [index_option] ...
			# KEY		[index_name]	[index_type] (index_col_name,...) [index_option] ...
			# UNIQUE	[index_name]	[index_type] (index_col_name,...) [index_option] ...
			# UNIQUE INDEX	[index_name]	[index_type] (index_col_name,...) [index_option] ...
			# UNIQUE KEY	[index_name]	[index_type] (index_col_name,...) [index_option] ...
			#

			case 'INDEX':
			case 'KEY':
			case 'UNIQUE':
			case 'UNIQUE INDEX':
			case 'UNIQUE KEY':

				$index = array(
					'type' => 'INDEX',
				);

				if ($tokens[0] == 'UNIQUE'	) $index['type'] = 'UNIQUE';
				if ($tokens[0] == 'UNIQUE INDEX') $index['type'] = 'UNIQUE';
				if ($tokens[0] == 'UNIQUE KEY'	) $index['type'] = 'UNIQUE';

				array_shift($tokens);

				if ($tokens[0] != '(' && $tokens[0] != 'USING BTREE' && $tokens[0] != 'USING HASH'){
					$index['name'] = $this->decode_identifier(array_shift($tokens));
				}

				$this->parse_index_type($tokens, $index);
				$this->parse_index_columns($tokens, $index);
				$this->parse_index_options($tokens, $index);


				if (count($tokens)) $index['more'] = $tokens;
				$indexes[] = $index;
				return;


			#
			# PRIMARY KEY [index_type] (index_col_name,...) [index_option] ...
			#

			case 'PRIMARY KEY':

				$index = array(
					'type'	=> 'PRIMARY',
				);

				array_shift($tokens);

				$this->parse_index_type($tokens, $index);
				$this->parse_index_columns($tokens, $index);
				$this->parse_index_options($tokens, $index);

				if (count($tokens)) $index['more'] = $tokens;
				$indexes[] = $index;
				return;


			# FULLTEXT		[index_name] (index_col_name,...) [index_option] ...
			# FULLTEXT INDEX	[index_name] (index_col_name,...) [index_option] ...
			# FULLTEXT KEY		[index_name] (index_col_name,...) [index_option] ...
			# SPATIAL		[index_name] (index_col_name,...) [index_option] ...
			# SPATIAL INDEX		[index_name] (index_col_name,...) [index_option] ...
			# SPATIAL KEY		[index_name] (index_col_name,...) [index_option] ...

			case 'FULLTEXT':
			case 'FULLTEXT INDEX':
			case 'FULLTEXT KEY':
			case 'SPATIAL':
			case 'SPATIAL INDEX':
			case 'SPATIAL KEY':

				$index = array(
					'type' => 'FULLTEXT',
				);

				if ($tokens[0] == 'SPATIAL'	) $index['type'] = 'SPATIAL';
				if ($tokens[0] == 'SPATIAL INDEX') $index['type'] = 'SPATIAL';
				if ($tokens[0] == 'SPATIAL KEY'	) $index['type'] = 'SPATIAL';

				array_shift($tokens);

				if ($tokens[0] != '('){
					$index['name'] = $this->decode_identifier(array_shift($tokens));
				}

				$this->parse_index_type($tokens, $index);
				$this->parse_index_columns($tokens, $index);
				$this->parse_index_options($tokens, $index);

				if (count($tokens)) $index['more'] = $tokens;
				$indexes[] = $index;
				return;


			# older stuff

			case 'CHECK':

				$fields[] = array(
					'_'		=> 'CHECK',
					'tokens'	=> $tokens,
				);
				return;
		}

		$fields[] = $this->parse_field($tokens);
	}

	function slice_until_next_field(&$tokens){

		$out = array();
		$stack = 0;

		while (count($tokens)){
			$next = $tokens[0];
			if ($next == '('){
				$stack++;
				$out[] = array_shift($tokens);
			}elseif ($next == ')'){
				if ($stack){
					$stack--;
					$out[] = array_shift($tokens);
				}else{
					return $out;
				}
			}elseif ($next == ','){
				if ($stack){
					$out[] = array_shift($tokens);
				}else{
					array_shift($tokens);
					return $out;
				}
			}else{
				$out[] = array_shift($tokens);
			}
		}

		return $out;
	}

	function parse_field($tokens){

		$f = array(
			'name'	=> $this->decode_identifier(array_shift($tokens)),
			'type'	=> strtoupper(array_shift($tokens)),
		);

		switch ($f['type']){

			# DATE
			case 'DATE':
			case 'TIME':
			case 'TIMESTAMP':
			case 'DATETIME':
			case 'YEAR':
			case 'TINYBLOB':
			case 'BLOB':
			case 'MEDIUMBLOB':
			case 'LONGDATE':

				# nothing more to read
				break;


			# TINYINT[(length)] [UNSIGNED] [ZEROFILL]
			case 'TINYINT':
			case 'SMALLINT':
			case 'MEDIUMINT':
			case 'INT':
			case 'INTEGER':
			case 'BIGINT':

				$this->parse_field_length($tokens, $f);
				$this->parse_field_unsigned($tokens, $f);
				$this->parse_field_zerofill($tokens, $f);
				break;


			# REAL[(length,decimals)] [UNSIGNED] [ZEROFILL]
			case 'REAL':
			case 'DOUBLE':
			case 'FLOAT':

				$this->parse_field_length_deciamsl($tokens, $f);
				$this->parse_field_unsigned($tokens, $f);
				$this->parse_field_zerofill($tokens, $f);
				break;


			# DECIMAL[(length[,decimals])] [UNSIGNED] [ZEROFILL]
			case 'DECIMAL':
			case 'NUMERIC':

				$this->parse_field_length_deciamsl($tokens, $f);
				$this->parse_field_length($tokens, $f);
				$this->parse_field_unsigned($tokens, $f);
				$this->parse_field_zerofill($tokens, $f);
				break;


			# BIT[(length)]
			# BINARY[(length)]
			case 'BIT':
			case 'BINARY':

				$this->parse_field_length($tokens, $f);
				break;


			# VARBINARY(length)
			case 'VARBINARY':

				$this->parse_field_length($tokens, $f);
				break;

			# CHAR[(length)] [CHARACTER SET charset_name] [COLLATE collation_name]
			case 'CHAR':

				$this->parse_field_length($tokens, $f);
				$this->parse_field_charset($tokens, $f);
				$this->parse_field_collate($tokens, $f);
				break;

			# VARCHAR(length) [CHARACTER SET charset_name] [COLLATE collation_name]
			case 'VARCHAR':

				$this->parse_field_length($tokens, $f);
				$this->parse_field_charset($tokens, $f);
				$this->parse_field_collate($tokens, $f);
				break;

			# TINYTEXT   [BINARY] [CHARACTER SET charset_name] [COLLATE collation_name]
			# TEXT       [BINARY] [CHARACTER SET charset_name] [COLLATE collation_name]
			# MEDIUMTEXT [BINARY] [CHARACTER SET charset_name] [COLLATE collation_name]
			# LONGTEXT   [BINARY] [CHARACTER SET charset_name] [COLLATE collation_name]
			case 'TINYTEXT':
			case 'TEXT':
			case 'MEDIUMTEXT':
			case 'LONGTEXT':

				# binary
				$this->parse_field_charset($tokens, $f);
				$this->parse_field_collate($tokens, $f);
				break;

			# ENUM(value1,value2,value3,...) [CHARACTER SET charset_name] [COLLATE collation_name]
			# SET (value1,value2,value3,...) [CHARACTER SET charset_name] [COLLATE collation_name]
			case 'ENUM':
			case 'SET':

				$f['values'] = $this->parse_value_list($tokens);
				$this->parse_field_charset($tokens, $f);
				$this->parse_field_collate($tokens, $f);
				break;

			default:
				;//无类型("Unsupported field type: {$f['type']}");
		}


		# [NOT NULL | NULL]
		if (isset($tokens[0]) && strtoupper($tokens[0]) == 'NOT NULL'){
			$f['null'] = false;
			array_shift($tokens);
		}
		if (isset($tokens[0]) && strtoupper($tokens[0]) == 'NULL'){
			$f['null'] = true;
			array_shift($tokens);
		}

		# [DEFAULT default_value]
		if(isset($tokens[0]) && strtoupper($tokens[0]) == 'DEFAULT'){
			$f['default'] = $this->decode_value($tokens[1]);
			array_shift($tokens);
			array_shift($tokens);
		}

		# [AUTO_INCREMENT]
		if (isset($tokens[0]) && strtoupper($tokens[0]) == 'AUTO_INCREMENT'){
			$f['auto_increment'] = true;
			array_shift($tokens);
		}

		# [UNIQUE [KEY] | [PRIMARY] KEY]
		# [COMMENT 'string']
		# [COLUMN_FORMAT {FIXED|DYNAMIC|DEFAULT}]
		# [STORAGE {DISK|MEMORY|DEFAULT}]
		# [reference_definition]

		if (count($tokens)) $f['more'] = $tokens;

		return $f;
	}

	function parse_table_props(&$tokens){

		$alt_names = array(
			'CHARACTER SET'		=> 'CHARSET',
			'DEFAULT CHARACTER SET'	=> 'CHARSET',
			'DEFAULT CHARSET'	=> 'CHARSET',
			'DEFAULT COLLATE'	=> 'COLLATE',
		);

		$props = array();

		while (count($tokens)){

		switch (strtoupper($tokens[0])){
			case 'ENGINE':
			case 'AUTO_INCREMENT':
			case 'AVG_ROW_LENGTH':
			case 'CHECKSUM':
			case 'COMMENT':
			case 'CONNECTION':
			case 'DELAY_KEY_WRITE':
			case 'INSERT_METHOD':
			case 'KEY_BLOCK_SIZE':
			case 'MAX_ROWS':
			case 'MIN_ROWS':
			case 'PACK_KEYS':
			case 'PASSWORD':
			case 'ROW_FORMAT':
			case 'COLLATE':
			case 'CHARSET':
			case 'DATA DIRECTORY':
			case 'INDEX DIRECTORY':
				$prop = strtoupper(array_shift($tokens));
				if ($tokens[0] == '=') array_shift($tokens);
				$props[$prop] = array_shift($tokens);
				if ($tokens[0] == ',') array_shift($tokens);
				break;

			case 'CHARACTER SET':
			case 'DEFAULT COLLATE':
			case 'DEFAULT CHARACTER SET':
			case 'DEFAULT CHARSET':
				$prop = $alt_names[strtoupper(array_shift($tokens))];
				if (isset($tokens[0]) && $tokens[0] == '=') array_shift($tokens);
				$props[$prop] = array_shift($tokens);
				if (isset($tokens[0]) && $tokens[0] == ',') array_shift($tokens);
				break;

			default:
				break 2;
		}
		}

		return $props;
	}



	# We can simplify parsing by merging certain tokens when
	# they occur next to each other. MySQL treats these productions
	# equally: 'UNIQUE|UNIQUE INDEX|UNIQUE KEY' and if they are
	# all always a single token it makes parsing easier.

	function collapse_tokens($tokens){

		$lists = array(
			'FULLTEXT INDEX',
			'FULLTEXT KEY',
			'SPATIAL INDEX',
			'SPATIAL KEY',
			'FOREIGN KEY',
			'USING BTREE',
			'USING HASH',
			'PRIMARY KEY',
			'UNIQUE INDEX',
			'UNIQUE KEY',
			'CREATE TABLE',
			'CREATE TEMPORARY TABLE',
			'DATA DIRECTORY',
			'INDEX DIRECTORY',
			'DEFAULT CHARACTER SET',
			'CHARACTER SET',
			'DEFAULT CHARSET',
			'DEFAULT COLLATE',
			'IF NOT EXISTS',
			'NOT NULL',
			'WITH PARSER',
		);

		$singles = array(
			'NULL',
			'CONSTRAINT',
			'INDEX',
			'KEY',
			'UNIQUE',
		);


		$maps = array();
		foreach ($lists as $l){
			$a = explode(' ', $l);
			$maps[$a[0]][] = $a;
		}
		$smap = array();
		foreach ($singles as $s) $smap[$s] = 1;

		$out = array();
		$i = 0;
		$len = count($tokens);
		while ($i < $len){
			$next = strtoupper($tokens[$i]);
			if(isset($maps[$next]) && is_array($maps[$next])){
				$found = false;
				foreach ($maps[$next] as $list){
					$fail = false;
					foreach ($list as $k => $v){
						if ($v != strtoupper($tokens[$k+$i])){
							$fail = true;
							break;
						}
					}
					if (!$fail){
						$i += count($list);
						$out[] = implode(' ', $list);
						$found = true;
						break;
					}
				}
				if ($found) continue;
			}
			if (isset($smap[$next]) && $smap[$next]){
				$out[] = $next;
				$i++;
				continue;
			}
			$out[] = $tokens[$i];
			$i++;
		}

		return $out;
	}

	function parse_index_type(&$tokens, &$index){
		if (isset($tokens[0]) && $tokens[0] == 'USING BTREE'){ $index['mode'] = 'btree'; array_shift($tokens); }
		if (isset($tokens[0]) && $tokens[0] == 'USING HASH' ){ $index['mode'] = 'hash'; array_shift($tokens); }
	}

	function parse_index_columns(&$tokens, &$index){

		# col_name [(length)] [ASC | DESC]

		if ($tokens[0] != '(') return;
		array_shift($tokens);

		while (true){

			$col = array(
				'name' => $this->decode_identifier(array_shift($tokens)),
			);

			if ($tokens[0] == '(' && $tokens[2] == ')'){
				$col['length'] = $tokens[1];
				array_shift($tokens);
				array_shift($tokens);
				array_shift($tokens);
			}

			if (strtoupper($tokens[0]) == 'ASC'){
				$col['direction'] = 'asc';
				array_shift($tokens);
			}elseif (strtoupper($tokens[0]) == 'DESC'){
				$col['direction'] = 'desc';
				array_shift($tokens);
			}

			$index['cols'][] = $col;

			if ($tokens[0] == ')'){
				array_shift($tokens);
				return;
			}

			if ($tokens[0] == ','){
				array_shift($tokens);
				continue;
			}

			# hmm, an unexpected token
			return;
		}
	}

	function parse_index_options(&$tokens, &$index){
		# index_option:
		#    KEY_BLOCK_SIZE [=] value
		#  | index_type
		#  | WITH PARSER parser_name

		if (isset($tokens[0]) && $tokens[0] == 'KEY_BLOCK_SIZE'){
			array_shift($tokens);
			if ($tokens[0] == '=') array_shift($tokens);
			$index['key_block_size'] = $tokens[0];
			array_shift($tokens);
		}

		$this->parse_index_type($tokens, $index);

		if (isset($tokens[0]) && $tokens[0] == 'WITH PARSER'){
			$index['parser'] = $tokens[1];
			array_shift($tokens);
			array_shift($tokens);
		}
	}


	#
	# helper functions for parsing bits of field definitions
	#

	function parse_field_length(&$tokens, &$f){
		if ($tokens[0] == '(' && $tokens[2] == ')'){
			$f['length'] = $tokens[1];
			array_shift($tokens);
			array_shift($tokens);
				array_shift($tokens);
			}
	}

	function parse_field_length_deciamsl(&$tokens, &$f){
		if ($tokens[0] == '(' && $tokens[2] == ',' && $tokens[4] == ')'){
			$f['length'] = $tokens[1];
			$f['decimals'] = $tokens[3];
			array_shift($tokens);
			array_shift($tokens);
			array_shift($tokens);
			array_shift($tokens);
			array_shift($tokens);
		}
	}

	function parse_field_unsigned(&$tokens, &$f){
		if (strtoupper($tokens[0]) == 'UNSIGNED'){
			$f['unsigned'] = true;
			array_shift($tokens);
		}
	}

	function parse_field_zerofill(&$tokens, &$f){
		if(isset($tokens[0]) && strtoupper($tokens[0]) == 'ZEROFILL'){
			$f['zerofill'] = true;
			array_shift($tokens);
		}
	}

	function parse_field_charset(&$tokens, &$f){
		if(isset($tokens[0]) && strtoupper($tokens[0]) == 'CHARACTER SET'){
			$f['character_set'] = $tokens[1];
			array_shift($tokens);
			array_shift($tokens);
		}
	}

	function parse_field_collate(&$tokens, &$f){
		if (isset($tokens[0]) && strtoupper($tokens[0]) == 'COLLATE'){
			$f['collation'] = $tokens[1];
			array_shift($tokens);
			array_shift($tokens);
		}
	}

	function parse_value_list(&$tokens){
		if (isset($tokens[0]) && $tokens[0] != '(') return null;
		array_shift($tokens);

		$values = array();
		while (count($tokens)){

			if ($tokens[0] == ')'){
				array_shift($tokens);
				return $values;
			}

			$values[] = $this->decode_value(array_shift($tokens));

			if ($tokens[0] == ')'){
				array_shift($tokens);
				return $values;
			}

			if ($tokens[0] == ','){
				array_shift($tokens);
			}else{
				# error
				return $values;
			}
		}
	}

	function decode_identifier($token){
		if ($token[0] == '`'){
			return substr($token, 1, -1);
		}
		return $token;
	}

	function decode_value($token){

		#
		# decode strings
		#

		if ($token[0] == "'" || $token[0] == '"'){
			$map = array(
				'n'	=> "\n",
				'r'	=> "\r",
				't'	=> "\t",
			);
			$out = '';
			for ($i=1; $i<strlen($token)-1; $i++){
				if ($token[$i] == '\\'){
					if ($map[$token[$i+1]]){
						$out .= $map[$token[$i+1]];
					}else{
						$out .= $token[$i+1];
					}
					$i++;
				}else{
					$out .= $token[$i];
				}
			}
			return $out;
		}

		return $token;
	}
}
