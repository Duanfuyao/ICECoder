<?php
require_once dirname(__FILE__) . "/../classes/_ExtraProcesses.php";
require_once dirname(__FILE__) . "/../classes/Settings.php";
require_once dirname(__FILE__) . "/../classes/System.php";

use ICEcoder\ExtraProcesses;

$settingsClass = new \ICEcoder\Settings();
$systemClass = new \ICEcoder\System();

// Check data dir exists, is readable and writable
// 判断data文件夹是否存在，以及其是否可读可写
if (false === $settingsClass->getDataDirDetails()['exists']) {
    $reqsFailures = ["phpDataDirDoesntExist"];
    include dirname(__FILE__) . "/requirements.php";
}

if (false === $settingsClass->getDataDirDetails()['readable']) {
    $reqsFailures = ["phpDataDirNotReadable"];
    include dirname(__FILE__) . "/requirements.php";
}

if (false === $settingsClass->getDataDirDetails()['writable']) {
    $reqsFailures = ["phpDataDirNotWritable"];
    include dirname(__FILE__) . "/requirements.php";
}

// Create a new global config file if it doesn't exist yet.
// 如果还没有全局config文件，那么新建一个
// The reason we create it, is so it has PHP write permissions, meaning we can update it later
if (false === $settingsClass->getConfigGlobalFileDetails()['exists']) {
    if (false === $settingsClass->setConfigGlobalSettings($settingsClass->getConfigGlobalTemplate())) {
        $reqsFailures = ["phpGlobalConfigFileCreate"];
        include dirname(__FILE__) . "/requirements.php";
    }
}

// Check global config settings file exists
// 这里没太明白，为啥不在上面一起弄了？
if (false === $settingsClass->getConfigGlobalFileDetails()['exists']) {
    $reqsFailures = ["phpGlobalConfigFileExists"];
    include dirname(__FILE__) . "/requirements.php";
}

// Check we can read global config settings file
// 检查全局config文件是否可读
if (false === $settingsClass->getConfigGlobalFileDetails()['readable']) {
    $reqsFailures = ["phpGlobalConfigReadFile"];
    include dirname(__FILE__) . "/requirements.php";
}

// Check we can write global config settings file
// 检查全局config文件是否可写
if (false === $settingsClass->getConfigGlobalFileDetails()['writable']) {
    $reqsFailures = ["phpGlobalConfigWriteFile"];
    include dirname(__FILE__) . "/requirements.php";
}

// Load global config settings
// 读取全局config文件的设置（这设置的是什么？待确认）
$ICEcoderSettings = $settingsClass->getConfigGlobalSettings();

// Load common functions
// 载入common函数？（这是什么？include函数是怎么操作的？）
include_once dirname(__FILE__) . "/settings-common.php";

// Establish user settings file
// 建立用户设置文件名
$username = "";
if (true === isset($_POST['username']) && "" !== $_POST['username']) {$username = $_POST['username'] . "-";};
if (true === isset($_SESSION['username']) && "" !== $_SESSION['username']) {$username = $_SESSION['username'] . "-";};
$settingsFile = 'config-' . $username . str_replace(".", "_", str_replace("www.", "", $_SERVER['SERVER_NAME'])) . '.php';

// Login is default
$setPWorLogin = "login";

// Create user settings file if it doesn't exist
// 如果用户config文件不存在，建立一个（好像在data文件夹）
if (true === $ICEcoderSettings['enableRegistration'] && false === $settingsClass->getConfigUsersFileDetails($settingsFile)['exists']) {
    if (false === $settingsClass->setConfigUsersSettings($settingsFile, $settingsClass->getConfigUsersTemplate())) {
        $reqsFailures = ["phpUsersConfigCreateConfig"];
        include dirname(__FILE__) . "/requirements.php";
    }
    $setPWorLogin = "set password";
}

// Check users config settings file exists
// 检查用户config文件是否存在（和上面类似的操作）
if (false === $settingsClass->getConfigUsersFileDetails($settingsFile)['exists']) {
    $reqsFailures = ["phpUsersConfigFileExists"];
    include dirname(__FILE__) . "/requirements.php";
}

// Check we can read users config settings file
// 检查用户config文件是否可读
if (false === $settingsClass->getConfigUsersFileDetails($settingsFile)['readable']) {
    $reqsFailures = ["phpUsersConfigReadFile"];
    include dirname(__FILE__) . "/requirements.php";
}

// Check we can write users config settings file
// 检查用户config文件是否可写
if (false === $settingsClass->getConfigUsersFileDetails($settingsFile)['writable']) {
    $reqsFailures = ["phpUsersConfigWriteFile"];
    include dirname(__FILE__) . "/requirements.php";
}

// Load users config settings
// 读取用户config文件
$ICEcoderUserSettings = $settingsClass->getConfigUsersSettings($settingsFile);

// Remove any previous files that are no longer there
// 把“之前文件”中不存在的删掉（这是啥意思？）
for ($i = 0; $i < count($ICEcoderUserSettings['previousFiles']); $i++) {
    if (false === file_exists(str_replace("|", "/", $ICEcoderUserSettings['previousFiles'][$i]))) {
        array_splice($ICEcoderUserSettings['previousFiles'], $i, 1);
    }
}

// Replace our config created date with the filemtime?
// 设置日期？（这是图啥？）
if ("index.php" === basename($_SERVER['SCRIPT_NAME']) && 0 === $ICEcoderUserSettings['configCreateDate']) {
    $settingsClass->updateConfigUsersCreateDate($settingsFile);
}

// On mismatch of settings file to system, rename to .old and reload
// 如果版本编号不匹配，则重命名成.old再载入（这个操作是在哪里实现的？reqsFailures吗？）
If ($ICEcoderUserSettings["versionNo"] !== $ICEcoderSettings["versionNo"]) {
    $reqsFailures = ["phpUsersConfigVersionMismatch"];
    include dirname(__FILE__) . "/requirements.php";
}

// Join ICEcoder global config settings and user config settings together to make our final ICEcoder array
// 将全局设置和用户设置拼接起来，成为最终的ICEcoder命名
$ICEcoder = $ICEcoderSettings + $ICEcoderUserSettings;

// Include language file
// 读取language文件
// Load base first as foundation
// 载入languageBase目录
include dirname(__FILE__) . "/../lang/" . basename($ICEcoder['languageBase']);
$baseText = $text;

// Load chosen language ontop to replace base
// 读取选择的语言文件，替换原来的
include dirname(__FILE__) . "/../lang/" . basename($ICEcoder['languageUser']);
$text = array_replace_recursive($baseText, $text);
$_SESSION['text'] = $text;
//保存到了SESSION数组中（SESSION数组是全局变量存储吗？）

// Login not required or we're in demo mode and have password set in our settings, log us straight in
// 如果不需要登录，或者在demo模式下并且设置了密码，直接登录
if ((false === $ICEcoder['loginRequired'] || true === $ICEcoder['demoMode']) && "" !== $ICEcoder['password']) {
    $_SESSION['loggedIn'] = true;
};
$demoMode = $ICEcoder['demoMode'];

// Update global config and users config files?
// 通过setting-updata.php来更新全局config和用户config设置
include dirname(__FILE__) . "/settings-update.php";
//只在这里调用了s-u.php文件

// Set loggedIn and username to false if not set as yet
// 如果全局变量？loggenIn和username还没有设置，初始化他们
if (false === isset($_SESSION['loggedIn'])) {$_SESSION['loggedIn'] = false;};
if (false === isset($_SESSION['username'])) {$_SESSION['username'] = "";};

// Attempt a login with password
// 尝试使用密码登录
if (true === isset($_POST['submit']) && "login" === $setPWorLogin) {
    // On success, set username if multiUser, loggedIn to true and redirect
    if (verifyHash($_POST['password'], $ICEcoder["password"]) === $ICEcoder["password"]) {
        session_regenerate_id();
        if ($ICEcoder["multiUser"]) {
            $_SESSION['username'] = $_POST['username'];
        }
        $_SESSION['loggedIn'] = true;
        $extraProcessesClass = new ExtraProcesses();
        $extraProcessesClass->onUserLogin($_SESSION['username'] ?? "");
        header('Location: ../');
        echo "<script>window.location = '../';</script>";
        die('Logging you in...');
    } else {
        $extraProcessesClass = new ExtraProcesses();
        $extraProcessesClass->onUserLoginFail($_SESSION['username'] ?? "");
    }
};

// Define the serverType, docRoot & iceRoot
// 定义server类型，文件根目录
$serverType = $systemClass->getOS();
$docRoot = rtrim(str_replace("\\", "/", $ICEcoder['docRoot']));
$iceRoot = rtrim(str_replace("\\", "/", $ICEcoder["root"]));
if ($_SESSION['loggedIn'] && "index.php" === basename($_SERVER['SCRIPT_NAME'])) {
    echo "<script>docRoot = '" . $docRoot . "'; iceRoot='" . $iceRoot . "'</script>";
}

// Establish the dir ICEcoders running from
// 生成ICEcoder的文件目录
$ICEcoderDirFullPath = rtrim(str_replace("\\", "/", dirname($_SERVER['SCRIPT_FILENAME'])), "/lib");
$rootPrefix = '/' . str_replace("/", "\/", preg_quote(str_replace("\\", "/", $docRoot))) . '/';
$ICEcoderDir = preg_replace($rootPrefix, '', $ICEcoderDirFullPath, 1);

// Setup our file security vars
// 设置3个安全参数
$settingsArray = ["findFilesExclude", "bannedFiles", "allowedIPs"];
for ($i = 0; $i < count($settingsArray); $i++) {
    if (false === isset($_SESSION[$settingsArray[$i]])) {
        $_SESSION[$settingsArray[$i]] = $ICEcoder[$settingsArray[$i]];
    }
}

// Check IP permissions
// 检查IP许可（这里是只检查变量？）
if (false === in_array(getUserIP(), $_SESSION['allowedIPs']) && false === in_array("*", $_SESSION['allowedIPs'])) {
    header('Location: /');
    $reqsFailures = ["systemIPRestriction"];
    include(dirname(__FILE__) . "/requirements.php");
};

// Establish any FTP site to use
// 设置FTPsite数组的变量
if (true === isset($_SESSION['ftpSiteRef']) && false !== $_SESSION['ftpSiteRef']) {
    $ftpSiteArray = $ICEcoder['ftpSites'][$_SESSION['ftpSiteRef']];
    $ftpSite = $ftpSiteArray['site'];                                         // FTP site domain, eg http://yourdomain.com
    $ftpHost = $ftpSiteArray['host'];                                         // FTP host, eg ftp.yourdomain.com
    $ftpUser = $ftpSiteArray['user'];                                         // FTP username
    $ftpPass = $ftpSiteArray['pass'];                                         // FTP password
    $ftpPasv = $ftpSiteArray['pasv'];                                         // FTP account requires PASV mode?
    $ftpMode = $ftpSiteArray['mode'] == "FTP_ASCII" ? FTP_ASCII : FTP_BINARY; // FTP transfer mode, FTP_ASCII or FTP_BINARY
    $ftpRoot = $ftpSiteArray['root'];                                         // FTP root dir to use as base, eg /htdocs
}

// Save currently opened files in previousFiles and last10Files arrays
// 将当前打开的文件放到previousFiles和last10Files数组中
include(dirname(__FILE__) . "/settings-save-current-files.php");

// Display the plugins
// 显示插件
include(dirname(__FILE__) . "/plugins-display.php");
// 唯一一次调用此php

// If loggedIn is false or we don't have a password set yet and we're not on login screen, boot user to that
// 如果loggenIn变量是false（也就是没登录），或者没有设置密码并且不在登录页面（这是什么情况），将用户跳转到登录页面
if (false === isset($_POST['password']) && (!$_SESSION['loggedIn'] || "" === $ICEcoder["password"]) && false === strpos($_SERVER['SCRIPT_NAME'], "lib/login.php")) {
    if (file_exists('lib/login.php')) {
        header('Location: ' . rtrim($_SERVER['REQUEST_URI'], "/") . '/lib/login.php');
        echo "<script>window.location = 'lib/login.php';</script>";
    } else {
        header('Location: login.php');
        echo "<script>window.location = 'login.php';</script>";
    }
    die('Redirecting to login...');

// If we are on the login screen and not logged in
// 如果在登录页面，但是没有登录
// 如下，全局变量记录了用户的状态
} elseif (!$_SESSION['loggedIn']) {
    // If the password hasn't been set and we're setting it
    // 如果还没有设置密码，现在设置
    if ("" === $ICEcoder["password"] && true === isset($_POST['submit']) && -1 < strpos($_POST['submit'], "set password")) {
        $password = generateHash($_POST['password']);
        $settingsClass->updateConfigUsersSettings($settingsFile, ["password" => $password, "checkUpdates" => $_POST["checkUpdates"]]);
        $settingsClass->createIPSettingsFileIfNotExist();
        if (true === isset($_POST['disableFurtherRegistration'])) {
            $settingsClass->updateConfigGlobalSettings(['enableRegistration' => false]);
        }
        // Set the session user level
        if ($ICEcoder["multiUser"]) {
            $_SESSION['username'] = $_POST['username'];
        }
        $_SESSION['loggedIn'] = true;
        $extraProcessesClass = new ExtraProcesses();
        $extraProcessesClass->onUserNew($_SESSION['username'] ?? "");
        // Finally, load again as now this file has changed and auto login
        header('Location: ../');
        echo "<script>window.location = '../';</script>";
        die('Logging you in...');
    }
    // ===================================================
    // We're likely showing the login screen at this point
    // ===================================================
} elseif ($ICEcoder['loginRequired'] && $_SESSION['loggedIn'] && "" === $ICEcoder["password"]) {
    header("Location: ../?logout");
    echo "<script>window.location = '../?logout';</script>";
    die('Logging you out...');
} else {
    // ==================================
    // Continue with whatever we're doing
    // ==================================
}
