<?php
/*
    telegraf-config.php

    Copyright (c) 2018 Andreas Schmidhuber
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice, this
       list of conditions and the following disclaimer.
    2. Redistributions in binary form must reproduce the above copyright notice,
       this list of conditions and the following disclaimer in the documentation
       and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
    ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
    ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
    (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
    LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
    ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
    (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
require("auth.inc");
require("guiconfig.inc");

$configAddon = "/var/etc/telegraf_conf/conf/telegraf_config";

$textdomain = "/usr/local/share/locale";
$textdomain_telegraf = "/usr/local/share/locale-telegraf";
if (!is_link($textdomain_telegraf)) { mwexec("ln -s {$rootfolder}/locale-telegraf {$textdomain_telegraf}", true); }
bindtextdomain("xigmanas", $textdomain_telegraf);

$pgtitle = array(gtext("Extensions"), gtext("Telegraf"), gtext("Configuration"));

$wSpace = "&nbsp;&nbsp;";
$wSpaceEqual = "&nbsp;&nbsp;=&nbsp;&nbsp;";
$paramNameSize = 30;	//length of parameter name input field, default for parameter value input field is '80' 

function htmlInput($name, $title, $value="", $size=80) {
	$result = "<input name='{$name}' size='{$size}' title='{$title}' placeholder='{$title}' value='{$value}' />";
	return $result;
}

function htmlButton($name, $text, $value="", $title="", $confirm="", $buttonImage="") {
	$onClick = ($confirm == "") ? "" : "onclick='return confirm(\"{$confirm}\")'";
	switch ($buttonImage) {
		case "add": $buttonImage = "<img src='images/add.png' height='10' width='10'>"; break; 
		case "delete": $buttonImage = "<img src='images/delete.png' height='10' width='10'>"; break;
		case "save": $buttonImage = "<img src='images/status_enabled.png' height='10' width='10'>"; break;
		default: $buttonImage = "";
	}
	$result = "<button name='{$name}' type='submit' class='formbtn' title='{$title}' value='{$value}' {$onClick}>{$buttonImage}{$text}</button>";
	return $result;
}

function parseConfigFile($configFile) {
	$fileArray = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);	// load config file content to array
	$configArray = array();
	foreach($fileArray as $line) {							// create array from config
	    $line = trim($line);								// remove leading/trailing space
	    if ($line[0] == "#") continue;						// skip if comment line
	    if ($line[0] == "[") {								// add as section
	        $configArray[$line] = [];
	        $section = $line;								// remember section name for params
	    } else {											// process params
	        $parameter = explode("=", $line);				// seperate key and value
	        $key = trim($parameter[0]);						// set key
	        $val = explode("#", trim($parameter[1]));		// get value, remove trailing comments
	        $value = $val[0];								// set value
	        $configArray[$section][$key] = $value;			// add param to section
	    }
	}
	return $configArray;
}

function saveConfigFile($configFile, $configArray, $hashTag="", $prettyPrint=true) {
	$printTab = ($prettyPrint) ? "\t" : "";
	$printSpace = ($prettyPrint) ? " " : "";

	$cFile = fopen($configFile, "w");
	foreach($configArray as $key => $line) {									// traverse array, key = section
		if (is_array($line)) {
			if ($key != '') fwrite($cFile, $key.PHP_EOL);						// write section if not "['']" => NO section
			foreach($line as $pName => $pValue) fwrite($cFile, $printTab.$pName.$printSpace."=".$printSpace.$pValue.PHP_EOL);	// "\t".$pName	= add TAB for output formatting
			fwrite($cFile, PHP_EOL);
		} else fwrite($cFile, $key.$printSpace."=".$printSpace.$line.PHP_EOL);
	}	// end foreach
	fclose($cFile);
	if (!empty($hashTag)) header("Location:#{$hashTag}");
}

// load addon config - use selected config from Telegraf tab or alternative if exist
$configAddonArray = parseConfigFile($configAddon);									// read addon config file
if (empty($configAddonArray['']['ALTERNATIVE_CONFIG'])) $configFile = str_replace('"', "", $configAddonArray['']['TELEGRAF_CONFIG']);	// get telegraf config file path and name
else $configFile = str_replace('"', "", $configAddonArray['']['ALTERNATIVE_CONFIG']);	// get Telegraf config file path and name

// load Telegraf config
if (!is_file($configFile)) $input_errors[] = sprintf(gtext("%s not found!"), gettext("Configuration File")." {$configFile}");
else {
	$configArray = parseConfigFile($configFile);									// parse Telegraf config file
	$savemsg = gtext("Loaded config file").": <b>".basename($configFile)."</b>";
}

if ($_POST) {
	unset($input_errors);
	
	if ((isset($_POST['loadConfig']) && $_POST['loadConfig']) || (isset($_POST['saveConfig']) && $_POST['saveConfig'])) {
		$altConfigPath = trim($_POST['altConfigPath']);
		if ($altConfigPath == "") $input_errors[] = sprintf(gtext("%s name must not be empty!"), gettext("Configuration File"));
		elseif (isset($_POST['loadConfig']) && !is_file($altConfigPath)) 
			$input_errors[] = sprintf(gtext("%s not found!"), gettext("Configuration File")." {$altConfigPath}");
		else {
			$configAddonArray = parseConfigFile($configAddon);									// read addon config file
			$configAddonArray['']['ALTERNATIVE_CONFIG'] = "\"{$altConfigPath}\"";				// set alt config path
			saveConfigFile($configAddon, $configAddonArray, "", false);							// save addon config file - NO hashTag/prettify
			$configFile = $altConfigPath;														// set Telegraf config file name
			if (isset($_POST['saveConfig'])) $savemsg = gtext("Saved to config file").": <b>".basename($configFile)."</b>";
			else {
				$configArray = parseConfigFile($configFile);									// parse Telegraf config file
				$savemsg = gtext("Loaded config file").": <b>".basename($configFile)."</b>";
			} 
		}
	}

	if (isset($_POST['addSection']) && $_POST['addSection']) {					// addSection: [[TEST.TEXT]]
		if (empty($_POST['sectionName'])) $input_errors[] = sprintf(gtext("%s name must not be empty!"), gtext("Section"));
		else {
			if ($_POST['sectionName'][0] != "[") {								// check sections for correct brackets, '[]' for simple, '[[]]' for table
				if (strPos($_POST['sectionName'], ".") !== false) $_POST['sectionName'] = "[[{$_POST['sectionName']}]]";
				else $_POST['sectionName'] = "[{$_POST['sectionName']}]";
			} 
	        $configArray[$_POST['sectionName']] = [];
			$hashTag = str_replace(["[", "]", ".", "#"], "", $_POST['sectionName']);// create destination to jump to after post
	#		$savemsg .= "addSection: ".$_POST['sectionName'];
		}
	}

	if (isset($_POST['removeSection']) && $_POST['removeSection']) {			// removeSection: [[outputs.influxdb]]
		unset($configArray[$_POST['removeSection']]);
		$savemsg = gtext("Removed section").": ".$_POST['removeSection'];
	}

	if (isset($_POST['addParam']) && $_POST['addParam']) {						// addParam s/n/v: [global_tags] qqqqqqqqqqqqqqqq wwwwwwwwwwwwww
		$nameTag = str_replace(["[", "]", "."], "", $_POST['addParam']);		// nameTag = <input title='$nameTag + addParam' ... />
		if (empty($_POST[$nameTag.'paramName'])) $input_errors[] = sprintf(gtext("%s name must not be empty!"), gtext("Parameter"));
		else {
			$hashTag = $nameTag;													// create destination to jump to after post
	        $configArray[$_POST['addParam']][$_POST[$nameTag.'paramName']] = $_POST[$nameTag.'paramValue'];	// add param to section
	#		$savemsg .= "addParam s/n/v: ".$_POST['addParam']." ".$_POST[$nameTag.'paramName']." ".$_POST[$nameTag.'paramValue'];
		}
	}

	if (isset($_POST['saveParam']) && $_POST['saveParam']) {					// saveParam s/n/v: [[outputs.influxdb]]#urls outputsinfluxdburls ["http://192.168.1.XYZ:8086"]
		$buttonTag = explode("#", $_POST['saveParam']);							// buttonTag[0] = section, buttonTag[1] = paramName
		$hashTag = str_replace(["[", "]", ".", "#"], "", $buttonTag[0]);		// create destination to jump to after post 
		$nameTag = str_replace(["[", "]", ".", "#"], "", $_POST['saveParam']);	// nameTag = <input title='$nameTag + addParam' ... />
        $configArray[$buttonTag[0]][$buttonTag[1]] = $_POST[$nameTag];			// save param to section
#		$savemsg .= "saveParam s/n/v: ".$_POST['saveParam']." ".$nameTag." ".$_POST[$nameTag];
	}

	if (isset($_POST['deleteParam']) && $_POST['deleteParam']) {				// deleteParam s/n/v: [[outputs.influxdb]]#password
		$buttonTag = explode("#", $_POST['deleteParam']);						// buttonTag[0] = section, buttonTag[1] = paramName
		$hashTag = str_replace(["[", "]", ".", "#"], "", $buttonTag[0]);		// create destination to jump to after post
		unset($configArray[$buttonTag[0]][$buttonTag[1]]);
#		$savemsg .= "deleteParam s/n/v: ".$_POST['deleteParam'];
	}

	if (empty($input_errors) && !isset($_POST['loadConfig'])) saveConfigFile($configFile, $configArray, $hashTag);
}

bindtextdomain("xigmanas", $textdomain);
include("fbegin.inc");
bindtextdomain("xigmanas", $textdomain_telegraf);
?>
<form action="telegraf-config.php" method="post" name="iform" id="iform" onsubmit="spinner()">
    <table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr><td class="tabnavtbl">
        <ul id="tabnav">
            <li class="tabinact"><a href="telegraf-gui.php"><span><?=gettext("Telegraf");?></span></a></li>
            <li class="tabact"><a href="telegraf-config.php"><span><?=gettext("Configuration");?></span></a></li>
        </ul>
	</td></tr>
    <tr><td class="tabcont">
        <table width="100%" border="0" cellpadding="6" cellspacing="0">
            <?php														// create table from configuration
				html_titleline(gtext("Config File Selection"));			// file selection title bar
				html_filechooser("altConfigPath", gtext("Config File"), $configFile, gtext("Path to configuration file."), $configFile, true, 60);
				echo "<tr><td colspan='2' style='padding-left:0px;'>";
					html_remark("noteFileSelection", gtext("Note"), 
						gtext("Load/edit/save alternative Telegraf configuration files.")."<br />".
						sprintf(gtext("To use alternative configuration files they must be selected and activated at the %s tab!"), "<b>".gtext("Extensions")." > ".gtext("Telegraf")."</b>")
					);
				echo "</td></tr>";
				echo "<tr><td colspan='2' style='padding-left:0px;'>";
					echo htmlButton("loadConfig", gtext("Load file"), "loadConfig", gtext("Load alternative config file"));
					echo $wSpace;
					echo htmlButton("saveConfig", gtext("Save as"), "saveConfig", gtext("Save as alternative config file"));
				echo "</td></tr>";
				html_separator();
				echo "<tr><td colspan='2' style='padding-left:0px; padding-right:0px;'>";
					if (!empty($input_errors)) print_input_errors($input_errors);
					if (!empty($savemsg)) print_info_box($savemsg);
				echo "</td></tr>";		
				// loop through configuration
				$firstSection = true;									// prevent first html_separator in loop
				if (is_array($configArray) && !empty($configArray))	
					foreach($configArray as $key => $line) {			// traverse array, key = section
						$nameTag = str_replace(["[", "]", "."], "", $key);	// create tag for post jump address and config changes
						if (is_array($line)) {
							if ($firstSection === true) $firstSection = false;
							else html_separator();
							html_titleline(gtext("Section").": ".$key, 2, $nameTag);	// section title bar
							foreach($line as $pName => $pValue)			// traverse params within section, pName = param name, pValue = param value
								html_text($pName, $pName,													// create param entry
									htmlInput($nameTag.$pName, gtext("Parameter Value"), $pValue).$wSpace.
									htmlButton("saveParam", "", $key."#".$pName, gtext("Save"), "", "save").$wSpace.
									htmlButton("deleteParam", "", $key."#".$pName, gtext("Remove"),
										sprintf(gtext("Shall the %s really be removed?"), gtext("parameter")." [{$pName}]"), "delete")
								);
						} else html_text($pName, $pName,				// create single param entry
									htmlInput($nameTag.$pName, gtext("Parameter Value"), $pValue).$wSpace.
									htmlButton("saveParam", "", $key."#".$pName, gtext("Save"), "", "save").$wSpace.
									htmlButton("deleteParam", "", $key."#".$pName, gtext("Remove"),  
										sprintf(gtext("Shall the %s really be removed?"), gtext("parameter")." [{$pName}]"), "delete")
								);

						html_text("addParameter", "<b>".gtext("Add New Parameter")."</b>",	// at the bottom of each section to create a new param
							htmlInput("{$nameTag}paramName", gtext("Parameter Name"), "", $paramNameSize).$wSpaceEqual.
							htmlInput("{$nameTag}paramValue", gtext("Parameter Value")).$wSpace.
							htmlButton("addParam", "", $key, gtext("Add"), "", "add"), true, true
						);
						echo "<tr><td style='padding-left:0px;'>";
						switch($key) {
							case "[global_tags]":
							case "[agent]": echo "<b>".sprintf(gtext("The section %s must not be removed!"), $key)."</b>"; break;
							default: echo htmlButton("removeSection", gtext("Remove"), $key, gtext("Remove the section"), 
								sprintf(gtext("Shall the %s really be removed?"), gtext("section")." {$key}")); 
						}
						echo "</td></tr>";
					}	// end foreach
                html_separator();
                html_titleline(gtext("Section"));											// at the bottom of the page to create new sections
				html_text("addSection", "<b>".gtext("Add New Section")."</b>",
					htmlInput("sectionName", gtext("Section Name"), "", $paramNameSize).$wSpace.
					htmlButton("addSection", "", "addSection", gtext("Add"), "", "add")
				);
				echo "<tr><td colspan='2' style='padding-left:0px;'>";
					html_remark("noteAddSection", gtext("Note"), gtext("Please obey the TOML definitions for section names when adding new sections!"));
				echo "</td></tr>";
            ?>
        </table><?php include("formend.inc");?>
    </td></tr>
    </table>
</form>
<?php include("fend.inc");?>
