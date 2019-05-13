<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);

/* ###########################################################################
* helper functions
* ###########################################################################*/

function mergeArray(array $a, array $b, $preserveNumericKeys = false)
{
    foreach ($b as $key => $value) {
        if (isset($a[$key]) || array_key_exists($key, $a)) {
            if (!$preserveNumericKeys && is_int($key)) {
                $a[] = $value;
            } elseif (is_array($value) && is_array($a[$key])) {
                $a[$key] = mergeArray($a[$key], $value, $preserveNumericKeys);
            } else {
                $a[$key] = $value;
            }
        } else {
           if (!$preserveNumericKeys && is_int($key)) {
               $a[] = $value;
           } else {
                $a[$key] = $value;
           }
        }
    }
    return $a;
}

function rrmdir($src) {
    $dir = opendir($src);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            $full = $src . '/' . $file;
            if ( is_dir($full) ) {
                rrmdir($full);
            }
            else {
                unlink($full);
            }
        }
    }
    closedir($dir);
    rmdir($src);
}

/* ###########################################################################
* load configuration
* ###########################################################################*/
$config = require __dir__."/config.php";
if(file_exists(__dir__."/config.local.php")){
   $config = \src\domain\util::mergeArray($config, require __dir__."/config.local.php");
}

/* ###########################################################################
* authentification
* ###########################################################################*/

$username = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : "";
$password = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_PW'] : "";

if(!$username || empty($config["user"][$username]) || $config["user"][$username]["password"] !== $password){
  header('WWW-Authenticate: Basic realm="Data Store"');
  header('HTTP/1.0 401 Unauthorized');
  die ("Not authorized");
}

/* ###########################################################################
* read request
* ###########################################################################*/

$request_method = $_SERVER["REQUEST_METHOD"];
$uri = trim(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH), '/');
$root_path = trim(preg_replace('!\/index\.php$!i', '', $_SERVER['SCRIPT_NAME']),'/');
$rel_path = trim(substr($uri, strlen($root_path)), '/');
$extension = pathinfo($rel_path, PATHINFO_EXTENSION);

/* ###########################################################################
* json handling
* ###########################################################################*/
header('Content-Type: application/json');

/*
* POST: create resource (resource may not exist)
* PUT: create or override complete resources
* PATCH: update partial (resource must exists)
*/
if(in_array($request_method, ["POST","PUT","PATCH"])){
  /*create Resource*/
  $resource_data = json_decode(file_get_contents('php://input'),true);
  $resource_file = "{$config['data_dir']}/{$rel_path}";
  $resource_dir = dirname($resource_file);
  if($extension != "json"){
    header('HTTP/1.0 400 	Bad Request');
    die ("\"json file path expected\"");
  }


  if($request_method == "POST" && file_exists($resource_file)){
    header('HTTP/1.0 409 Conflict');
    die ("\"resource already exists\"");
  }
  if($request_method == "PATCH"){
    if(!file_exists($resource_file) || is_dir($resource_file)){
      header('HTTP/1.0 404 Not Found');
      die ("\"resource or directory not found\"");
    }
    $resource_data_original = json_decode(file_get_contents($resource_file),true);
    $resource_data = mergeArray($resource_data, $resource_data_original);
  }

  if(!is_dir($resource_dir) && !mkdir($resource_dir)){
    header('HTTP/1.0 500 Internal Server Error');
    die ("\"can not create directory\"");
  }
  file_put_contents($resource_file, json_encode($resource_data));
  echo json_encode(["success" => true]);
}
elseif($request_method == "DELETE"){
  /*delete resource*/
  $resource_file = "{$config['data_dir']}/{$rel_path}.json";
  $resource_dir = "{$config['data_dir']}/{$rel_path}";
  if(file_exists($resource_file) && !is_dir($resource_file)){
    unlink($resource_file);
  }
  if(!empty($rel_path) && is_dir($resource_dir)){
    rrmdir($resource_dir);
  } else {
    header('HTTP/1.0 404 Not Found');
    die ("\"resource not found\"");
  }

}
else{
  /* get resource */
  $resource_path = "{$config['data_dir']}/{$rel_path}";

  if($extension == "json" && file_exists($resource_path) && !is_dir($resource_path)){
      echo file_get_contents($resource_path);
  }
  elseif($extension !== "json" && file_exists($resource_path) && is_dir($resource_path)){
      $resource_ids = [];
      $dirs = [];
      $filters = (array)isset($_REQUEST["filter"]) ? $_REQUEST["filter"] : [];

      if ($handle = opendir($resource_path)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry == "." || $entry == "..") {
              continue;
            }

            if (is_dir("{$config['data_dir']}/{$rel_path}/$entry")){
              $dirs[] = $entry;
            }
            elseif(pathinfo($entry, PATHINFO_EXTENSION) == "json"){
              $insert = true;

              if(!empty($filters)){
                $entity = json_decode(file_get_contents("{$config['data_dir']}/{$rel_path}/$entry"), true);
                foreach ($filters as $filter) {
                  if(!isset($entity[$filter["field"]])){
                    $insert = false;
                    break;
                  }
                  elseif($filter["op"] == "equal" && $entity[$filter["field"]] !== $filter["value"]){
                      $insert = false;
                      break;
                    } elseif($filter["op"] == "contains"){
                      if(is_array($entity[$filter["field"]]) && !in_array($filter["value"], $entity[$filter["field"]])){
                        $insert = false;
                        break;
                      }
                      elseif(is_string($entity[$filter["field"]]) && strpos($entity[$filter["field"]], $filter["value"]) === false){
                        $insert = false;
                        break;
                      }
                    }
                }
              }

              if($insert){
                $resource_ids[] = $entry;
              }

            }
        }
        closedir($handle);
     }

     echo json_encode([
       "resources" => $resource_ids,
       "directories" => $dirs,
       "filter" => $filters
     ]);
  }
  else {
    header('HTTP/1.0 404 Not Found');
    die ("\"resource or directory not found\"");
  }
}
