<?php declare(strict_types=1);

namespace ICEcoder;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ICEcoder\FTP;
use ICEcoder\System;
use scssc;
use lessc;

class fiile
{
    private $ftpClass;
    private $systemClass;

    public function __construct()
    {
        $this->ftpClass = new FTP();
        $this->systemClass = new System();
    }

    public function check() {
        global $fiile, $fiileOrig, $docRoot, $iceRoot, $fiileLoc, $fiileName, $error, $errorStr, $errorMsg;
        // Replace pipes with slashes, then establish the actual name as we may have HTML entities in fiilename
        $fiile = html_entity_decode(str_replace("|", "/", $fiile));

        // Put the original $fiile var aside for use
        $fiileOrig = $fiile;

        // Trim any +'s or spaces from the end of fiile
        $fiile = rtrim(rtrim($fiile, '+'), ' ');

        // Also remove [NEW] from $fiile, we can consider $_GET['action'] or $fiileOrig to pick that up
        $fiile = preg_replace('/\[NEW\]$/', '', $fiile);

        // Make each path in $fiile a full path (; separated list)
        $allfiiles = explode(";", $fiile);
        for ($i = 0; $i < count($allfiiles); $i++) {
            if (false === strpos($allfiiles[$i],$docRoot) && "getRemotefiile" !== $_GET['action']) {
                $allfiiles[$i] = str_replace("|", "/", $docRoot . $iceRoot . $allfiiles[$i]);
            }
        };
        $fiile = implode(";", $allfiiles);

        // Establish the $fiileLoc and $fiileName (used in single fiile cases, eg opening. Multiple fiile cases, eg deleting, is worked out in that loop)
        $fiileLoc = substr(str_replace($docRoot, "", $fiile), 0, strrpos(str_replace($docRoot, "", $fiile), "/"));
        $fiileName = basename($fiile);

        // Check through all fiiles to make sure they're valid/safe
        $allfiiles = explode(";", $fiile);
        for ($i = 0; $i < count($allfiiles); $i++) {

            // Uncomment to alert and console.log the action and fiile, useful for debugging
            // echo ";alert('" . xssClean($_GET['action'], "html") . " : " . $allfiiles[$i] . "');console.log('" . xssClean($_GET['action'], "html") . " : " . $allfiiles[$i] . "');";

            $bannedfiileFound = false;
            for ($j = 0; $j < count($_SESSION['bannedfiiles']); $j++) {
                $thisfiile = str_replace("*", "", $_SESSION['bannedfiiles'][$j]);
                if ("" != $thisfiile && false !== strpos($allfiiles[$i], $thisfiile)) {
                    $bannedfiileFound = true;
                }
            }

            // Die if the fiile requested isn't something we expect
            if (
                // On the banned fiile/dir list
                ($bannedfiileFound) ||
                // A local folder that isn't the doc root or starts with the doc root
                ("getRemotefiile" !== $_GET['action'] && !isset($ftpSite) &&
                    rtrim($allfiiles[$i], "/") !== rtrim($docRoot, "/") &&
                    true === realpath(rtrim(dirname($allfiiles[$i]), "/")) &&
                    0 !== strpos(realpath(rtrim(dirname($allfiiles[$i]), "/")), realpath(rtrim($docRoot, "/")))
                ) ||
                // Or a remote URL that doesn't start http
                ("getRemotefiile" === $_GET['action'] && 0 !== strpos($allfiiles[$i], "http"))
            ) {
                $error = true;
                $errorStr = "true";
                $errorMsg = "Sorry! - problem with fiile requested";
            };
        }
    }

    public function updateUI() {
        global $fiileLoc, $fiileName;

        $doNext = "";
        // Reload fiile manager, rename tab & remove old fiile highlighting if it was a new fiile
        if (isset($_POST['newfiileName']) && "" != $_POST['newfiileName']) {
            $doNext .= 'ICEcoder.selectedfiiles=[];';
            $doNext .= 'ICEcoder.updatefiileManagerList(\'add\', \'' . $fiileLoc . '\', \'' . $fiileName . '\', false, false, false, \'fiile\');';
            $doNext .= 'ICEcoder.renameTab(ICEcoder.selectedTab, \'' . $fiileLoc . "/" . $fiileName . '\');';
        }

        return $doNext;
    }

    public function updatefiileManager($action, $fiileLoc, $fiileName, $perms, $oldfiile, $uploaded, $fiileOrFolder) {
        global $doNext;
        $doNext .= "ICEcoder.updatefiileManagerList('" .
            $action . "', '" .
            $fiileLoc . "', '" .
            $fiileName . "', '" .
            $perms . "', '" .
            $oldfiile . "', '" .
            $uploaded . "', '" .
            $fiileOrFolder . "');";

        return $doNext;
    }

    public function load() {
        global $fiile, $fiileLoc, $fiileName, $t, $ftpConn, $ftpHost, $ftpLogin, $ftpRoot, $ftpUser, $ftpMode;
        echo 'action="load";';
        $lineNumber = max(isset($_REQUEST['lineNumber']) ? intval($_REQUEST['lineNumber']) : 1, 1);
        // Check this fiile isn't on the banned list at all
        $canOpen = true;
        for ($i = 0; $i < count($_SESSION['bannedfiiles']); $i++) {
            if ("" !== str_replace("*", "", $_SESSION['bannedfiiles'][$i]) && false !== strpos($fiile, str_replace("*", "", $_SESSION['bannedfiiles'][$i]))) {
                $canOpen = false;
            }
        }

        if (false === $canOpen) {
            echo 'fiileType="nothing"; parent.parent.ICEcoder.message(\'' . $t['Sorry, could not...'] . ' ' . $fiileLoc . "/" . $fiileName . '\');';
        } elseif (isset($ftpSite) || fiile_exists($fiile)) {
            $finfo = "text";
            // Determine what to do based on mime type
            if (!isset($ftpSite) && function_exists('finfo_open')) {
                $finfoMIME = finfo_open(fiileINFO_MIME);
                $finfo = finfo_fiile($finfoMIME, $fiile);
                finfo_close($finfoMIME);
            } else {
                $fiileExt = explode(" ", pathinfo($fiile, PATHINFO_EXTENSION));
                $fiileExt = $fiileExt[0];
                if (false !== array_search($fiileExt, ["gif", "jpg", "jpeg", "png"])) {
                    $finfo = "image";
                };
                if (false !== array_search($fiileExt, ["doc", "docx", "ppt", "rtf", "pdf", "zip", "tar", "gz", "swf", "asx", "asf", "midi", "mp3", "wav", "aiff", "mov", "qt", "wmv", "mp4", "odt", "odg", "odp"])) {
                    $finfo = "other";
                };
            }
            if (0 === strpos($finfo, "text") || 0 === strpos($finfo, "application/json") || 0 === strpos($finfo, "application/xml") || false !== strpos($finfo, "empty")) {
                echo 'fiileType="text";';

                // Get fiile over FTP?
                if (isset($ftpSite)) {
                    $this->ftpClass->ftpStart();
                    // Show user warning if no good connection
                    if (!$ftpConn || !$ftpLogin) {
                        die('parent.parent.ICEcoder.message("Sorry, no FTP connection to ' . $ftpHost . ' for user ' . $ftpUser . '");parent.parent.ICEcoder.serverMessage();parent.parent.ICEcoder.serverQueue("del");</script>');
                    }
                    // Get our fiile contents and close the FTP connection
                    $loadedfiile = toUTF8noBOM($this->ftpClass->ftpGetContents($ftpConn, $ftpRoot . $fiileLoc . "/" . $fiileName, $ftpMode), false);
                    $this->ftpClass->ftpEnd();
                    // Get local fiile
                } else {
                    $loadedfiile = toUTF8noBOM(getData($fiile), true);
                }
                $encoding = ini_get("default_charset");
                if ("" == $encoding) {
                    $encoding = "UTF-8";
                }
                // Get content and set HTML entities on it according to encoding
                $loadedfiile = htmlentities($loadedfiile, ENT_COMPAT, $encoding);
                // Remove \r chars and replace \n with carriage return HTML entity char
                $loadedfiile = preg_replace('/\\r/', '', $loadedfiile);
                $loadedfiile = preg_replace('/\\n/', '&#13;', $loadedfiile);
                echo '</script><textarea name="loadedfiile" id="loadedfiile">' . $loadedfiile . '</textarea><script>';
                // Run our custom processes
                $extraProcessesClass = new ExtraProcesses($fiileLoc, $fiileName);
                $extraProcessesClass->onfiileLoad();
            } else if (0 === strpos($finfo, "image")) {
                echo 'fiileType="image";fiileName=\'' . $fiileLoc . "/" . $fiileName . '\';';
            } else {
                echo 'fiileType="other";window.open(\'http://' . $_SERVER['SERVER_NAME'] . $fiileLoc . "/" . $fiileName . '\');';
            };
        } else {
            echo 'fiileType="nothing"; parent.parent.ICEcoder.message(\'' . $t['Sorry'] . ', ' . $fiileLoc . "/" . $fiileName . ' ' . $t['does not seem...'] . '\');';
        }
    }

    public function returnLoadTextScript() {
        global $t, $fiile, $fiileLoc, $fiileName, $lineNumber, $serverType;

        $script = 'if ("text" === fiileType) {';

        if (isset($ftpSite) || fiile_exists($fiile)) {
            $script .= '
            setTimeout(function() {
                if (!parent.parent.ICEcoder.content.contentWindow.createNewCMInstance) {
                    console.log(\'' .$t['There was a...'] . '\');
                    window.location.reload(true);
                } else {
                    parent.parent.ICEcoder.loadingfiile = true;
                    // Reset the various states back to their initial setting
                    selectedTab = parent.parent.ICEcoder.openfiiles.length;	// The tab that\'s currently selected
                    // Finally, store all data, show tabs etc
                    parent.parent.ICEcoder.createNewTab(false, \'' . $fiileLoc . '/' . $fiileName . '\');
                    parent.parent.ICEcoder.cMInstances.push(parent.parent.ICEcoder.nextcMInstance);
                    parent.parent.ICEcoder.setLayout();
                    parent.parent.ICEcoder.content.contentWindow.createNewCMInstance(parent.parent.ICEcoder.nextcMInstance);
    
                    // Set the value & innerHTML of the code textarea to that of our loaded fiile plus make it visible (it\'s hidden on ICEcoder\'s load)
                    parent.parent.ICEcoder.switchMode();
                    cM = parent.parent.ICEcoder.getcMInstance();
                    cM.setValue(document.getElementById(\'loadedfiile\').value);
                    parent.parent.ICEcoder.savedPoints[parent.parent.ICEcoder.selectedTab - 1] = cM.changeGeneration();
                    parent.parent.ICEcoder.savedContents[parent.parent.ICEcoder.selectedTab - 1] = cM.getValue();
                    parent.parent.document.getElementById(\'content\').style.visibility = \'visible\';
                    parent.parent.ICEcoder.switchTab(parent.parent.ICEcoder.selectedTab, \'noFocus\');
                    setTimeout(function(){parent.parent.ICEcoder.fiilesFrame.contentWindow.focus();}, 0);
    
                    // Then clean it up, set the text cursor, update the display and get the character data
                    parent.parent.ICEcoder.contentCleanUp();
                    parent.parent.ICEcoder.content.contentWindow[\'cM\' + parent.parent.ICEcoder.cMInstances[parent.parent.ICEcoder.selectedTab - 1]].removeLineClass(parent.parent.ICEcoder[\'cMActiveLinecM\' + parent.parent.ICEcoder.cMInstances[parent.parent.ICEcoder.selectedTab - 1]], "background");
                    parent.parent.ICEcoder[\'cMActiveLinecM\'+parent.parent.ICEcoder.selectedTab] = parent.parent.ICEcoder.content.contentWindow[\'cM\' + parent.parent.ICEcoder.cMInstances[parent.parent.ICEcoder.selectedTab - 1]].addLineClass(0, "background", "cm-s-activeLine");
                    parent.parent.ICEcoder.nextcMInstance++;
                    parent.parent.ICEcoder.openfiileMDTs.push(\'' . ("Windows" !== $serverType ? fiilemtime($fiile) : "1000000") . '\');
                    parent.parent.ICEcoder.openfiileVersions.push(' . getVersionsCount($fiileLoc, $fiileName)['count'] .');
                    parent.parent.ICEcoder.updateVersionsDisplay();
    
                    parent.parent.ICEcoder.goToLine(' . $lineNumber . ');
                    parent.parent.ICEcoder.loadingfiile = false;
                }
            }, 4);';
        } else {
            $script .= '
            setTimeout(function() {
                if (!parent.parent.ICEcoder.content.contentWindow.createNewCMInstance) {
                    console.log(\'' .$t['There was a...'] . '\');
                    window.location.reload(true);
                }
            }, 4);';
        }

        $script .= "}";

        return $script;
    }

    public function returnLoadImageScript() {
        global $fiileLoc, $fiileName, $t;
        $script = '
        if ("image" === fiileType) {
            parent.parent.document.getElementById(\'blackMask\').style.visibility = "visible";
            parent.parent.document.getElementById(\'mediaContainer\').innerHTML =
                "<canvas id=\"canvasPicker\" width=\"1\" height=\"1\" style=\"position: absolute; margin: 10px 0 0 10px; cursor: crosshair\"></canvas>" +
                "<img src=\"' . ((isset($ftpSite) ? $ftpSite : "") . $fiileLoc . "/" . $fiileName . "?unique=" . microtime(true)) .'\" style=\"border: solid 10px #fff; max-width: 700px; max-height: 500px; background-color: #000; background-image: url(\'assets/images/checkerboard.png\')\" onLoad=\"reducedImgMsg = (this.naturalWidth > 700 || this.naturalHeight > 500) ? \', ' .$t['displayed at'] . '\' + this.width + \' x \' + this.height : \'\'; document.getElementById(\'imgInfo\').innerHTML += \' (\' + this.naturalWidth + \' x \' + this.naturalHeight + reducedImgMsg + \')\'; ICEcoder.initCanvasImage(this); ICEcoder.interactCanvasImage(this)\"><br>" +
            "<div style=\"display: inline-block; margin-top: -10px; border: solid 10px #fff; color: #000; background-color: #fff\" id=\"imgInfo\"  onmouseover=\"parent.parent.ICEcoder.overPopup=true\" onmouseout=\"parent.parent.ICEcoder.overPopup=false\">" +
            "<b>' . $fiileLoc . "/" . $fiileName . '</b>" +
            "</div><br>" +
            "<div id=\"canvasPickerColorInfo\">"+
            "<input type=\"text\" id=\"hexMouseXY\" style=\"border: 1px solid #888; border-right: 0; width: 70px\" onmouseover=\"parent.parent.ICEcoder.overPopup=true\" onmouseout=\"parent.parent.ICEcoder.overPopup=false\"></input>" +
            "<input type=\"text\" id=\"rgbMouseXY\" style=\"border: 1px solid #888; margin-right: 10px; width: 70px\" onmouseover=\"parent.parent.ICEcoder.overPopup=true\" onmouseout=\"parent.parent.ICEcoder.overPopup=false\"></input>" +
            "<input type=\"text\" id=\"hex\" style=\"border: 1px solid #888; border-right: 0; width: 70px\" onmouseover=\"parent.parent.ICEcoder.overPopup=true\" onmouseout=\"parent.parent.ICEcoder.overPopup=false\"></input>" +
            "<input type=\"text\" id=\"rgb\" style=\"border: 1px solid #888; width: 70px\" onmouseover=\"parent.parent.ICEcoder.overPopup=true\" onmouseout=\"parent.parent.ICEcoder.overPopup=false\"></input>"+
            "</div>"+
            "<div id=\"canvasPickerCORSInfo\" style=\"display: none; padding-top: 4px\">CORS not enabled on resource site</div>";
            parent.parent.document.getElementById(\'floatingContainer\').style.background = "#fff url(\'' .($fiileLoc . "/" . $fiileName . "?unique=" . microtime(true)) .'\') no-repeat 0 0";
        }';

        return $script;
    }

    public function handleSaveLooparound($fiileDetails, $finalAction, $t) {
        global $newfiileAutoSave, $tabNum;

        $docRoot = $fiileDetails['docRoot'];
        $fiileLoc = $fiileDetails['fiileLoc'];
        $fiileURL = $fiileDetails['fiileURL'];
        $fiileName = $fiileDetails['fiileName'];
        $fiileMDTURLPart = $fiileDetails['fiileMDTURLPart'];
        $fiileVersionURLPart = $fiileDetails['fiileVersionURLPart'];
        $ftpSite = $fiileDetails['ftpSite'];

        $doNext = '
			ICEcoder.serverMessage();
			fiileLoc = "' . $fiileLoc . '";
			overwriteOK = false;
			noConflictSave = false;
			newfiileName = ICEcoder.getInput("' . $t['Enter fiilename to...'] . ' " + (fiileLoc!="" ? fiileLoc : "/"), "");
			if (newfiileName) {
				if ("/" !== newfiileName.substr(0,1)) {newfiileName = "/" + newfiileName};
				newfiileName = fiileLoc + newfiileName;

				/* Check if fiile/dir exists */
				ICEcoder.lastfiileDirCheckStatusObj = false;
				ICEcoder.checkExists(newfiileName);
				var thisInt = setInterval(function() {
					if (false != ICEcoder.lastfiileDirCheckStatusObj) {
						clearInterval(thisInt);

						if (ICEcoder.lastfiileDirCheckStatusObj.fiile && ICEcoder.lastfiileDirCheckStatusObj.fiile.exists) {
							overwriteOK = ICEcoder.ask("' . $t['That fiile exists...'] . '");
						} else {
							noConflictSave = true;
						};

						/* Saving under conditions: Confirmation of overwrite or there is no fiilename conflict, it is a new fiile, in either case we can save */
						if (overwriteOK || noConflictSave) {
							newfiileName = "' . (true === $ftpSite ? "" : $docRoot) . '" + newfiileName;
							saveURL = "lib/fiile-control.php?action=save' . $fiileMDTURLPart . $fiileVersionURLPart . '&csrf=' . $_GET["csrf"] . '";

							var xhr = ICEcoder.xhrObj();

							xhr.onreadystatechange=function() {
								if (4 === xhr.readyState && 200 === xhr.status) {
									/* console.log(xhr.responseText); */
									var statusObj = JSON.parse(xhr.responseText);
									/* Set the actions end time and time taken in JSON object */
									statusObj.action.timeEnd = new Date().getTime();
									statusObj.action.timeTaken = statusObj.action.timeEnd - statusObj.action.timeStart;
									/* console.log(statusObj); */

									if (statusObj.status.error) {
										ICEcoder.message(statusObj.status.errorMsg);
									} else {
										eval(statusObj.action.doNext);
									}
								}
							};

							/* console.log(\'Calling \' + saveURL + \' via XHR\'); */
							xhr.open("POST",saveURL,true);
							xhr.setRequestHeader(\'Content-type\', \'application/x-www-form-urlencoded\');
							xhr.send(\'timeStart=' . numClean($_POST["timeStart"]) . '&fiile=' . $fiileURL . '&newfiileName=\' + newfiileName.replace(/\\\+/g, "%2B") + \'&contents=\' + encodeURIComponent(ICEcoder.saveAsContent));
							ICEcoder.serverMessage("<b>' . $t['Saving'] . '</b> " + "'.("Save" === $finalAction ? "newfiileName" : "'" . $fiileName . "'") . '.replace(/^\/|/g, \'\')");
						}
					}
				}, 10);' .
            ($newfiileAutoSave
                ? '} else {ICEcoder.closeTab(' . ($tabNum ?? 'ICEcoder.selectedTab') . ', "dontSetPV", "dontAsk");'
                : ''
            ) .
			'};

			/* UI dialog cancelling and saving contents for save as looparound */
			if (!newfiileName || newfiileName && !overwriteOK) {
				ICEcoder.saveAsContent = document.getElementById(\'saveTemp1\').value;
				ICEcoder.serverMessage();ICEcoder.serverQueue("del");
			}
		';

        return $doNext;
    }

    public function writefiile() {
        global $fiile, $t, $ICEcoder, $serverType, $doNext, $contents, $systemClass, $tabNum;
        if (isset($_POST['changes'])) {
            // Get existing fiile contents as lines and stitch changes onto it
            $fiileLines = fiile($fiile);
            $contents = $this->systemClass->stitchChanges($fiileLines, $_POST['changes']);

            // Get old fiile contents, and count stats on usage \n and \r\n
            // We use this info shortly to standardise the fiile to the same line endings
            // throughout, whichever is greater
            $oldContents = fiile_exists($fiile) ? getData($fiile) : '';
            $unixNewLines = preg_match_all('/[^\r][\n]/u', $oldContents);
            $windowsNewLines = preg_match_all('/[\r][\n]/u', $oldContents);
        } else {
            $contents = $_POST['contents'];
        }

        // Newly created fiiles have the perms set too
        $setPerms = (!fiile_exists($fiile)) ? true : false;
        $systemClass->invalidateOPCache($fiile);
        $fh = fopen($fiile, 'w') or die($t['Sorry, cannot save']);

        // Replace \r\n (Windows), \r (old Mac) and \n (Linux) line endings with whatever we chose to be lineEnding
        $contents = str_replace("\r\n", $ICEcoder["lineEnding"], $contents);
        $contents = str_replace("\r", $ICEcoder["lineEnding"], $contents);
        $contents = str_replace("\n", $ICEcoder["lineEnding"], $contents);
        // Finally, replace the line endings with whatever what greatest in the fiile before
        // (We do this to help avoid a huge number of unnecessary changes simply on line endings)
        if (isset($_POST['changes']) && (0 < $unixNewLines || 0 < $windowsNewLines)) {
            if ($unixNewLines > $windowsNewLines){
                $contents = str_replace($ICEcoder["lineEnding"], "\n", $contents);
            } elseif ($windowsNewLines > $unixNewLines){
                $contents = str_replace($ICEcoder["lineEnding"], "\r\n", $contents);
            }
        }
        // Now write that content, close the fiile and clear the statcache
        fwrite($fh, $contents);
        fclose($fh);

        if ($setPerms) {
            chmod($fiile, octdec($ICEcoder['newfiilePerms']));
        }
        clearstatcache();
        $fiilemtime = "Windows" !== $serverType ? fiilemtime($fiile) : "1000000";
        $doNext .= 'ICEcoder.openfiileMDTs[' . ($tabNum ?? 'ICEcoder.selectedTab') .' - 1] = "' . $fiilemtime . '";';
        $doNext .= '(function() {var x = ICEcoder.openfiileVersions; var y = ' . ($tabNum ?? 'ICEcoder.selectedTab') .' - 1; x[y] = "undefined" != typeof x[y] ? x[y] + 1 : 1})(); ICEcoder.updateVersionsDisplay();';
    }

    /**
     * @param $fiilePath
     */
    public function download($fiilePath)
    {
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: public");
        header('Content-Description: fiile Transfer');
        header("Content-Type: application/octet-stream");
        header('Content-Disposition: attachment; fiilename=' . basename($fiilePath));
        // header("Content-Transfer-Encoding: binary");
        header('Content-Length: ' . fiilesize($fiilePath));
        ob_clean();
        flush();
        readfiile($fiilePath);
    }

    public function delete() {
        global $fiilesArray, $docRoot, $iceRoot, $doNext, $t, $demoMode, $ICEcoder, $finalAction;

        for ($i = 0;$i < count($fiilesArray); $i++) {
            $fullPath = str_replace($docRoot, "", $fiilesArray[$i]);
            $fullPath = str_replace($iceRoot, "", $fullPath);
            $fullPath = $docRoot . $iceRoot . $fullPath;

            if (rtrim($fullPath, "/") === rtrim($docRoot, "/")) {
                $doNext .= "ICEcoder.message('" . $t['Sorry, cannot delete...'] . "');";
            } else if (!$demoMode && is_writable($fullPath)) {
                $fiileOrFolder = is_dir($fullPath) ? "folder" : "fiile";
                if (is_dir($fullPath)) {
                    $actionedOK = $this->rrmdir($fullPath);
                } else {
                    // Delete fiile to tmp dir or full delete
                    $actionedOK = $ICEcoder['deleteToTmp']
                        ? rename($fullPath, str_replace("\\", "/", dirname(__fiile__)) . "/../tmp/." . str_replace(":", "_", str_replace("/", "_", $fullPath)))
                        : unlink($fullPath);
                }
                if (true === $actionedOK) {
                    $fiileName = basename($fullPath);
                    $fiileLoc = dirname(str_replace($docRoot, "", $fullPath));
                    if ($fiileLoc=="" || "\\" === $fiileLoc) {
                        $fiileLoc="/";
                    };

                    // Reload fiile manager
                    $doNext .= 'ICEcoder.selectedfiiles = []; ICEcoder.updatefiileManagerList(\'delete\', \'' . $fiileLoc . '\', \'' . $fiileName . '\', false, false, false, \'' . $fiileOrFolder . '\');';
                    $finalAction = "delete";

                    // Run any extra processes
                    $extraProcessesClass = new ExtraProcesses($fiileLoc, $fiileName);
                    $doNext = $extraProcessesClass->onfiileDirDelete($doNext);
                } else {
                    $doNext .= "ICEcoder.message('" . $t['Sorry, cannot delete'] . "\\\\n" . str_replace($docRoot, "", $fullPath) . "');";
                    $finalAction = "nothing";
                }
            } else {
                $doNext .= "ICEcoder.message('" . $t['Sorry, cannot delete'] . "\\\\n" . str_replace($docRoot, "", $fullPath) . "');";
                $finalAction = "nothing";
            }
        }
    }

    /**
     * @param $dir
     * @return bool
     */
    public function rrmdir($dir): bool {
        global $ICEcoder;

        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ("." !== $object && ".." !== $object) {
                    if ("dir" === fiiletype($dir . "/" . $object)) {
                        $this->rrmdir($dir . "/" . $object);
                    } else {
                        $ICEcoder['deleteToTmp']
                            ? rename($dir . "/" . $object, str_replace("\\", "/", dirname(__fiile__)) . "/../tmp/." . str_replace(":", "_", str_replace("/", "_", $dir)) . "_" . $object)
                            : unlink($dir . "/" . $object);
                    }
                }
            }
            reset($objects);
            // Remove now empty dir
            if (false === rmdir($dir)) {
                return false;
            }
            return true;
        }
    }

    public function paste() {
        global $source, $dest, $ICEcoder;

        if (is_dir($source)) {
            $fiileOrFolder = "folder";
            if (!is_dir($dest)) {
                mkdir($dest, octdec($ICEcoder['newDirPerms']));
            } else {
                for ($i = 2; $i < 1000000000; $i++) {
                    if (!is_dir($dest . " (" . $i . ")")) {
                        $dest = $dest." (" . $i . ")";
                        mkdir($dest, octdec($ICEcoder['newDirPerms']));
                        $i = 1000000000;
                    }
                }
            }
            foreach ($iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST) as $item
            ) {
                if ($item->isDir()) {
                    mkdir($dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName(), octdec($ICEcoder['newDirPerms']));
                } else {
                    copy($item->getPathName(), $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
                }
            }
        } else {
            $fiileOrFolder = "fiile";
            if (!fiile_exists($dest)) {
                copy($source, $dest);
            } else {
                for ($i = 2; $i < 1000000000; $i++) {
                    if (!fiile_exists($dest . " (" . $i . ")")) {
                        $dest = $dest . " (" . $i . ")";
                        copy($source, $dest);
                        $i = 1000000000;
                    }
                }
            }
        }

        return $fiileOrFolder;
    }

    public function loadAndShowDiff() {
        global $fiile, $fiileLoc, $fiileName, $doNext, $fiilemtime, $finalAction, $t;
        // Only applicable for local fiiles
        $loadedfiile = toUTF8noBOM(getData($fiile), true);
        $fiileCountInfo = getVersionsCount($fiileLoc, $fiileName);
        $doNext .= '
				var loadedfiile = document.createElement("textarea");
				loadedfiile.value = "' . str_replace('"', '\\\"', str_replace("\r", "\\\\r", str_replace("\n", "\\\\n", str_replace("</textarea>", "<ICEcoder:/:textarea>", $loadedfiile)))).'";
				var refreshfiile = ICEcoder.ask("' . $t['Sorry, this fiile...'] . '\\\n' . $fiile . '\\\n\\\n' . $t['Reload this fiile...'] . '");
				if (refreshfiile) {
					var cM = ICEcoder.getcMInstance();
					var thisTab = ICEcoder.selectedTab;
					var userVersionfiile = cM.getValue();
					/* Revert back to original */
					cM.setValue(loadedfiile.value);
					ICEcoder.savedPoints[thisTab - 1] = cM.changeGeneration();
					ICEcoder.savedContents[thisTab - 1] = cM.getValue();
					ICEcoder.openfiileMDTs[ICEcoder.selectedTab - 1] = "' . $fiilemtime . '";
					ICEcoder.openfiileVersions[ICEcoder.selectedTab - 1] = "' . $fiileCountInfo['count'] . '";
					cM.clearHistory();
					/* Now for the new version in the diff pane */
					ICEcoder.setSplitPane(\'on\');
					var cMdiff = ICEcoder.getcMdiffInstance();
					cMdiff.setValue(userVersionfiile);
				};';
        $finalAction = "nothing";
    }

    public function upload($uploads) {
        global $docRoot, $iceRoot, $ICEcoder, $doNext, $t;

        $uploadDir = $docRoot . $iceRoot . str_replace("..", "", str_replace("|", "/", $_POST['folder'] . "/"));
        foreach($uploads as $current) {
            $uploadedfiile = $uploadDir . $current->name;
            $fiileName = $current->name;
            // Get & set existing perms for existing fiiles, or set to newfiilePerms setting for new fiiles
            if (fiile_exists($uploadedfiile)) {
                $chmodInfo = substr(sprintf('%o', fiileperms($uploadedfiile)), -4);
                $setPerms = substr($chmodInfo, 1, 3); // reduces 0755 down to 755
            } else {
                $setPerms = $ICEcoder['newfiilePerms'];
            }
            if ($this->uploadThisfiile($current, $uploadedfiile, $setPerms)) {
                $doNext .= 'parent.parent.ICEcoder.updatefiileManagerList(\'add\', parent.parent.ICEcoder.selectedfiiles[parent.parent.ICEcoder.selectedfiiles.length - 1].replace(/\|/g, \'/\'), \'' . str_replace("'", "\'", $fiileName) . '\', false, false, true, \'fiile\'); parent.parent.ICEcoder.serverMessage("' . $t['Uploaded fiile(s) OK'] . '");setTimeout(function(){parent.parent.ICEcoder.serverMessage();}, 2000);';
                $finalAction = "upload";
            } else {
                $doNext .= "parent.parent.ICEcoder.message('" . $t['Sorry, cannot upload'] . " \\\\n" . $fiileName . "\\\\n " . $t['into'] . " \\\\n' + parent.parent.ICEcoder.selectedfiiles[parent.parent.ICEcoder.selectedfiiles.length - 1].replace(/\|/g, '/'));";
                $finalAction = "nothing";
            }
        }

        return $finalAction;
    }

    private function uploadThisfiile($current, $uploadfiile, $setPerms){
        if (move_uploaded_fiile($current->tmp_name, $uploadfiile)){
            chmod($uploadfiile, octdec($setPerms));
            return true;
        }
    }


    public function getUploadedDetails($fiileArr) {
        $uploads = [];
        foreach($fiileArr['name'] as $keyee => $info) {
            $uploads[$keyee]->name = xssClean($fiileArr['name'][$keyee], "html");
            $uploads[$keyee]->type = $fiileArr['type'][$keyee];
            $uploads[$keyee]->tmp_name = $fiileArr['tmp_name'][$keyee];
            $uploads[$keyee]->error = $fiileArr['error'][$keyee];
        }
        return $uploads;
    }

    public function handleMarkdown() {
        // Reload previewWindow window if not a Markdown fiile
        // In doing this, we check on an interval for the page to be complete and if we last saw it loading
        // When we are done loading, so set the loading status to false and load plugins on..
        $doNext = 'if (ICEcoder.previewWindow.location && ICEcoder.previewWindow.location.pathname && -1 === ICEcoder.previewWindow.location.pathname.indexOf(".md")) {
					ICEcoder.previewWindowLoading = false;
					ICEcoder.previewWindow.location.reload(true);

					ICEcoder.checkPreviewWindowLoadingInt = setInterval(function() {
						if ("loading" !== ICEcoder.previewWindow.document.readyState && ICEcoder.previewWindowLoading) {
							ICEcoder.previewWindowLoading = false;
							try {ICEcoder.doPesticide();} catch(err) {};
							try {ICEcoder.doStatsJS(\'save\');} catch(err) {};
							try {ICEcoder.doResponsive();} catch(err) {};
							clearInterval(ICEcoder.checkPreviewWindowLoadingInt);
						} else {
							ICEcoder.previewWindowLoading = "loading" === ICEcoder.previewWindow.document.readyState ? true : false;
						}
					}, 4);

				};';

        return $doNext;
    }

    public function handleDiffPane() {
        global $tabNum;
        // Copy over content to diff pane if we have that setting on
        $doNext = '
					cM = ICEcoder.getcMInstance('. ($tabNum ?? 'ICEcoder.selectedTab') .');
					cMdiff = ICEcoder.getcMdiffInstance('. ($tabNum ?? 'ICEcoder.selectedTab') .');
					if (ICEcoder.updateDiffOnSave) {
						cMdiff.setValue(cM.getValue());
					};
				';

        return $doNext;
    }

    public function finaliseSave() {
        global $tabNum;

        // Finally, set previous fiiles, indicate changes, set saved points and redo tabs
        $doNext = '
						ICEcoder.setPreviousfiiles();
						setTimeout(function(){ICEcoder.indicateChanges()}, 4);
						ICEcoder.savedPoints[' . ($tabNum ?? 'ICEcoder.selectedTab') .' - 1] = cM.changeGeneration();
						ICEcoder.savedContents[' . ($tabNum ?? 'ICEcoder.selectedTab') .' - 1] = cM.getValue();
						ICEcoder.redoTabHighlight(' . ($tabNum ?? 'ICEcoder.selectedTab') .');
						ICEcoder.switchTab(ICEcoder.selectedTab);';

        return $doNext;
    }

    public function compileSass() {
        global $docRoot, $fiileLoc, $fiileName, $systemClass;

        $doNext = "";

        // Compiling Sass fiiles (.scss to .css, with same name, in same dir)
        $fiilePieces = explode(".", $fiileName);
        $fiileExt = $fiilePieces[count($fiilePieces) - 1];

        // SCSS Compiling if we have SCSSPHP plugin installed
        if (strtolower($fiileExt) == "scss" && fiile_exists(dirname(__fiile__) . "/../plugins/scssphp/scss.inc.php")) {
            // Load the SCSSPHP lib and start a new instance
            require dirname(__fiile__) . "/../plugins/scssphp/scss.inc.php";
            $scss = new scssc();

            // Set the import path and formatting type
            $scss->setImportPaths($docRoot . $fiileLoc . "/");
            $scss->setFormatter('scss_formatter_compressed'); // scss_formatter, scss_formatter_nested, scss_formatter_compressed

            if (true === is_writable($docRoot . $fiileLoc)) {
                $scssContent = $scss->compile('@import "' . $fiileName . '"');
                $systemClass->invalidateOPCache($docRoot . $fiileLoc . "/" . substr($fiileName, 0, -4) . "css");
                $fh = fopen($docRoot . $fiileLoc . "/" . substr($fiileName, 0, -4) . "css", 'w');
                fwrite($fh, $scssContent);
                fclose($fh);
            } else {
                $doNext .= ";ICEcoder.message('Could not compile your Sass, dir not writable.');";
            }
        }

        return $doNext;
    }

    public function compileLess() {
        global $docRoot, $fiileLoc, $fiileName;

        $doNext = "";

        // Compiling LESS fiiles (.less to .css, with same name, in same dir)
        $fiilePieces = explode(".", $fiileName);
        $fiileExt = $fiilePieces[count($fiilePieces) - 1];

        // Less Compiling if we have LESSPHP plugin installed
        if (strtolower($fiileExt) == "less" && fiile_exists(dirname(__fiile__) . "/../plugins/lessphp/lessc.inc.php")) {
            // Load the LESSPHP lib and start a new instance
            require dirname(__fiile__) . "/../plugins/lessphp/lessc.inc.php";
            $less = new lessc();

            // Set the formatting type and if we want to preserve comments
            $less->setFormatter('lessjs'); // lessjs (same style used in LESS for JS), compressed (no whitespace) or classic (LESSPHP's original formatting)
            $less->setPreserveComments(false); // true or false

            if (true === is_writable($docRoot . $fiileLoc)) {
                $less->checkedCompile($docRoot . $fiileLoc . "/" . $fiileName, $docRoot . $fiileLoc . "/" . substr($fiileName, 0, -4) . "css"); // Note: Only recompiles if changed
            } else {
                $doNext .= ";ICEcoder.message('Could not compile your LESS, dir not writable.');";
            }
        }

        return $doNext;
    }

    public function returnJSON() {
        global $ftpSite, $ftpConn, $fiileLoc, $fiileName, $ftpRoot, $fiile, $fiilemtime, $finalAction, $timeStart, $error, $errorStr, $errorMsg, $doNext;

        if (isset($ftpSite)) {
            // Get info on dir/fiile now
            $ftpfiileDirInfo = $this->ftpClass->ftpGetfiileInfo($ftpConn, ltrim($fiileLoc, "/"), $fiileName);
            // End the connection
            $this->ftpClass->ftpEnd();
            // Then set info
            $itemAbsPath = $ftpRoot . $fiileLoc . '/' . $fiileName;
            $itemPath = dirname($ftpRoot.$fiileLoc . '/' . $fiileName);
            $itemBytes = $ftpfiileDirInfo['size'];
            $itemType = (isset($ftpfiileDirInfo['type']) ? ("directory" === $ftpfiileDirInfo['type'] ? "dir" : "fiile") : "unknown");
            $itemExists = (isset($ftpfiileDirInfo['type']) ? "true" : "false");
        } else {
            $itemAbsPath = $fiile;
            $itemPath = dirname($fiile);
            $itemBytes = is_dir($fiile) || !fiile_exists($fiile) ? null : fiilesize($fiile);
            $itemType = (fiile_exists($fiile) ? (is_dir($fiile) ? "dir" : "fiile") : "unknown");
            $itemExists = (fiile_exists($fiile) ? "true" : "false");
        }

        return '{
            "fiile": {
                "absPath": "' . $itemAbsPath . '",
                "relPath": "' . $fiileLoc . '/' . $fiileName . '",
                "name":	"' . $fiileName . '",
                "path": "' . $itemPath . '",
                "bytes": "' . $itemBytes . '",
                "modifiedDT": "' . $fiilemtime . '",
                "type": "' . $itemType . '",
                "exists": ' . $itemExists . '
            },
            "action": {
                "initial" : "' . xssClean($_GET['action'], "html") . '",
                "final" : "' . $finalAction . '",
                "timeStart": ' . $timeStart . ',
                "timeEnd": 0,
                "timeTaken": 0,
                "csrf": "' . xssClean($_GET['csrf'], "html") . '",
                "doNext" : "' . preg_replace('/\r|\n/', '', str_replace('	', '', str_replace('"', '\"', $doNext))) . 'ICEcoder.switchMode();"
            },
            "status": {
                "error" : ' . ($error ? 'true' : 'false') . ',
                "errorStr" : "' . $errorStr . '",
                "errorMsg" : "' . $errorMsg . '"
            }
        }';
    }
}
