<?php

//namespace Incentives;
/**
*
*/
class Database{

	/*** Wordpress Variables ***/
	protected $o_wpdb;
	/*** Setup Variables ***/
	protected $s_DbTableName;
	/*** Plugin Variables ***/
	protected $a_columnNames;
	protected $a_columnNamesAndFormat = array(
		'ID' 					=> '%d',
		'model_code' 			=> '%s',
		'option_code' 			=> '%s',
		'full_option_code' 		=> '%s',
        'option_name'           => '%s',
    	'description' 			=> '%s',
    	'msrp' 				    => '%s',
    	'invoice' 				=> '%s',
    );


	function __construct(){

		global $wpdb;

		$this->o_wpdb = $wpdb;
		$this->s_DbTableName = $this->o_wpdb->prefix . "vehicle_options";
	}

	public function getColumnNamesAndFormat(){
		return $this->a_columnNamesAndFormat;
	}
	public function getColumnNames(){
		if( $this->a_columnNames === null ){
			$this->a_columnNames = array_keys( $this->a_columnNamesAndFormat );
		}
		return $this->a_columnNames;
	}

	public function insertRow( array $a_preparedValues ){
                    //  $this->pre_var_dump($a_preparedValues, 'a_preparedValues' );
        $a_columnNames = $this->getColumnNamesAndFormat();
                    // $this->pre_var_dump($a_columnNames, 'a_columnames');
		ksort( $a_columnNames );
        $a_values = array_intersect_key( $a_preparedValues, $a_columnNames );
                    // $this->pre_var_dump($a_values, 'a_values');
		ksort( $a_values );
        $a_format = array_values( array_intersect_key( $a_columnNames, $a_values ) );
                    // $this->pre_var_dump($a_format, 'a_format');
		return $this->o_wpdb->insert(
			$this->s_DbTableName, //table name
			$a_values, //insert values
			$a_format //insert format
		);
    }


    public function deleteOptionInDB($s_code){
        return $this->o_wpdb->delete( $this->s_DbTableName, array('full_option_code'=>$s_code));
    }


    public function checkIfOptionInDB($s_code){
        return $this->o_wpdb->get_results(
			$this->o_wpdb->prepare(
				"SELECT *
				FROM $this->s_DbTableName
				WHERE full_option_code = %s"
				, $s_code
			), 'ARRAY_A'
		);
	}
	
	public function getAllOptionsFromDB(){
				return $this->o_wpdb->get_results(
						"SELECT option_code
						FROM $this->s_DbTableName
						WHERE '1' = '1' "
				);
			}


    public function updateRow( array $a_preparedValues ){
    	return $this->o_wpdb->update(
			$this->s_DbTableName,
			$a_preparedValues['data'],
			$a_preparedValues['where'],
			$a_preparedValues['format'],
			$a_preparedValues['whereFormat']
        );
    }


    public function getTableContents(){
		return $this->o_wpdb->get_results( "SELECT * FROM $this->s_DbTableName", ARRAY_A );
	}


	public function truncateTable(){
		return $this->o_wpdb->query('TRUNCATE TABLE ' . $this->s_DbTableName . ';' );
	}

	public function dropTable(){
		return $this->o_wpdb->query('DROP TABLE IF EXISTS ' . $this->s_DbTableName .';' );
	}

	public function createTable(){
        $s_charsetCollate = $this->o_wpdb->get_charset_collate();
        $s_SQL =
            "CREATE TABLE IF NOT EXISTS `" . $this->s_DbTableName . "` (
                `ID` INT NOT NULL AUTO_INCREMENT,
				`option_code` VARCHAR(12) NULL,
				`full_option_code` VARCHAR(12) NULL,
                `option_name` VARCHAR(55) NULL,
	  			`description` VARCHAR(255) NULL,
	  			`msrp` VARCHAR(12) NULL,
	  			`invoice` VARCHAR(12) NULL
			) " . $s_charsetCollate . ";";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $s_SQL );
    }
}