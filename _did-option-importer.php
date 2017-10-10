<?php
/**
 * Plugin Name: DLD Option Importer
 * Description: Dealer uploads CSV of option codes per vehicle by VIN through admin of site. Upon successful upload, script is run to pull data for each option from chrome and update database dld_vehicle_options
 * Version: 1.0
 * Author: Tom Molinaro, Gaetano Dragone
 *
 * NOTE UPON DEPLOYMENT - table will have to be created in DB
 * SQL :
 * CREATE TABLE toyotabo_db.dld_vehicle_options(
 * `ID` INT( 8 ) NOT NULL AUTO_INCREMENT ,
 * `option_code` VARCHAR( 12 ) DEFAULT NULL ,
 * `full_option_code` VARCHAR( 12 ) DEFAULT NULL ,
 * `option_name` VARCHAR( 55 ) DEFAULT NULL ,
 * `description` TEXT,
 * `msrp` VARCHAR( 12 ) DEFAULT NULL ,
 * `invoice` VARCHAR( 12 ) DEFAULT NULL ,
 * PRIMARY KEY (  `ID` )
 * ) ENGINE = MYISAM AUTO_INCREMENT =127 DEFAULT CHARSET = latin1
 *
 */



 require 'plugin_update_check.php';
 $MyUpdateChecker = new PluginUpdateChecker_2_0 (
    'https://kernl.us/api/v1/updates/59dd2721b765ab6a6acc71b0/',
    __FILE__,
    '_dld_option_importer',
    1
 );
 // $MyUpdateChecker->purchaseCode = "somePurchaseCode";  <---- optional!
 // $MyUpdateChecker->remoteGetTimeout = 5; <--- optional


include_once plugin_dir_path( __FILE__ ).'classes/Database.class.php';
include_once plugin_dir_path( __FILE__ ).'include/dld-option-import.php';


// CREATE 2 necessary pages - main page and page for postback (loading CSV)
function dld_option_importer_admin_menu() {

        add_menu_page (
        'Vehicle Option Importer Page',					// string $page_title
        'Vehicle Option Importer',					// string $menu_title
        'read',							// string $capability
        '_dld_option_importer',		// string $menu_slug
        'dld_option_importer_init',		// callback $function
        'dashicons-admin-page',			// string $icon_url
        '92'							// int $position
        );

        add_menu_page (
            'Vehicle Option Importer Page2',					// string $page_title
            null ,					// string $menu_title
            'read',							// string $capability
            '_dld_option_importer2',		// string $menu_slug
            'process_vehicle_options_from_csv_upload_and_update'		// callback $function ************** PROD --- REMOVE '_test'
            );
} add_action( 'admin_menu', 'dld_option_importer_admin_menu' );



// GET STYLES AND SCRIPT
function dld_option_importer_admin_enqueue_scripts($s_PageTitle) {

    if (  preg_match( '/_dld_option_importer/i', $s_PageTitle ) ) {
        wp_register_style( 'prefix-style', plugins_url('/styles/styles.css', __FILE__) );
        wp_enqueue_style( 'prefix-style', plugins_url('/styles/styles.css', __FILE__) );
    
        wp_enqueue_script('jquery');
       
        wp_register_script( 'custom-js', plugins_url('/scripts/scripts.js', __FILE__));
        wp_enqueue_script( 'custom-js', plugins_url('/scripts/scripts.js', __FILE__));
    
	} else {
		return;
    }
    
} add_action( 'admin_enqueue_scripts', 'dld_option_importer_admin_enqueue_scripts' );


// FUNCTION - sets up main admin page
function dld_option_importer_init(){
    $path = get_site_url();
    ?>

    <h1>Vehicle Option Importer</h1>
    <hr>
    </br>

    <form action="admin.php?page=_dld_option_importer2" method="post" enctype="multipart/form-data">
        <table>
            <tr><td colspan="2" style="font-size:1.4em;">
                <strong>Please select CSV file to upload:</br></br>
            </td></tr>
            <tr><td></td><td>
                <strong><input type="file" name="fileToUpload" id="fileToUpload"  data-validation="file_check"></strong>
            </td></tr>
            <tr><td></td><td>
                <input type="submit" value="Upload CSV" name="submit"  id="btnUploadFile" class="myButton" style="width:400px"  ></strong>
            </td></tr>
            <tr><td colspan="2">
            <div class="errorDiv" style="color:red;font-weight:bold;"></div>
        </td></tr>
        </table>
    </form>
    </br>

    <h2 id="h2PleaseWait" style="display:none;"><strong>This may take a few minutes.  Please do not leave or refresh this page while process is completing!</h2></strong>
    <hr>

    
</br>

<form action="admin.php?page=_dld_option_importer" method="post" enctype="multipart/form-data">
    <table>
        <tr><td colspan="2"  style="font-size:1.4em;">
            <strong>Add new blacklisted option name:</strong></br></br>
        </td></tr>
        <tr><td></td><td>
            <strong><input type="text" name="txtOptionCodeToAdd" id="txtOptionCodeToAdd" style="width:400px;"></strong>
        </td></tr>
        <tr><td></td><td>
            <input type="submit" value="Save New Option Name" name="submit" style="width:400px;" class="myButton" id="btnSaveNewOption"></strong></input>
        </td></tr>

    </table>
</form>
</br>
<hr>
    </br>
    

    <?php
    $s_blacklist = '';
    $a_blacklistArray = array();
    $a_blacklistGoodArray = array();
    if(get_option('commmaSeparatedBlacklistedOptionNames') !== false){
        $s_blacklist = get_option('commmaSeparatedBlacklistedOptionNames');
        $a_blacklistArray = explode(',', $s_blacklist);
        // REMOVE ANY EMPTY STRINGS FROM ARRAY
        foreach($a_blacklistArray as $s_singleItem){
            if(strlen($s_singleItem) > 1){
                $a_blacklistGoodArray[] = $s_singleItem;
            }
        }
    }

    // SHOW OPTIONS
    echo "
    <table>
        <tr><td colspan='2' style='font-size:1.4em;'>
            <strong>Blacklisted options:</strong>
        </td></tr>
        <tr><td colspan='2' style='font-size:1em;'>
            <em><strong>&nbsp;&nbsp;&nbsp;* Options that contain the following words or sequence of words within their names will be omitted: </em></strong>
        </td></tr>
        ";

    $count = 0;
    foreach($a_blacklistGoodArray as $s_item){
        echo "
        <form action='admin.php?page=_dld_option_importer' method='post' enctype='multipart/form-data'>
            <tr><td>
                <input id='blacklist$s_item' name ='OptionCodeToRemove' value='$s_item' readonly='readonly' style='width:400px;'></input>
            </td>
            <td>
                <input type='submit' id='btn$s_item' class='myRedButton'  value='Delete'></input>
            </td></tr>
        </form>
        ";
        $count++;
    }


    

    unset($a_blacklistArray);
    unset($a_blacklistGoodArray);
    $a_blacklistArray = array();
    $a_blacklistGoodArray = array();


    // GET new posted blacklist item
    $s_newBlackListItem = htmlspecialchars($_POST["txtOptionCodeToAdd"]);

    // REMOVE all non-space and non-word characters from new blacklisted item and convert to all lowercase
    $s_newBlackListItem = preg_replace('/[^ \w]+/', '', $s_newBlackListItem);
    $s_newBlackListItem = strtolower($s_newBlackListItem);

    // GET posted blacklist item to remove
    $s_blackListItemToRemove = htmlspecialchars($_POST["OptionCodeToRemove"]);

    // IF there are already stored values in blacklist
    if(get_option('commmaSeparatedBlacklistedOptionNames') !== false){
        // GET comma separated string of all blacklist values from OPTION VALUE
        $s_blacklist = get_option('commmaSeparatedBlacklistedOptionNames');
        // CONVERT TO ARRAY
        $a_blacklistArray = explode(',', $s_blacklist);


        // REMOVE ANY EMPTY STRINGS FROM ARRAY
        foreach($a_blacklistArray as $s_singleItem){
            if(strlen($s_singleItem) > 1){
                $a_blacklistGoodArray[] = $s_singleItem;
            }
        }
        if (!in_array($s_newBlackListItem, $a_blacklistGoodArray) && strlen($s_newBlackListItem) > 0)
        {
            // Add new blacklist item to blacklist array
            $a_blacklistGoodArray[] = $s_newBlackListItem;
            $s_item = htmlspecialchars($_POST["txtOptionCodeToAdd"]);
            echo "
                <form action='admin.php?page=_dld_option_importer' method='post' enctype='multipart/form-data'>
                <tr><td>
                <input id='blacklist$s_item' name ='OptionCodeToRemove' value='$s_item' readonly='readonly' style='width:400px;'></input>
                </td>
                <td>
                    <input type='submit' id='btn$s_item' class='myRedButton'  value='Delete'></input>
                </td></tr>
                </form>
                ";
        }
    }
    // ELSEIF - No stored values in blacklist, and posted blacklisted item is 1 char
    elseif (strlen($s_newBlackListItem) > 0){
        $a_blacklistGoodArray[] = $s_newBlackListItem;
        // echo '<strong><big>'.htmlspecialchars($_POST["txtOptionCodeToAdd"]) .'</big></strong> </br> The above has been added to blacklisted options.';
        $s_item = htmlspecialchars($_POST["txtOptionCodeToAdd"]);
        echo "
        <form action='admin.php?page=_dld_option_importer' method='post' enctype='multipart/form-data'>
            <tr><td>
            <input id='blacklist$s_item' name ='OptionCodeToRemove' value='$s_item' readonly='readonly' style='width:400px;'></input>
            </td>
            <td>
                <input type='submit' id='btn$s_item' class='myRedButton'  value='Delete'></input>
            </td></tr>
         </form>
            ";

    }
    echo "</table>";

    // IF there is a posted option name to delete
    if(strlen($s_blackListItemToRemove) > 0){
        foreach (array_keys($a_blacklistGoodArray, $s_blackListItemToRemove, true) as $key) {
            unset($a_blacklistGoodArray[$key]);
            echo '<h2 style="color:black;"><strong>"'.$s_blackListItemToRemove.'" has been deleted</h2>';
            echo '<input type ="button" value = "OK" class="myButton" id="btnReloadPage" style="width:400px;background-color:red;"></input>';
        }
    }

    $s_blacklist = implode(',', $a_blacklistGoodArray);
    update_option( 'commmaSeparatedBlacklistedOptionNames', $s_blacklist, $autoload = false );

}

    // TEST - SHOW FULL BLACKLIST
    // echo '</br></br>FULL BLACKLIST : </br>'.$s_blacklist;
    // echo'</br></br></br>(delete this)</br>
    // These items should be blacklisted :</br>
    // 50 state emissions</br>
    // gallons of gas</br>
    // federal emissions</br>
    // southeast toyota distributor</br>';
    // }
    
?>