<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Yaml_forge {

	var $debug = TRUE;
	var $yaml_file;
	var $yaml_arr;

	var $auto_id = TRUE;
	var $drop_tables = TRUE;

	function __construct()
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

	function set_debug($bool)
	{
		$this->debug = $bool;
	}

	function set_auto_id($bool)
	{
		$this->auto_id = $bool;
	}

	function set_drop_tables($bool)
	{
		$this->drop_tables = $bool;
	}

	function parse( $path )
	{
		$this->yaml_file = read_file( $path );
		$this->yaml_arr = $this->yaml->parse_string($this->yaml_file);

		return $this->yaml_arr;
	}

	function generate( $path )
	{
		if( ! $this->parse( $path ) )
		{
			return FALSE;
		}

		if($this->debug) print_r($this->yaml_arr);

		foreach($this->yaml_arr as $table => $defs)
		{
			if($this->drop_tables)
			{
				if(isset($table['fields']))
				{
					$this->dbforge->drop_table($table);
				}
				elseif(isset($table['data']))
				{
					$this->dbforge->truncate_table($table);
				}
			}

			if($this->auto_id)
			{
				$this->dbforge->add_field('id');
			}

			if(isset($defs['has_many']))
			{
				foreach($defs['has_many'] as $rel_table)
				{
					$sort_arr = array($table, $rel_table);
					sort($sort_arr);
					$_table = $sort_arr[0]."_".$sort_arr[1];

					$this->has_many[$_table] = array(
						singular($table) . "_id" => array(
							'type' => 'INT',
							'unsigned' => TRUE
						),
						singular($rel_table) . "_id" => array(
							'type' => 'INT',
							'unsigned' => TRUE
						)
					);
				}
			}

			if(isset($defs['has_one']))
			{
				foreach($defs['has_one'] as $rel_table)
				{
					$this->dbforge->add_field(
						array(
							singular($rel_table) . "_id" => array(
								'type' => 'INT',
								'unsigned' => TRUE
							)
						)
					);
				}
			}

			$fields = isset($defs['fields']) ? $defs['fields'] : array();

			foreach($fields as $name => $field)
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
					$field = preg_replace('/^(varchar|char):([0-9]{1,3})$/', '$1($2)', $field);

					if($field == 'int')
					{
						$field .= " unsigned";
					}

					$this->dbforge->add_field($name ." ". $field);
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

			// insert data
			$data = isset($defs['data']) ? $defs['data'] : array();

			// Deal with data
			if( is_array($data) )
			{
				$insert = array();

				foreach($data as $d_id => $d)
				{
					
					$columns = $this->db->list_fields($table);

					foreach($columns as $i => $k)
					{
						if($k == 'id')
						{
							unset($columns[$i]);
							$insert['id'] = $d_id;
						}
					}

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

			// Create the "has many" tables
			if( ! empty($this->has_many))
			{
				$this->dbforge->add_field('id');
				foreach($this->has_many as $table => $fields)
				{
					$this->dbforge->add_field($fields);
					$this->dbforge->create_table($table, TRUE);
				}
			}
		}


	}

}
