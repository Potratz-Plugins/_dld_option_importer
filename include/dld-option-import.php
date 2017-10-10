<?php
/*
* FUNCTIONS FOR dld-option-importer plugin
*
*/


function process_vehicle_options_from_csv_upload_and_update(){

    $target_dir = plugin_dir_path( __DIR__ )."UploadedFromDealer/"; 
    $s_fileName = basename($_FILES["fileToUpload"]["name"]);
    $target_file = $target_dir . $s_fileName;
    $s_option_key = 'filename_for_options_update';
    $uploadOk = 1;
    $imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);

    // Check if file already exists
    if (file_exists($target_file)) {
        echo "</br>FILE : <strong>$s_fileName</strong> will now be overwritten.</br>";
    }
    echo "</br>FILETYPE : <strong>$imageFileType</strong></br>"; 

    // Allow certain file formats
    if($imageFileType != "csv" ) {
        echo "</br>Sorry, only csv files are allowed.</br>";
        $uploadOk = 0;
    }

    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 0) {
        echo "</br>Sorry, your file was not uploaded.</br>";
    // if everything is ok, try to upload file
    } else {
        if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
            echo "</br>The file <strong>". $s_fileName. "</strong> has been uploaded.</br>";
            update_option( $s_option_key, $s_fileName, false );
        } else {
            echo "</br>Sorry, there was an error uploading your file.</br>";
            exit;
        }
    }

    process_and_update_vehicle_options();
}



function process_and_update_vehicle_options(){
    $s_option_key = 'filename_for_options_update';

    // GET OPTION for file name of last uploaded csv
    if(get_option($s_option_key)){
        $s_fileName = get_option($s_option_key);
    } else {
        echo "</br>ERROR - there is no valid csv file uploaded";
        exit;
    }

    // Set the page timeout to 2.5 hours.
    set_time_limit(2000);

    // Includes
    // Need to require the config file to have access to the wordpress functions.
    // TEST - NEEDED??? require_once( "../../../wp-load.php" );
    require_once( ABSPATH."wp-admin/includes/file.php" );
    require_once( ABSPATH."wp-admin/includes/media.php" );
    require_once( ABSPATH."wp-admin/includes/image.php" );


    // FOR DATABASE
    $o_db = new Database();
    // TEST - NLINE - DELTE ALL DATA FROM dld_vehicle_options 
    $o_db->truncateTable();

    global $wpdb;
    $o_wpdb = $wpdb;
    $s_DbTableName = $o_wpdb->prefix . "vehicle_options";

    // Get Chrome Key Info.
    $a_ChromeKeys = get_option('ChromeKeys');

    // SET COUNTRY TO 'US', NOT CANADA
    $a_ChromeKeys['country'] = 'US';

    // Open Options File.
    $o_OptionFeed = fopen( plugin_dir_path( __DIR__ )."UploadedFromDealer/$s_fileName", "r" );

    // Check to make sure the feed file was opened before we continue.
    if( $o_OptionFeed === false ) {
        echo "Option Feed Cannot Be Opened<br><br>Options Not Updated.";
        die();
    }

    // Get all the current new vehicles in the database that are active, the stock numbers will be stored in the post_title field.
    $a_CurrentNewVehicles =
    new WP_Query(
            array(
                    'post_type' 	=> 'newvehicles',
                    'post_status' 	=> 'publish',
                    'posts_per_page' 	=> '-1',
                    'meta_query' 	=> array(
                            'relation' 	=> 'AND',
                            array(
                                    'key'     => 'IsVirtualInventory',
                                    'value'   => 'True',
                                    'compare' => '!='
                            )
                    )
            )
    );

    foreach ( $a_CurrentNewVehicles->posts as $a_SinglePost ) {

        $a_NewVehiclePostMeta = get_post_meta( $a_SinglePost->ID );
        $a_NewVehiclesByVin[ $a_NewVehiclePostMeta['VIN'][0] ] = array(
            
            'postID'        =>  intval( $a_SinglePost->ID ),
            'Trim'          =>  $a_NewVehiclePostMeta['Trim'][0],
            'ModelNumber'   =>  $a_NewVehiclePostMeta['ModelNumber'][0],
            'Model'   		=>  $a_NewVehiclePostMeta['Model'][0],
            'Year'   		=>  $a_NewVehiclePostMeta['Year'][0],

        );
    }

    // $a_allOptionCodesInstalledFromCSV - is an array that will hold every unique vehicle option code from the CSV file
    $a_allOptionCodesInstalledFromCSV = array();



// ******************************************************** BEGIN PROCESS OPTION FEED ********************************************************
//  - If VIN from feed matches a VIN in current new inventory - Adds data from feed to $a_NewVehiclesByVin data by VIN to create $a_FinalListForChrome - which is what is fed to chrome
    $b_IsFirstRow = true;
    // Loop through option feed and grab the option codes and VIN listed for each vehicle. We will use the VIN to match with the entries from the $a_NewVehiclesByVin array later.
    while ( ( $a_CSV = fgetcsv( $o_OptionFeed,  "," ) ) !== FALSE) {

        // Skip the first row.
        if ( $b_IsFirstRow === true ) {
            $b_IsFirstRow = false;
            continue;
        }

        // Where we will store vehicle info from their uploaded SET feed.
        unset( $a_VehicleOptionInformation );
        $a_VehicleOptionInformation = array();

        // Temporary storage for the option codes, before they are added to the array above.
        unset( $a_OptionCodes );
        $a_OptionCodes = array();
        
        // GET VIN from CSV
        $a_VehicleOptionInformation['VIN']  = sanitize_text_field( $a_CSV[0] );

        // NL TEST PROBLEM?
        unset($a_VehicleOptionInformation['OptionCodes']);
        $a_VehicleOptionInformation['OptionCodes']  = array();

        // Loop from the first known Option Code in the feed at $a_CSV[7] until an empty string is recieved, because codes are stored anywhere from index 7 and on, but there are no headers past 7. -__-
        // Will count if a field is empty, and after 3 in a row it will move on. This takes into consideration the chance that there are empty fields between actual option listings.
        // CSV Field Counter
        $i = 7;
        // Empty Field Counter
        $x = 0;

        while ( $x < 3 ) {
            
            if( $a_CSV[$i] != '' ) {
                $a_OptionCodes[] = $a_CSV[$i];
                if (!in_array($a_CSV[$i], $a_allOptionCodesInstalledFromCSV))
                {
                    $a_allOptionCodesInstalledFromCSV[]  = $a_CSV[$i];
                }
                $x = 0;
            } else {
                $x++;
            }
            $i++;
        }

        $a_VehicleOptionInformation['OptionCodes'] = $a_OptionCodes;
        // 1st param : vin from csv, 2nd param : array of all vehicles key:'VIN' values:array(info needed for chrome)
        // Check if a VIN exists in current site inventory, and if so merges our site's vehicle post_meta that was collected, with the option feed info we collected. For sanity later.
        // $a_FinalListForChrome[] - VIN, all option codes , VIN again , trim , model number , Model , year
        if ( array_key_exists( $a_VehicleOptionInformation['VIN'], $a_NewVehiclesByVin ) ) {
            $a_FinalListForChrome[] = array_merge( $a_VehicleOptionInformation, $a_NewVehiclesByVin[ $a_VehicleOptionInformation['VIN'] ] );
        }
    }
    fclose( $o_OptionFeed );
    // TEST - SEE ALL OPTION CODES FROM CSV - pre_var_dump($a_allOptionCodesInstalledFromCSV, 'all option codes');
// ******************************************************** END PROCESS OPTION FEED ********************************************************



// ******************************************************** BEGIN MAIN LOOP ******************************************************** 
    $a_OptionsNotFoundDump = array();
    $a_uniqueCodesNotFound = array();
    // - loop though each vehicle from $a_FinalListForChrome
    foreach( $a_FinalListForChrome as $a_SingleVehicle ) {

        $s_bestTrim = urlencode( $a_SingleVehicle['Trim'] );
        $s_bestModelCode = $a_SingleVehicle['ModelNumber'];

                                                // TEST - see all model codes
                                                // var_dump($s_bestModelCode);

        // Add/Update post_meta for each vehicle with the option codes associated with that VIN.
        $UpdatePostMeta = update_post_meta( $a_SingleVehicle['postID'], 'vehicleOptions', $a_SingleVehicle['OptionCodes'] );
                                                // TEST -- echo '<br>Updated post_meta for '.$a_SingleVehicle['postID'].': '.$UpdatePostMeta.'..<br><br>';

        // Call chrome for the vehicle info.
        $request = '';
        $request = '
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:description7a.services.chrome.com">
        <soapenv:Header/>
        <soapenv:Body>
        <urn:VehicleDescriptionRequest>
            <urn:accountInfo number="'.$a_ChromeKeys['number'].'" secret="'.$a_ChromeKeys['secret'].'" country="'.$a_ChromeKeys['country'].'" language="'.$a_ChromeKeys['language'].'" behalfOf="?"/>
            <urn:vin>' . $a_SingleVehicle['VIN'] . '</urn:vin>';
        if ( strlen($s_bestTrim) > 0 ) {
            $request .= '<urn:trimName>'.$s_bestTrim.'</urn:trimName>';
        }
        if ( $s_bestModelCode != '' ) {
            $request .= '<urn:manufacturerModelCode>'.$s_bestModelCode.'</urn:manufacturerModelCode>';
        }

        //These switches are required to get all that we need from Chrome for these SET options. According to Chrome the order also matters, keep them at the end of the call.
        $request .= 
        '<urn:nonFactoryEquipmentDescription>Y</urn:nonFactoryEquipmentDescription>
         <urn:switch>IncludeDefinitions</urn:switch>
         <urn:switch>ShowExtendedDescriptions</urn:switch>
         <urn:switch>ShowExtendedTechnicalSpecifications</urn:switch>
         <urn:switch>ShowAvailableEquipment</urn:switch>
        <urn:switch>IncludeRegionalVehicles</urn:switch>';
        
        $request .= '
        </urn:VehicleDescriptionRequest>
            </soapenv:Body>
        </soapenv:Envelope>';

        $vehicleInfo = CallSoapDLDImporter($request);

        if ($vehicleInfo !== "") {
            
            if ($vehicleInfo->responseStatus["responseCode"] != "Successful") {
               // TEST - show success message ---  echo "<b>" . $vehicleInfo->responseStatus["responseCode"] . "</b><br>";
            }
        }

        $s_vin = $a_SingleVehicle['VIN'];


    // ******************************************************** BEGIN MAIN SUB-LOOP ********************************************************
        // LOOP THROUGH EACH FactoryOption Returned from CHROME for this vehicle
        // check what is returned against the Option Codes we have from the client, to pull back the info for the specific options they are looking for.
        // loop through each FactoryOption returned from chrome for this vehicle - we will be matching on the 'oemCode' field
        foreach( $vehicleInfo->factoryOption as $a_FactoryOption ) {
            $s_oem = $a_FactoryOption['oemCode'];
            
            
            // METHOD 1 - LOOP through each Installed Option for the specific VIN according to CSV
           // foreach( $a_SingleVehicle['OptionCodes'] as $s_OptionCode ) {
            // METHOD 2 (ALTERNATIVE) - loops through all option codes encountered for all vehicles, rather than just the ones installed on this vehicle(by vin) according to the CSV
             foreach($a_allOptionCodesInstalledFromCSV as $s_OptionCode ) {

                $s_OptionCodeOriginal = $s_OptionCode;
                $b_is4Length = false;
                $b_OEMIs4Length = false;
                $s_OEMCode = $a_FactoryOption['oemCode'];

                // current model year dictates that any option code ending in 'AT' needs to have that removed in order to get a return from chrome for that code.
                //This checks and removes that if present. e.g. code = 'DSAT', will be 'DS' afterward.
                if( strlen($s_OptionCode) == 4) {
                    $b_is4Length = true;
                    $s_OptionCode = substr_replace( $s_OptionCode, '', -2 );
                }
                if( strlen($s_OEMCode) == 4) {
                    $b_OEMIs4Length = true;
                }
                
                // if this (Looping) trimmed option code retrived from this (L) single vehicle matches the 'oemCode' attribute from this (L) factory option retrieved from CHROME
                $s_OEMCode = $a_FactoryOption['oemCode'];

                if( substr($s_OptionCode, 0, 4) == substr($s_OEMCode, 0, 4) || ($b_is4Length  && $b_OEMIs4Length && (substr($s_OEMCode, 0, 2) == $s_OptionCode)) ) {
                    // TEST - SHOW EACH MATCH - echo "Matching code : $s_OptionCode and $s_OEMCode";

                    // CREATE an array of the values for this option we are going to store as db record
                    $a_ChromeOptionList = array(

                        'model_code'		=>	$s_bestModelCode,
                        'option_code'		=>	$s_OptionCode,
                        'full_option_code'  =>  $s_OptionCodeOriginal,
                        'option_name'		=>	(string) $a_FactoryOption->description[0],
                        'description'		=>	(string) str_ireplace( '-inc: ', '', $a_FactoryOption->description[1] ),
                        'msrp'				=>	number_format((float)$a_FactoryOption->price['msrpMax'], 2, '.', ''),
                        'invoice'			=>	number_format((float)$a_FactoryOption->price['invoiceMax'], 2, '.', ''),
                        
                    );

                    // INSERT RECORD INTO DB
                    try {
                            $result = $o_db->insertRow($a_ChromeOptionList);

                            if(!empty($result)){
                                // TEST - echo 'Row Inserted';
                            }
                            else {
                                // TEST - echo 'There was a problem.  Row was not inserted';
                            }
                    unset($a_ChromeOptionList);
                    } catch (Exception $e) {
                        // CATCH - PROBLEM UPDATING DATABASE
                        echo '* EXCEPTION : ',  $e->getMessage(), "\n";
                    }
                } 
            }
        }
    // ******************************************************** END MAIN SUB-LOOP ********************************************************
    }
// ******************************************************** END MAIN LOOP ********************************************************



    // ********* DISPLAY RESULTS **************
    echo "<h2><strong>Vehicle Option Codes Have Been Updated</strong></h2>";
    echo '<a href="/wp-admin/admin.php?page=dld_manage_vehicle_options_import"><input type ="button" value = "BACK TO MAIN PAGE" class="myButton" id="btnBack" style="width:400px;"></input></a>';
}


// function to make soap call for chrome
function CallSoapDLDImporter( $s_XML ) {
	$s_SoapURL ="http://services.chromedata.com/Description/7a?wsdl";
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $s_SoapURL);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $s_XML);
	$header[] = "SOAPAction: ". "";
	$header[] = "MIME-Version: 1.0";
	$header[] = "Content-type: text/xml; charset=utf-8";
	curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
	$result = curl_exec($ch);
	$start = strpos($result, "<S:Body>") + 8;
	$end = strrpos($result, "</S:Body>");
	if (($start <= 0) || ($end <= 0)) {
		echo "Response returned from '$s_SoapURL' doesn't appear to be a SOAP document.";
		return '';
	}
	$result = substr($result, $start, $end - $start);
	$doc = simplexml_load_string($result);
	return $doc;
}

?>