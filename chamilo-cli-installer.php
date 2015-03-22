<?php 
/**
 * This script allows installing Chamilo from the command line, using a list of 
 * parameters (launch the command alone to see a list of parameters).
 * This script uses the classical web-based files as a base and prepares the
 * parameters correspndingly
 */
/**
 * Environment initialization - prepare the environment to execute this script
 */
ini_set('register_argc_argv','On');
ini_set('max_execution_time',0);
ini_set('memory_limit','100M');
ini_set('log_errors','On');
ini_set('display_errors','On');
if (PHP_SAPI!='cli') {
	die('This script has to be launched from the command line!');
}

// Setting the error reporting levels.
error_reporting(E_COMPILE_ERROR | E_ERROR | E_CORE_ERROR);

// Overriding the timelimit (for large campusses that have to be migrated).
@set_time_limit(0);

define('SYSTEM_INSTALLATION', 1);
define('INSTALL_TYPE_UPDATE', 'update');
define('FORM_FIELD_DISPLAY_LENGTH', 40);
define('DATABASE_FORM_FIELD_DISPLAY_LENGTH', 25);
define('MAX_FORM_FIELD_LENGTH', 80);

/*		PHP VERSION CHECK */

// PHP version requirement.
define('REQUIRED_PHP_VERSION', '5.4');

if (!function_exists('version_compare') || version_compare( phpversion(), REQUIRED_PHP_VERSION, '<')) {
	$global_error_code = 1;
	// Incorrect PHP version.
	require dirname(__FILE__).'/../inc/global_error_message.inc.php';
	die();
}

session_start();
$install_language = $_SESSION['install_language'] = 'english';

// Some constants
//define('MAX_COURSE_TRANSFER',100);
//define('INSTALL_TYPE_UPDATE', 'update');
//define('FORM_FIELD_DISPLAY_LENGTH', 40);
//define('DATABASE_FORM_FIELD_DISPLAY_LENGTH', 25);
//define('MAX_FORM_FIELD_LENGTH', 80);
//define('DEFAULT_LANGUAGE', 'english');
$session_lifetime=360000;
define('SESSION_LIFETIME',$session_lifetime);
$new_version = '1.10.0';

global $_configuration;
$_configuration['root_sys'] = $pathForm = realpath(dirname(__FILE__).'/../..');
$_configuration['url_append'] = '';
$_configuration['code_append'] = 'main/';
$_configuration['course_folder'] = 'courses/';

/**
 * Inclusion initialization - includes necessary Chamilo libs 
 */
require dirname(__FILE__).'/../inc/lib/main_api.lib.php';
require_once dirname(__FILE__).'/../inc/lib/database.lib.php'; //also defines constants
require_once 'install.lib.php';

// The function api_get_setting() might be called within the installation scripts.
// We need to provide some limited support for it through initialization of the
// global array-type variable $_setting.
$_setting = array(
	'platform_charset' => 'UTF-8',
	'server_type' => 'production', // 'production' | 'test'
	'permissions_for_new_directories' => '0770',
	'permissions_for_new_files' => '0660'
);
// Character set during the installation, it is always to be 'UTF-8'.
$charset = 'UTF-8';
// Initialization of the internationalization library.
api_initialize_internationalization();
// Initialization of the default encoding that will be used by the multibyte string routines in the internationalization library.
api_set_internationalization_default_encoding($charset);

// Page encoding initialization.
header('Content-Type: text/html; charset='. api_get_system_encoding());


/**
 * Check parameters
 */
if ($argc <= 10) {
	echo "\nWARNING: This script will install the Chamilo portal from the\n".
		 "command line. As such, it is considered dangerous and should be\n" .
		 "used with caution, providing the following parameters in order.\n\n" .
		 "USAGE: php5 cli_install.php  -l username -p userpass\n" .
		 "       -U DB_user  -P DB_pass  -u 'http://portal.example.com/'\n" .
		 "       -X db_name [-L portal_language (english|spanish|...)] [-H 'db_host']\n".
                 "       [-t enable_tracking (true|false)] [-r allow_self_registration (true|false)]\n".
		 "       [-q allow_auto_register_teacher(true|false)]\n".
                 "       [-n sys_abs_path_to_chamilo_root]\n".
		 "       [-e encrypt_pass (sha1|md5|none)] [-z 'admin_mail'] [-f 'admin_fname']\n".
		 "       [-g 'admin_lname'] [-b 'admin_phone'] [-c 'campus_name']\n".
		 "       [-y 'My company'] [-w 'http://www.chamilo.org']\n\n";
}
$opts = 'l:p:U:P:u:L:H:X:t:r:q:e:z:f:g:t:c:y:w:b:';
$params = getopt($opts);
//die(print_r($params,1));
$error = false;
if (empty($params['l'])) {
	echo "  -l param must be defined.\n";
	$error = true;
}
if (empty($params['p'])) {
	echo "  -p param must be defined.\n";
	$error = true;
}
if (empty($params['U'])) {
	echo "  -U param must be defined.\n";
	$error = true;
}
if (empty($params['P'])) {
	echo "  -P param must be defined.\n";
	$error = true;
}
if (empty($params['u'])) {
	echo "  -u param must be defined.\n";
	$error = true;
}
if ($error === true) { die('Please ensure you type the command correctly.'."\n\n"); }
$config = array();
/**
 * Init default values
 */
// Values without default (mandatory)
$new_version_stable = true;
$loginForm = $loginForm = $params['l'];
$passForm = $params['p'];
$dbUsernameForm = $params['U'];
$dbPassForm = $params['P'];
$urlForm = $params['u'];
$installType = 'new';
// Values with defaults
$languageForm = 'english';
if (!empty($params['L'])) { $languageForm = $params['L']; }
$dbHostForm = 'localhost';
if (!empty($params['H'])) { $dbHostForm = $params['H']; }
$dbPrefixForm = '';
$dbNameForm = '';
if (!empty($params['X'])) { $dbNameForm = $params['X']; }
$dbStatsForm = 'main';
$dbUserForm = 'main';
$enableTrackingForm = true;
if (!empty($params['t'])) { $enableTrackingForm = getBoolFromString($params['t']); }
$allowSelfReg = 'false';
if (!empty($params['r'])) { $allowSelfReg = $params['r']; }
$allowSelfRegProf = 'false';
if (!empty($params['q'])) { $allowSelfRegProf = $params['q']; }
$encryptPassForm = 'sha1';
if (!empty($params['e'])) { $encryptPassForm = $params['e']; }
$emailForm = 'admin@localhost';
if (!empty($params['z'])) { $emailForm = $params['z']; }
$adminFirstName = 'John';
if (!empty($params['f'])) { $adminFirstName = $params['f']; }
$adminLastName = 'Doe';
if (!empty($params['g'])) { $adminLastName = $params['g']; }
$adminPhoneForm = '';
if (!empty($params['b'])) { $adminPhoneForm = $params['b']; }
$campusForm = 'My Campus';
if (!empty($params['c'])) { $campusForm = $params['c']; }
$institutionForm = 'My company';
if (!empty($params['y'])) { $institutionForm = $params['y']; }
$institutionUrlForm = 'http://www.chamilo.org/';
if (!empty($params['w'])) { $institutionUrlForm = $params['w']; }

echo "All params collected, now starting install...\n\n";

set_file_folder_permissions();
database_server_connect();

// Initialization of the database encoding to be used.
Database::query("SET storage_engine = MYISAM;");
Database::query("SET SESSION character_set_server='utf8';");
Database::query("SET SESSION collation_server='utf8_general_ci';");
Database::query("SET CHARACTER SET 'utf8';");

include 'install_db.inc.php';
include 'install_files.inc.php';

require_once(dirname(__FILE__).'/install_db.inc.php');
require_once(dirname(__FILE__).'/install_files.inc.php');

echo "Installation completed! Please browse $urlForm \nwith login $loginForm/$passForm to ensure the installation is OK...\n\n";

/**
 * Convert string to bool
 * @param string 'true' or 'false'
 * @param bool	true or false
 */
function getBoolFromString($b) {
	return ($b=='true'?true:false);
}
