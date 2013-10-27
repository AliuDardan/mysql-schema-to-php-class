<?php

class MySQLSchemaToPHPClass {

	private $connection;
	private $tab = "\t";
	private $output_folder = 'model/';

	public function __construct($database_host, $database_user, $database_pass, $database_name) {
		$this->connection = mysqli_connect($database_host, $database_user, $database_pass, $database_name) or die('Database Connection Error: ' . mysqli_connect_error());
	}

	public function __destruct() {
		mysqli_close($this->connection);
	}

	public function execute() {
		$schema_map = $this->get_schema_map();
		$this->write_schema_map_to_class_file($schema_map);
	}

	public function get_schema_map() {
		$tables = $this->get_schema_tables();
		$schema_map = array();

		foreach ($tables as $table) {
			$schema_map[$table] = array();
			$schema_map[$table]['primary_key'] = $this->get_table_primary_key($table);
			$schema_map[$table]['fields'] = array();
			$schema_map[$table]['relations'] = array();
		}

		foreach ($tables as $table) {
			// Table Fields
			$query = "SHOW COLUMNS FROM $table";
			if ($result = mysqli_query($this->connection, $query)) {
				while ($property = mysqli_fetch_assoc($result)) {
					$schema_map[$table]['fields'][$property['Field']] = array(
						'type' => $property['Type'],
						'nullable' => $property['Null'],
						'key' => $property['Key'],
						'default' => $property['Default'],
						'extra' => $property['Extra'],
					);
				}
			}

			// Table Relations
			$result_rel = mysqli_query($this->connection, "SHOW CREATE TABLE $table;");
			if ($result_rel !== false) {
				while ($row_rel = mysqli_fetch_array($result_rel)) {
					if (preg_match_all('/CONSTRAINT `(.*)` FOREIGN KEY \(`(.*)`\) REFERENCES `(.*)` \(`(.*)`\)/', $row_rel['Create Table'], $relations)) {

						$rels_foreign_key = $relations[2];
						$rels_reference_table = $relations[3];
						$rels_reference_key = $relations[4];

						for ($i = 0; $i < count($rels_reference_table); $i++) {
							$foreign_key = $rels_foreign_key[$i];
							$reference_table = $rels_reference_table[$i];
							$reference_key = $rels_reference_key[$i];

							list($relation_name_parent, $relation_name_child) = array($reference_table, $table);

							$schema_map[$table]['relations'][] = array(
								'type' => 'belongs_to',
								'name' => $relation_name_parent,
								'foreign_key' => $foreign_key,
								'reference_table' => $reference_table,
								'reference_key' => $reference_key
							);
							$schema_map[$reference_table]['relations'][] = array(
								'type' => 'has_many',
								'name' => $relation_name_child,
								'foreign_key' => $foreign_key,
								'reference_table' => $table,
								'reference_key' => $reference_key
							);
						}
					}
				}
			}
		}

		return $schema_map;
	}

	// Get Schema Tables Array
	private function get_schema_tables() {
		$table_list = array();
		$result = mysqli_query($this->connection, "SHOW TABLES");
		while ($row = mysqli_fetch_array($result)) {
			$table_list[] = $row[0];
		}
		return $table_list;
	}

	// Table Primary Key
	private function get_table_primary_key($table) {
		if ($result = mysqli_query($this->connection, "SHOW KEYS FROM $table WHERE Key_name = 'PRIMARY'")) {
			$info = $result->fetch_assoc();
			return $info['Column_name'];
		}
		return '';
	}

	// Here you can fix the formatting to your IDE
	private function write_schema_map_to_class_file($schema_map) {
		$this->log("<b>MySQL Schema To PHP Class:</b>");

		foreach ($schema_map as $table_name => $table) {
			ob_start();
			echo '<?php' . PHP_EOL . PHP_EOL;
			echo 'class Base_' . $this->camelize($table_name) . ' extends Model {' . PHP_EOL . PHP_EOL;

			// Class Properties
			foreach ($schema_map[$table_name]['fields'] as $property => $property_meta) {
				$value = $property_meta['default'];
				if (is_numeric($value)) {
					$value = ' = ' . $value;
				} else if ($value != null) {
					$value = ' = \'' . addslashes($value) . '\'';
				}

				echo $this->tab . 'protected $' . $property . $value . ';' . PHP_EOL;
			}
			echo PHP_EOL;

			// Properties Property
			echo $this->tab . 'protected $__properties_meta = array(' . PHP_EOL;
			$i = 0;
			$num_properties = count($schema_map[$table_name]['fields']);
			foreach ($schema_map[$table_name]['fields'] as $property => $property_meta) {
				$comma = ($i == $num_properties - 1) ? '' : ',';

				@list($field_type, $field_size) = preg_split('/[()]/', $property_meta['type']);
				if (!isset($field_size)) {
					$field_type = $property_meta['type'];
				}

				echo $this->tab . $this->tab . "'" . $property . "' => array(";
				echo "'type' => '" . $field_type . "', ";
				echo "'nullable' => " . (($property_meta['nullable'] == "YES") ? 'true' : 'false') . ", ";
				echo "'key' => '" . $property_meta['key'] . "', ";
				echo "'default_value' => '" . $property_meta['default'] . "', ";
				echo "'extra' => '" . $property_meta['extra'] . "'";
				echo ')' . $comma . PHP_EOL;
				$i++;
			}
			echo $this->tab . ');' . PHP_EOL;
			echo PHP_EOL;

			// Constructor
			echo $this->tab . 'public function __construct() {' . PHP_EOL;

			// Relations
			foreach ($schema_map[$table_name]['relations'] as $relation) {
				echo $this->tab . $this->tab . '$this->' . $relation['type'] . '("' . $relation['name'] . '", "' . $this->camelize($relation['reference_table']) . '", "' . $relation['reference_key'] . '", "' . $relation['foreign_key'] . '");' . PHP_EOL;
			}
			echo PHP_EOL;

			echo $this->tab . $this->tab . '// parent::__construct();' . PHP_EOL;
			echo $this->tab . $this->tab . '// parent::__construct(get_class($this), "' . $table_name . '", "' . $schema_map[$table_name]['primary_key'] . '");' . PHP_EOL;
			echo $this->tab . '}' . PHP_EOL;

			echo PHP_EOL;
			echo '}' . PHP_EOL;
			echo PHP_EOL;
			echo '?>';

			$class_contents = ob_get_contents();
			ob_end_clean();

			$fp = fopen($this->output_folder . $table_name . '.php', 'w');
			if ($fp) {
				fwrite($fp, $class_contents);
				fclose($fp);
				$this->log('Table <b>\'' . $table_name . '\'</b> was converted to class in <b>\'' . $this->output_folder . $table_name . '.php\'</b>');
			} else {
				$this->log('The path: \'' . $this->output_folder . $table_name . '.php\' is not writable');
			}
		}
	}

	///////////////////////// Helpers /////////////////////////

	function camelize($word) {
		return preg_replace('/(^|_)([a-z])/e', 'strtoupper("\\2")', $word);
	}

	private function log($msg) {
		echo sprintf('<p>%s</p>', $msg);
	}

}

?>