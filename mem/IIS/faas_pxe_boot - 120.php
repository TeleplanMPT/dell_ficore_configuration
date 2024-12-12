<?php
//#########################################################################################
// Script: FaaS_PXE_Boot
//
// Purpose: To build an iPXE boot scipt dynamically
// 
// Revision History:
// Kenneth Fortner    001   March 18, 2013  Initial release
// Kenneth Fortner    002   April 27, 2013  Added code to boot even if GCF is corrupt
// Brendan Mullhare   003   July, 04, 2019  ARB in QSL support
//#########################################################################################



//#########################################################################################
// Set out variable defaults
//#########################################################################################
//http://FaaS_Web.faas.local/faas_pxe_boot.php?mac=${net0/mac}&serial=${serial:uristring}&platform=${platform:uristring}

// Enable error reporting
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

$StartTime = date('h:i:s');
$override = false;
//$override = true;
//$server_name = gethostbyname(gethostname());
$mac = $_GET['mac'];
$serial = $_GET['serial'];
$bios_efi = $_GET['platform'];
$remote = $_SERVER['REMOTE_HOST'];
$server_name = $_SERVER['SERVER_NAME'];
$buildarch = $_GET['arc'];

$barcodes_path = "//172.16.16.120/dellsftw/barcodes";
$tools_path  = "//172.16.16.120/dellsftw/barcodes/Tools";
$sut_log_path = "//172.16.16.120/dellsftw/barcodes/$serial";

$arb=False;
$pxe_boot_path = "";
$boot_image_type = '';
$ipxe_menu_timeout = 0;
$tmp_FICore_version = "";
$tmp_odm_version = "001";
$ipxe_menu_default = "exit";
$bootmgr_path = "Shared/WIN7";
$gcf_path = "$sut_log_path/gcf.xml";
if ($buildarch == "arm64") {
    $wimboot = "wimboot.arm64.efi";
} else {
    $wimboot = "wimboot";
}


//$temp_debug = "$sut_log_path/iPXEDEBUG.log";
$tmp_FICore_default_version = "btoapp64167_03A301";

//#########################################################################################
// Determine the iPXE boot image type
//#########################################################################################
//Default log location 
$iPXE_log_file = "$barcodes_path/IPXELogs/$serial.log";  
$iPXE_log_file_end = "$sut_log_path/ipxe.log";
appendtolog ($iPXE_log_file, "#################################################");
appendtolog ($iPXE_log_file, "#          iPXE Boot Process  $StartTime          #");
appendtolog ($iPXE_log_file, "#################################################");
appendtolog ($iPXE_log_file, "SERVICE TAG: $serial");
appendtolog ($iPXE_log_file, "sut_log_path $sut_log_path");

//#########################################################################################
// Determine the SUT log path boot image type
//#########################################################################################
$sut_log_path = "$barcodes_path/$serial";
$boot_image_type = "FI_CORE";

if (is_dir("$sut_log_path")) {
  $log_folder_exists = true;
  
  if(file_exists("$sut_log_path/arbficore.flg")) {
    $boot_image_type = "FI_CORE";
  } elseif(file_exists("$sut_log_path/odm.flg")) {
    $boot_image_type = "ODM";
  } else {
    $boot_image_type = "FI_CORE";
  }
  
  // Load the GCF data into a string 
  $gcf_path = readGCFData($sut_log_path, $gcf_path);
  appendtolog ($iPXE_log_file, "GCF PATH: $gcf_path");
  
  $SENDID = isUnitARB($gcf_path);

  //$SENDID = "MSS";
  $SIAccount = getSIFromGCF($gcf_path);
  
  //extract the BTOA Version
  appendtolog ($iPXE_log_file, "Extract btoaversion"); 
  $tmp_FICore_version  =  readImageData($gcf_path, $iPXE_log_file);
  if ($tmp_FICore_version ==""){
   $tmp_FICore_version = $tmp_FICore_default_version;
  }
  
  appendtolog ($iPXE_log_file, "SENDID value returned $SENDID");
  appendtolog ($iPXE_log_file, "SIAccount value returned $SIAccount");
  appendtolog ($iPXE_log_file, "BootImage value returned $tmp_FICore_version");
   
   // and ARB Flag <SENDID>MSS</SENDID>
   if ($SENDID == "MSS" )
   {
      //ARB Flag set SI should be  blank
      if ($SIAccount != ""){
        appendtolog ($iPXE_log_file, "SIAccount string is not empty this is not a valid ARB unit FAIL");
        //how do we handle this fail
        $boot_image_type = "";
      } else {
        appendtolog ($iPXE_log_file, "SIAccount string is empty this is a valid ARB unit");
        $boot_image_type = "FI_CORE";
      }
   } else {
    // echo ("GCS UNIT");
    appendtolog ($iPXE_log_file, "We should be a GCS unit");
    $gcf_path = readGCFData($sut_log_path, $gcf_path);
     
    appendtolog ($iPXE_log_file, "GCF PATH: $gcf_path");
    //if( file_exists("$sut_log_path/FaaS_kicker_complete.txt") || file_exists("$sut_log_path/FaaS_kicker_complete.flg")) {
    //  appendtolog ($iPXE_log_file, "Found the FaaS_kicker_completet flag file set the Boot Option : $boot_image_type");
    //  $boot_image_type = "FI_CORE";
    //} //else {
      // echo "no completion flag";
    //  appendtolog ($iPXE_log_file, "No Completion flag set, boot the kicker");
    //  $tmp_faas_kicker_version = get_kicker_version($iPXE_log_file, $gcf_path);
    //  $boot_image_type = "Kicker";
    //}  
  }  
} else {
    $log_folder_exists = false;
    appendtolog ($iPXE_log_file, "$barcodes_path/$serial does not exist");
    appendtolog ($iPXE_log_file, "Check for $barcodes_path/$serial.zip");
    if (file_exists("$barcodes_path/$serial/$serial.zip")){
      $boot_image_type = "FI_CORE";
      // Going to unzip the file to temp folder and read the BTOA image, remove the folder and let the FICore process take care of the rest
      appendtolog ($iPXE_log_file, "Found the zip file $serial.zip: ");
      appendtolog ($iPXE_log_file, "Create the folder $barcodes_path/IPXELogs/$serial: ");

      if ( extractZip ("$barcodes_path/$serial/$serial.zip", "$barcodes_path/IPXELogs/tempZip/$serial") ) {
           appendtolog ($iPXE_log_file, "Unzip successful");
      } else {
          appendtolog ($iPXE_log_file, "Unzip failed");
      }
      //extract the BTOA Version
      $gcf_TempPath = "";
      $sut_log_path = "$barcodes_path/IPXELogs/tempZip/$serial";
      $handle = opendir($sut_log_path);
      while (($file = readdir($handle)) !== false) {
        $gcf_search_return = "";
        if (preg_match('/(.*gcf\.xml)$/i', $file, $gcf_search_return)) {
            $tmp_gcf_name = $gcf_search_return[1];
            $gcf_TempPath = "$sut_log_path/$tmp_gcf_name";
            appendtolog ($iPXE_log_file, "Unzipped GCF PATH: $gcf_path");
        }
      }
      closedir($handle);
      
      if ($gcf_TempPath == "") {
          appendtolog($iPXE_log_file, "Failed to find GCF file in zip");
      }
	  appendtolog ($iPXE_log_file, "gcf_TempPath $gcf_TempPath");
      $gcf_path = readGCFData($sut_log_path, $gcf_path);
      $tmp_FICore_version  =  readImageData($gcf_TempPath, $iPXE_log_file);

  }
}

//#########################################################################################
// Set the iPXE boot options for the script below
//#########################################################################################
  if ($tmp_FICore_version == '') {
    appendtolog ($iPXE_log_file, "tmp_FICore_version is empty get the latest BTOA version");
    $tmp_FICore_version = getLatestBTOAImage();
} 
    
appendtolog ($iPXE_log_file, "Booting  $boot_image_type  -- $tmp_FICore_version ");
if ($boot_image_type == "FI_CORE") {
   $pxe_boot_path = "FICore/$tmp_FICore_version";
    appendtolog ($iPXE_log_file, "pxe_boot_path FICORE-- $pxe_boot_path");
    $ipxe_menu_timeout = 3000;
    $ipxe_menu_default = "process_controlled";
}   elseif ($boot_image_type == "") {
  $ipxe_menu_timeout = 0;
  $ipxe_menu_default = "exit";
} 
 

//#######################################################################################
// Extract a zip file to a folder
//#######################################################################################  
function extractZip($zipPath, $outputPath) {
    $zip = new ZipArchive;
    $res = $zip->open($zipPath);
    if ($res === TRUE) {
      $zip->extractTo($outputPath);
      $zip->close();
      return (True);
    } else {
      return (False);
    }
}

//#######################################################################################
// Delete a Folder and all sublevel files 
//#######################################################################################  
 function deleteDir($dirPath) {
    if (! is_dir($dirPath)) {
        throw new InvalidArgumentException("$dirPath must be a directory");
    }
    if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
        $dirPath .= '/';
    }
    $files = glob($dirPath . '*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            $this->deleteDir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dirPath);
}

//#######################################################################################
// Read the GCF into a string and return
//#######################################################################################
function readGCFData($sut_log_path, $gcf_path)
{
      if(false == file_exists($gcf_path)) {
        // find the GCFs in the SVCTAG folder
        $handle = opendir($sut_log_path);
        while (($file = readdir($handle)) !== false) {
          if (preg_match('/(.*gcf\.xml)/i', $file, $gcf_search_return)) {
            $tmp_gcf_name = $gcf_search_return[1];
            $gcf_path = "$sut_log_path/$tmp_gcf_name";
          }
        }
    
        closedir($handle);
    }
    return ($gcf_path);
}


//########################################################################################
// Extract Ficore image name
//#######################################################################################
function readImageData($gcf_TempPath, $iPXE_log_file){

  $tmp_FICore_version = "";
  $xmlContent = file_get_contents($gcf_TempPath);
  appendtolog($iPXE_log_file, "Extracting wim file name path from gcf");

  if ($xmlContent !== false) {
      $dom = new DOMDocument;
      libxml_use_internal_errors(true); // Suppress XML errors for handling

      if ($dom->loadXML($xmlContent)) {
          appendtolog($iPXE_log_file, "XML is valid");
          $good_gcf_found = true;

          // Convert DOMDocument to SimpleXML for easier parsing
          $gcf_xml = simplexml_import_dom($dom);
          $FICore_Imagename = $gcf_xml->DATACONTAINERS->CONTAINER[0]->attributes()->name;

          foreach ($gcf_xml->DATACONTAINERS->CONTAINER as $item) {
              if ($item->attributes()->name == "imagedata") {
                  $tmp_FICore_version = $item->IMAGE->attributes()->imageName;
                  preg_match('/(.+)\.img/', $tmp_FICore_version, $arr);
                  if ($arr) {
                      $tmp_FICore_version = $arr[1];
                  }
              }
          }
          if ($tmp_FICore_version) {
            appendtolog($iPXE_log_file, "Wim Path Retrieved: $tmp_FICore_version");
          }
      } else {
          appendtolog($iPXE_log_file, "XML is not valid or failed to load.");
      }

      // Clear any errors after parsing
      libxml_clear_errors();
      libxml_use_internal_errors(false);
  } else {
      appendtolog($iPXE_log_file, "Failed to read XML file content.");
  }

  appendtolog($iPXE_log_file, "End extraction");
  return $tmp_FICore_version;
}

//########################################################################################
// Read the GCF and retrun the BootImage string
//#######################################################################################
function readImageDataOldWay($gcf_TempPath, $iPXE_log_file){

      $tmp_FICore_version = "";
      @$valid_xml_bool = simplexml_load_string(file_get_contents($gcf_TempPath));
      appendtolog ($iPXE_log_file, "Boot option menu Faas_Kicker statement:"); 
      if ($valid_xml_bool == true) {
        appendtolog ($iPXE_log_file, "xml is valid"); 
        $good_gcf_found = true;
      
        $gcf_xml = simplexml_load_file($gcf_TempPath);
        $FICore_Imagename =  $gcf_xml->DATACONTAINERS->CONTAINER[0]->attributes()->name;
        foreach($gcf_xml->DATACONTAINERS->CONTAINER as $item) {
          if ($item->attributes()->name == "imagedata") {
            $tmp_FICore_version = $item->IMAGE->attributes()->imageName;
            preg_match('/(.+)\.img/', $tmp_FICore_version, $arr);
            if ($arr) {
                $tmp_FICore_version = $arr[1];
            }
          }
        }
      }
      appendtolog ($iPXE_log_file, "end extraction"); 
      return $tmp_FICore_version ;
      
}

//#########################################################################################
// Function to call create the serviceTag directory if required
//#########################################################################################
function InitializeDirectory($Directory)
{
    if (is_dir("$Directory"))
        return(0);
    if (!mkdir("$Directory", 0777, true))
        return(1);
    return(0);
}

//#########################################################################################
// loog for SENDID and return value
//#########################################################################################
function isUnitARB($gcf_path)
{
    $gcf_xml = simplexml_load_file($gcf_path);
    $SENDID=  $gcf_xml->SENDID ;
    return $SENDID;
}

//#########################################################################################
// loog for SIAccount and return value
//#########################################################################################
function getSIFromGCF($gcf_path)
{
    $gcf_xml = simplexml_load_file($gcf_path);
    foreach($gcf_xml->DATACONTAINERS->CONTAINER  as $item) {
      for ($a = 0; $a<2000; $a++) {
        $xmlString = ($item->HEADERATTRIBUTES->ATTRIBUTE[$a]);
        if ($xmlString['name'] == 'siaccount'){
            //echo $xmlString['value'];
          $siaccount = $xmlString['value'];        
          }
        }
      }
  
  return $siaccount;
  
}

//#########################################################################################
// Get the Model from the GCF
//#########################################################################################
function getModelFromSI($gcf_path)
{
  // echo "get model";
    $gcf_xml = simplexml_load_file($gcf_path);
    foreach($gcf_xml->DATACONTAINERS->CONTAINER as $item) {
    for ($a = 0; $a<2000; $a++) {
      $xmlString = ($item->HEADERATTRIBUTES->ATTRIBUTE[$a]);
      if ($xmlString['name'] == 'model'){
        //echo $xmlString['value'];
        $tmp_model = $xmlString['value'];        
      }
    }
  }
  // echo $tmp_model;
  return $tmp_model;
  
}

//#########################################################################################
// Get the Kicker Version 
//#########################################################################################
function get_kicker_version($iPXE_log_file, $gcf_path){

  appendtolog ($iPXE_log_file, "Boot option menu Faas_Kicker statement:"); 
  $tmp_model = getModelFromSI($gcf_path);

  appendtolog ($iPXE_log_file, "Look for the Kicker image based on the Model $tmp_model"); 
  $tmp_faas_kicker_version = ReadDefaultIni($tmp_model);

  // Select the default if all else fails
  if ($tmp_faas_kicker_version == "") {
    appendtolog ($iPXE_log_file, "Look for the default Kicker image"); 
    $tmp_faas_kicker_version = ReadDefaultIni("tmp_default_faas_kicker_version");
  }

  $tmp_faas_kicker_version = trim($tmp_faas_kicker_version);
  //$pxe_boot_path = "FaaS_Kicker_Image/$tmp_faas_kicker_version";
 // $ipxe_menu_timeout = 3000;
  //$ipxe_menu_default = "process_controlled";

  return $tmp_faas_kicker_version;
}

//#########################################################################################
// Read in the GCF and return 
//#########################################################################################
function ReadGCF($SearchString)
{
  $returnVar = "";
  $handle = fopen("FaasBootDefaults.ini", "r");
  if ($handle) {
    while (($line = fgets($handle)) !== false) {
      //echo $line;
      $pieces = explode(":", $line);
      if ($SearchString == $pieces[0]){
        $pieces = explode(":", $line);
        $returnVar = $pieces[1];
    }
   }
 
   fclose($handle);
   return($returnVar);
  }
}

//####################################
// Write the string to the log file 
//####################################
function appendtolog($iPXE_log_file, $string) {
  $iPXE_log_file = fopen($iPXE_log_file, 'a') or die("can't open file");
  if (preg_match('/#/', $string)){
    fwrite ($iPXE_log_file, $string . "\r\n");
  }
  else{
    fwrite ($iPXE_log_file, "Time: [" . date('h:i:s') . ']  ' . $string . "\r\n");
  }
}

//#########################################################################################
// Function to read the image location and pull the newest BTOA image 
//#########################################################################################
function getLatestBTOAImage()
{
    $path='BOOT\\img\\wimboot';
    $working_dir = getcwd();
    $path = getcwd() . "\\boot\\img\\wimboot";
    chdir($path); ## chdir to requested dir

    $ret_val = false;
    if ($p = opendir($path) ) {
    while (false !== ($file = readdir($p))) {
      //echo $file;
    if ($file{0} != '.' && is_dir($file)) {
      $list[] = date('YmdHis', filemtime($path.'/'.$file)).$path.'/'.$file;
      }
    }

    rsort($list);
    $ret_val = $list[0];
    $mystring = explode('/', $ret_val);
    $ret_val = $mystring[1];
    }
    
  return $ret_val;
  
}

//#########################################################################################
// Function to read default ini for Fi-core/kicker versions
//#########################################################################################
function ReadDefaultIni($SearchString)
{
  $returnVar = "";
  $handle = fopen("FaasBootDefaults.ini", "r");
  if ($handle) {
    while (($line = fgets($handle)) !== false) {
      //echo $line;
      $pieces = explode(":", $line);
      if ($SearchString == $pieces[0]){
        $pieces = explode(":", $line);
        $returnVar = $pieces[1];
    }
   }
 
   fclose($handle);
   return($returnVar);
  }
}

function write_debug($toScreen, $toFile) {
  //#########################################################################################
  // Echo debug code
  //#########################################################################################
  // echo "00000000";
  global $debug_variables;

  $sut_log_path = $debug_variables['sut_log_path']; 
  $boot_image_type = $debug_variables['boot_image_type'];
  $iPXE_log_file = $debug_variables['iPXE_log_file']; 

  $iPXE_log_file_end = $debug_variables['sut_log_path'];

  if(file_exists("$sut_log_path\ipxe_debug.flg")) {
    if ($toFile and array_key_exists("log_folder_exists", $debug_variables)) {
      $iPXE_log_file = fopen("$iPXE_log_file", 'a') or die("can't open file");
      
      fwrite ($iPXE_log_file, "#################################################\r\n");
      fwrite ($iPXE_log_file, "#          Variable Dump        #\r\n");
      fwrite ($iPXE_log_file, "#################################################\r\n");
      fwrite ($iPXE_log_file, "Time: " . date('h:i:s') . "\r\n");
    }
    else 
    {
      $toFile = false; 
    }
  
    foreach ($debug_variables as $key => $value)
    {
      if ($key != 'debug_variables' and $key != 'doc' and $key != 'valid_xml_bool') {
        if ($toScreen) {
          echo "<br />" . $key . ": " . print_r($value, true); }
      if ($toFile) {
        fwrite ($iPXE_log_file, $key . ": " . print_r($value, true) . "\r\n"); }
    }
  }
  
  if ($toFile) {
    fclose($iPXE_log_file); }
  }  
  echo $iPXE_log_file_end;
  $iPXE_log_file_end = $debug_variables['iPXE_log_file_end'];
  if (!copy($iPXE_log_file, $iPXE_log_file_end)) {
    echo "failed to copy $iPXE_log_file to $iPXE_log_file_end...\n";
  }
    else{
        echo "copy $iPXE_log_file to $iPXE_log_file_end...\n";
        
    }
}




//#########################################################################################
// iPXE Script being sent to the SUT
//#########################################################################################
  ?>
#!ipxe

###################### MAIN MENU ####################################
menu CFI PXE Menu
item odm                       Boot ODM Image
item ficore                    Boot FICore
item process_controlled        Boot to what the iPXE process discovered
item shell                     Enter iPXE shell
item exit                      Exit to BIOS
item reboot                    Reboot computer  
choose --timeout <?php echo $ipxe_menu_timeout; ?> --default <?php echo "$ipxe_menu_default"; ?> selected && goto ${selected} || goto cancel



:cancel
echo You cancelled the menu, dropping you to a shell

:shell
echo Type exit to get the back to the menu
shell
set menu-timeout 0
set submenu-timeout 0
goto start

:failed
echo Booting failed, dropping to shell
goto shell

:reboot
reboot

:exit
exit


:odm
set base-url http://<?php echo $server_name; ?>/boot/img 
kernel ${base-url}/<?php echo $wimboot; ?> 
initrd ${base-url}/ODM/<?php echo $tmp_odm_version; ?>/bootmgr                     bootmgr
initrd ${base-url}/ODM/<?php echo $tmp_odm_version; ?>/boot/bcd                    BCD
initrd ${base-url}/ODM/<?php echo $tmp_odm_version; ?>/boot/boot.sdi               boot.sdi
initrd ${base-url}/ODM/<?php echo $tmp_odm_version; ?>/sources/boot.wim            boot.wim 
boot || goto failed
goto start


:ficore
set base-url http://<?php echo $server_name; ?>/boot/img 
kernel ${base-url}/<?php echo $wimboot; ?> 
initrd ${base-url}/<?php echo $tmp_FICore_version; ?>/bootmgr                     bootmgr
initrd ${base-url}/<?php echo $tmp_FICore_version; ?>/boot/bcd                    BCD
initrd ${base-url}/<?php echo $tmp_FICore_version; ?>/boot/boot.sdi               boot.sdi
initrd ${base-url}/<?php echo $tmp_FICore_version; ?>/sources/boot.wim            boot.wim 
boot || goto failed
goto start


:process_controlled
set base-url http://<?php echo $server_name; ?>/boot/img 
kernel ${base-url}/<?php echo $wimboot; ?> 
initrd ${base-url}/<?php echo $pxe_boot_path; ?>/bootmgr                     bootmgr
initrd ${base-url}/<?php echo $pxe_boot_path; ?>/boot/bcd                    BCD
initrd ${base-url}/<?php echo $pxe_boot_path; ?>/boot/boot.sdi               boot.sdi
initrd ${base-url}/<?php echo $pxe_boot_path; ?>/sources/boot.wim            boot.wim 
boot || goto failed
goto start

  <?php
  
?>



  

