<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Yaml_forge {

	var $debug = TRUE;
	var $yaml_file;
	var $yaml_arr;

	var $auto_id = TRUE;
	var $drop_tables = TRUE;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->helper('file');
		$this->CI->load->helper('inflector');
		$this->CI->load->dbforge();
		$this->CI->load->spark('yaml/1.0.0');

		$this->db =& $this->CI->db;
		$this->yaml =& $this->CI->yaml;
		$this->dbforge =& $this->CI->dbforge;
	}

	public function set_debug($bool)
	{
		$this->debug = $bool;
	}

	public function set_auto_id($bool)
	{
		$this->auto_id = $bool;
	}

	public function set_drop_tables($bool)
	{
		$this->drop_tables = $bool;
	}

	public function parse( $path )
	{
		$this->yaml_file = read_file( $path );
		$this->yaml_arr = $this->yaml->parse_string($this->yaml_file);

		return $this->yaml_arr;
	}

	public function generate( $path )
	{
		if( ! $this->parse( $path ) )
		{
			return FALSE;
		}

		if($this->debug) print_r($this->yaml_arr);

		foreach($this->yaml_arr as $table_name => $operations)
		{
			// some operations depend on order being maintained
			$operations_order = array('fields', 'has_one', 'has_many', 'key', 'keys', 'column', 'columns', 'drop_column', 'drop_columns', 'modify_columns', 'data');

			// be helpful and display and operations not permitted
			foreach($operations as $op_name => $ops)
			{
				if( ! in_array($op_name, $operations_order) )
				{
					if($this->debug) echo "$table_name - operation: $op_name not defined! Check your spelling or the docs...\n";
				}
			}

			foreach($operations_order as $op_name)
			{
				if(isset($operations[$op_name]))
				{
					if($this->debug) echo "$table_name - operation: $op_name\n";

					$meth = "add_" . $op_name;

					if( ! method_exists($this, $meth))
					{
						exit('Operation not defined: ' . $op_name);
					}

					$this->$meth($table_name, $operations[$op_name]);
				}
			}
		}

	}

	private function add_data($table, $data)
	{
		// Deal with data
		if( ! is_array($data) )
		{
			exit($table . ': data must be defined as an associative array');
		}

		foreach($data as $d_id => $d)
		{
			if( ! is_array($d) )
			{
				echo "Oops, you data must be defined as an array...";
				continue;
			}


			$columns = $this->db->list_fields($table);

			$is_assoc = (is_array($d) AND ( ! count($d) OR count(array_filter(array_keys($d),'is_string')) == count($d)));

			// Handle associative arrays
			if($is_assoc)
			{
				$d['id'] = $d_id;
				$this->db->insert($table, $d);
				continue;
			}

			// Handle non-associative arrays

			$insert = array();

			foreach($columns as $i => $k)
			{
				if($k == 'id')
				{
					unset($columns[$i]);
					$insert['id'] = $d_id;
				}
			}

			$columns = array_values($columns);

			foreach($columns as $i => $k)
			{
				if(isset($d[$i]))
				{
					$insert[$k] = $d[$i];
				}
			}

			$this->db->insert($table, $insert);

		}
	}

	private function add_has_one($table, $ops)
	{
		if( is_string($ops))
		{
			$ops = array($ops);
		}

		foreach($ops as $rel_table)
		{
			$this->dbforge->add_column(
				$table,
				array(
					singular($rel_table) . "_id" => array(
						'type' => 'INT',
						'unsigned' => TRUE
					)
				)
			);
		}
	}

	private function add_fields($table, $ops)
	{
		if($this->drop_tables)
		{
			$this->dbforge->drop_table($table);
		}

		if($this->auto_id)
		{
			$this->dbforge->add_field('id');
		}

		foreach($ops as $name => $field)
		{

			if($this->auto_id == TRUE AND $name == 'id')
			{
				continue;
			}

			if($this->auto_id == FALSE AND $name == 'id' AND empty($field))
			{
				$this->dbforge->add_field('id');
				continue;
			}

			if( ! is_array($field) )
			{
				$field = preg_replace('/^(varchar|char)\|([0-9]{1,3})$/', '$1($2)', $field);

				if($field == 'int')
				{
					$field .= " unsigned";
				}

				$this->dbforge->add_field("`" . $name ."` ". $field);
				continue;
			}
			
			if( ! isset($field['type']))
			{
				exit('Field must have type');
			}

			$field['type'] = strtoupper($field['type']);

			$this->dbforge->add_field(
				array($name => $field)
			);

		}
		// Finally, create the table
		$this->dbforge->create_table($table, TRUE);
	}

	private function add_columns($table, $ops)
	{
		foreach($ops as $name => $field)
		{
			$this->dbforge->add_column($table, array(
				$name => $field
				)
			);
		}
	}

	private function add_modify_columns($table, $ops)
	{
		foreach($ops as $name => $field)
		{
			$this->dbforge->modify_column($table, array(
				$name => $field
				)
			);
		}
	}

	private function add_key($table, $ops)
	{
		if(is_string($ops))
		{
			$ops = array($ops);
		}

		$this->add_keys($table, $ops);
	}

	private function add_keys($table, $ops)
	{
		foreach($ops as $key_name)
		{
			// DBForge doesn't have the support I'd like... so SQL it is
			$this->db->query("ALTER TABLE $table ADD key $key_name($key_name)");
		}
	}

	private function add_drop_column($table, $ops)
	{
		if( is_string($ops) )
		{
			$ops = array($ops);
		}

		$this->add_drop_columns($table, $ops);
	}

	private function add_drop_columns($table, $ops)
	{
		foreach($ops as $column)
		{
			$this->dbforge->drop_column($table, $column);
		}
	}

	private function add_has_many($table, $ops)
	{
		if( is_string($ops) )
		{
			$ops = array($ops);
		}

		foreach($ops as $rel_table)
		{
			$sort_arr = array($table, $rel_table);
			sort($sort_arr);
			$_table = $sort_arr[0]."_".$sort_arr[1];

			$this->dbforge->add_field('id');
			$this->dbforge->add_field(array(
					singular($table) . "_id" => array(
						'type' => 'INT',
						'unsigned' => TRUE
					),
					singular($rel_table) . "_id" => array(
						'type' => 'INT',
						'unsigned' => TRUE
					)
				)
			);

			// Create the "has many" tables
			$this->dbforge->create_table($table, TRUE);
		}



	}

}
