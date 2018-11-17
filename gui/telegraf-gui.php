<?php
/*
	telegraf-gui.php

	WebGUI wrapper for the XigmaNAS "Telegraf" add-on created by JoseMR.
	(https://www.xigmanas.com/forums/viewtopic.php?f=71&t=14127)

	Copyright (c) 2016 Andreas Schmidhuber
	All rights reserved.

	Portions of NAS4Free (http://www.nas4free.org).
	Copyright (c) 2012-2016 The NAS4Free Project <info@nas4free.org>.
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

	The views and conclusions contained in the software and documentation are those
	of the authors and should not be interpreted as representing official policies,
	either expressed or implied, of the NAS4Free Project.
*/
require("auth.inc");
require("guiconfig.inc");

$application = "Telegraf";
$pgtitle = array(gtext("Extensions"), "Telegraf");

// For NAS4Free 10.x versions.
$return_val = mwexec("/bin/cat /etc/prd.version | cut -d'.' -f1 | /usr/bin/grep '10'", true);
if ($return_val == 0) {
	if (is_array($config['rc']['postinit'] ) && is_array( $config['rc']['postinit']['cmd'] ) ) {
		for ($i = 0; $i < count($config['rc']['postinit']['cmd']);) { if (preg_match('/telegraf-init/', $config['rc']['postinit']['cmd'][$i])) break; ++$i; }
	}
}

// Initialize some variables.
//$rootfolder = dirname($config['rc']['postinit']['cmd'][$i]);
$pidfile = "/var/run/telegraf.pid";
$confdir = "/var/etc/telegraf_conf";
$cwdir = exec("/usr/bin/grep 'INSTALL_DIR=' {$confdir}/conf/telegraf_config | cut -d'\"' -f2");
$rootfolder = $cwdir;
$configfile = "{$rootfolder}/conf/telegraf_config";
$versionfile = "{$rootfolder}/version";
$date = strftime('%c');
$logfile = "{$rootfolder}/log/telegraf_ext.log";
$logevent = "{$rootfolder}/log/telegraf_last_event.log";

$prdname = "telegraf";


if ($rootfolder == "") $input_errors[] = gtext("Extension installed with fault");
else {
// Initialize locales.
	$textdomain = "/usr/local/share/locale";
	$textdomain_telegraf = "/usr/local/share/locale-telegraf";
	if (!is_link($textdomain_telegraf)) { mwexec("ln -s {$rootfolder}/locale-telegraf {$textdomain_telegraf}", true); }
	bindtextdomain("xigmanas", $textdomain_telegraf);
}
if (is_file("{$rootfolder}/postinit")) unlink("{$rootfolder}/postinit");

// Set default agentconf directory.
if (1 == mwexec("/bin/cat {$configfile} | /usr/bin/grep 'TELEGRAF_CONFIG='")) {
	if (is_file("{$configfile}")) exec("/usr/sbin/sysrc -f {$configfile} TELEGRAF_CONFIG={$rootfolder}/etc/telegraf.conf");
}
$agentconf_path = exec("/bin/cat {$configfile} | /usr/bin/grep 'TELEGRAF_CONFIG=' | cut -d'\"' -f2");

// Retrieve IP@.
$ipaddr = get_ipaddr($config['interfaces']['lan']['if']);
$url = htmlspecialchars("http://{$ipaddr}:32400/web");
$ipurl = "<a href='{$url}' target='_blank'>{$url}</a>";

if ($_POST) {
	if (isset($_POST['start']) && $_POST['start']) {
		$return_val = mwexec("{$rootfolder}/telegraf-init -s", true);
		if ($return_val == 0) {
			$savemsg .= gtext("Telegraf started successfully.");
			exec("echo '{$date}: {$application} successfully started' >> {$logfile}");
		}
		else {
			$input_errors[] = gtext("Telegraf startup failed.");
			exec("echo '{$date}: {$application} startup failed' >> {$logfile}");
		}
	}

	if (isset($_POST['stop']) && $_POST['stop']) {
		$return_val = mwexec("{$rootfolder}/telegraf-init -p", true);
		if ($return_val == 0) {
			$savemsg .= gtext("Telegraf stopped successfully.");
			exec("echo '{$date}: {$application} successfully stopped' >> {$logfile}");
		}
		else {
			$input_errors[] = gtext("Telegraf stop failed.");
			exec("echo '{$date}: {$application} stop failed' >> {$logfile}");
		}
	}

	if (isset($_POST['restart']) && $_POST['restart']) {
		$return_val = mwexec("{$rootfolder}/telegraf-init -r", true);
		if ($return_val == 0) {
			$savemsg .= gtext("Telegraf restarted successfully.");
			exec("echo '{$date}: {$application} successfully restarted' >> {$logfile}");
		}
		else {
			$input_errors[] = gtext("Telegraf restart failed.");
			exec("echo '{$date}: {$application} restart failed' >> {$logfile}");
		}
	}

	if(isset($_POST['upgrade']) && $_POST['upgrade']):
		$cmd = sprintf('%1$s/telegraf-init -u > %2$s',$rootfolder,$logevent);
		$return_val = 0;
		$output = [];
		exec($cmd,$output,$return_val);
		if($return_val == 0):
			ob_start();
			include("{$logevent}");
			$ausgabe = ob_get_contents();
			ob_end_clean(); 
			$savemsg .= str_replace("\n", "<br />", $ausgabe)."<br />";
		else:
			$input_errors[] = gtext('An error has occurred during upgrade process.');
			$cmd = sprintf('echo %s: %s An error has occurred during upgrade process. >> %s',$date,$application,$logfile);
			exec($cmd);
		endif;
	endif;

	// Remove only extension related files during cleanup.
	if (isset($_POST['uninstall']) && $_POST['uninstall']) {
		bindtextdomain("xigmanas", $textdomain);
		if (is_link($textdomain_telegraf)) mwexec("rm -f {$textdomain_telegraf}", true);
		if (is_dir($confdir)) mwexec("rm -Rf {$confdir}", true);
		mwexec("rm /usr/local/www/telegraf-gui.php && rm -R /usr/local/www/ext/telegraf-gui", true);
		mwexec("{$rootfolder}/telegraf-init -p && rm -f {$pidfile}", true);
		//$uninstall_cmd = "rm -Rf '{$rootfolder}/etc' '{$rootfolder}/conf' '{$rootfolder}/gui' '{$rootfolder}/locale-telegraf' '{$rootfolder}/log' '{$rootfolder}/system' '{$rootfolder}/telegraf-init' '{$rootfolder}/README.md' '{$rootfolder}/release_notes' '{$rootfolder}/version'";
		$uninstall_cmd = "echo 'y' | {$rootfolder}/telegraf-init -R";
		mwexec($uninstall_cmd, true);
		if (is_link("/usr/local/share/{$prdname}")) mwexec("rm /usr/local/share/{$prdname}", true);
		if (is_link("/var/cache/pkg")) mwexec("rm /var/cache/pkg", true);
		if (is_link("/var/db/pkg")) mwexec("rm /var/db/pkg && mkdir /var/db/pkg", true);
		
		// Remove postinit cmd in NAS4Free 10.x versions.
		$return_val = mwexec("/bin/cat /etc/prd.version | cut -d'.' -f1 | /usr/bin/grep '10'", true);
			if ($return_val == 0) {
				if (is_array($config['rc']['postinit']) && is_array($config['rc']['postinit']['cmd'])) {
					for ($i = 0; $i < count($config['rc']['postinit']['cmd']);) {
					if (preg_match('/telegraf-init/', $config['rc']['postinit']['cmd'][$i])) { unset($config['rc']['postinit']['cmd'][$i]); }
					++$i;
				}
			}
			write_config();
		}

		// Remove postinit cmd in NAS4Free later versions.
		if (is_array($config['rc']) && is_array($config['rc']['param'])) {
			$postinit_cmd = "{$rootfolder}/telegraf-init";
			$value = $postinit_cmd;
			$sphere_array = &$config['rc']['param'];
			$updateconfigfile = false;
		if (false !== ($index = array_search_ex($value, $sphere_array, 'value'))) {
			unset($sphere_array[$index]);
			$updateconfigfile = true;
		}
		if ($updateconfigfile) {
			write_config();
			$updateconfigfile = false;
		}
	}
	header("Location:index.php");
}

	if (isset($_POST['save']) && $_POST['save']) {
		// Ensure to have NO whitespace & trailing slash.
		$agentconf_path = rtrim(trim($_POST['agentconf_path']),'/');
		if ("{$agentconf_path}" == "") $agentconf_path = "{$rootfolder}/etc";
			else exec("/usr/sbin/sysrc -f {$configfile} TELEGRAF_CONFIG={$agentconf_path}");
		if (isset($_POST['enable'])) { 
			exec("/usr/sbin/sysrc -f {$configfile} EXT_ENABLE=YES");
			mwexec("{$rootfolder}/telegraf-init", true);
			exec("echo '{$date}: Extension settings saved and enabled' >> {$logfile}");
		}
		else {
			exec("/usr/sbin/sysrc -f {$configfile} EXT_ENABLE=NO");
			$return_val = mwexec("{$rootfolder}/telegraf-init -p", true);
			if ($return_val == 0) {
				$savemsg .= gtext("Telegraf stopped successfully.");
				exec("echo '{$date}: Extension settings saved and disabled' >> {$logfile}");
			}
			else {
				$input_errors[] = gtext("Telegraf stop failed.");
				exec("echo '{$date}: {$application} stop failed' >> {$logfile}");
			}
		}
	}
}

// Update some variables.
$telegrafenable = exec("/bin/cat {$configfile} | /usr/bin/grep 'EXT_ENABLE=' | cut -d'\"' -f2");
$agentconf_path = exec("/bin/cat {$configfile} | /usr/bin/grep 'TELEGRAF_CONFIG=' | cut -d'\"' -f2");

function get_version_telegraf() {
	global $prdname;
	exec('/usr/local/sbin/pkg info -I {$prdname} || echo "$(/usr/local/bin/telegraf --version) Time-series data collection"', $result);
	return ($result[0]);
}

function get_version_ext() {
	global $versionfile;
	exec("/bin/cat {$versionfile}", $result);
	return ($result[0]);
}

function get_process_info() {
	global $pidfile;
	if (exec("/bin/ps acx | /usr/bin/grep -f {$pidfile}")) { $state = '<a style=" background-color: #00ff00; ">&nbsp;&nbsp;<b>'.gtext("running").'</b>&nbsp;&nbsp;</a>'; }
	else { $state = '<a style=" background-color: #ff0000; ">&nbsp;&nbsp;<b>'.gtext("stopped").'</b>&nbsp;&nbsp;</a>'; }
	return ($state);
}

function get_process_pid() {
	global $pidfile;
	exec("/bin/cat {$pidfile}", $state); 
	return ($state[0]);
}

if (is_ajax()) {
	$getinfo['info'] = get_process_info();
	$getinfo['pid'] = get_process_pid();
	$getinfo['telegraf'] = get_version_telegraf();
	$getinfo['ext'] = get_version_ext();
	render_ajax($getinfo);
}

bindtextdomain("xigmanas", $textdomain);
include("fbegin.inc");
bindtextdomain("xigmanas", $textdomain_telegraf);
?>
<script type="text/javascript">//<![CDATA[
$(document).ready(function(){
	var gui = new GUI;
	gui.recall(0, 2000, 'telegraf-gui.php', null, function(data) {
		$('#getinfo').html(data.info);
		$('#getinfo_pid').html(data.pid);
		$('#getinfo_telegraf').html(data.telegraf);
		$('#getinfo_ext').html(data.ext);
	});
});
//]]>
</script>
<!-- The Spinner Elements -->
<script src="js/spin.min.js"></script>
<!-- use: onsubmit="spinner()" within the form tag -->
<script type="text/javascript">
<!--
function enable_change(enable_change) {
	var endis = !(document.iform.enable.checked || enable_change);
	document.iform.start.disabled = endis;
	document.iform.stop.disabled = endis;
	document.iform.restart.disabled = endis;
	document.iform.upgrade.disabled = endis;
	document.iform.agentconf.disabled = endis;
	document.iform.agentconf_path.disabled = endis;
	document.iform.agentconf_pathbrowsebtn.disabled = endis;
}
//-->
</script>
<form action="telegraf-gui.php" method="post" name="iform" id="iform" onsubmit="spinner()">
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr><td class="tabcont">
			<?php if (!empty($input_errors)) print_input_errors($input_errors);?>
			<?php if (!empty($savemsg)) print_info_box($savemsg);?>
			<table width="100%" border="0" cellpadding="6" cellspacing="0">
				<?php html_titleline_checkbox("enable", gtext("Telegraf"), $telegrafenable == "YES", gtext("Enable"));?>
				<?php html_text("installation_directory", gtext("Installation directory"), sprintf(gtext("The extension is installed in %s"), $rootfolder));?>
				<tr>
					<td class="vncellt"><?=gtext("Telegraf version");?></td>
					<td class="vtable"><span name="getinfo_telegraf" id="getinfo_telegraf"><?=get_version_telegraf()?></span></td>
				</tr>
				<tr>
					<td class="vncellt"><?=gtext("Extension version");?></td>
					<td class="vtable"><span name="getinfo_ext" id="getinfo_ext"><?=get_version_ext()?></span></td>
				</tr>
				<tr>
					<td class="vncellt"><?=gtext("Status");?></td>
					<td class="vtable"><span name="getinfo" id="getinfo"><?=get_process_info()?></span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;PID:&nbsp;<span name="getinfo_pid" id="getinfo_pid"><?=get_process_pid()?></span></td>
				</tr>
				<?php html_filechooser("agentconf_path", gtext("Telegraf config file"), $agentconf_path, gtext("Path to telegraf.conf agent configuration file."), $agentconf_path, true, 60);?>
			</table>
			<div id="submit">
				<input id="save" name="save" type="submit" class="formbtn" title="<?=gtext("Save settings");?>" value="<?=gtext("Save");?>"/>
				<input name="start" type="submit" class="formbtn" title="<?=gtext("Start Telegraf");?>" value="<?=gtext("Start");?>" />
				<input name="stop" type="submit" class="formbtn" title="<?=gtext("Stop Telegraf");?>" value="<?=gtext("Stop");?>" />
				<input name="restart" type="submit" class="formbtn" title="<?=gtext("Restart Telegraf");?>" value="<?=gtext("Restart");?>" />
				<input name="upgrade" type="submit" class="formbtn" title="<?=gtext("Upgrade Extension and Telegraf Packages");?>" value="<?=gtext("Upgrade");?>" />
			</div>
			<div id="remarks">
				<?php html_remark("note", gtext("Note"), sprintf(gtext("Removing Telegraf extension will preserve the telegaf.conf file(s).")));?>
			</div>
			
			<div id="submit1">
				<input name="uninstall" type="submit" class="formbtn" title="<?=gtext("Uninstall Extension and Telegraf from the system");?>" value="<?=gtext("Uninstall");?>" onclick="return confirm('<?=gtext("Telegraf Extension and packages will be completely removed, ready to proceed?");?>')" />
			</div>
		</td></tr>
	</table>
	<?php include("formend.inc");?>
</form>
<script type="text/javascript">
<!--
enable_change(false);
//-->
</script>
<?php include("fend.inc");?>
