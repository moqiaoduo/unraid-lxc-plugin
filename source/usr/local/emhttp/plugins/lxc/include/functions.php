<?php

require_once __DIR__ . '/i18n.php';
require_once 'Container.php';
require_once 'Settings.php';

/**
 * True while running as a task-queue worker (scripts/lxc-task) instead of a
 * web request. The task runner defines LXC_TASK_CLI before it loads this file.
 */
function lxc_task_mode()
{
  return defined('LXC_TASK_CLI') && LXC_TASK_CLI;
}

/**
 * Publish one line to unRAID's task log.
 *
 * A task started through the 7.4 task queue captures everything published on
 * the shared 'plugins' channel, so lines published here show up in the task
 * window and in the task's log file.
 */
function lxc_task_line($text)
{
  foreach (preg_split('/\r\n|\r|\n/', (string)$text) as $line) {
    $line = rtrim($line);
    if ($line === '') {
      continue;
    }
    lxc_task_publish($line);
  }
}

/**
 * Post one message to the shared task channel.
 *
 * WebGUI's publish.php builds a path from $docroot, which a command line
 * process does not define; including it from here dies with a fatal error
 * before a single line can be published. Talking to the same nginx socket
 * directly has no such dependency and posts exactly what publish.php posts.
 */
function lxc_task_publish($message)
{
  if (function_exists('publish')) {
    @publish('plugins', $message);
    return;
  }
  static $socket = null;
  if ($socket === null) {
    // file_exists() rather than is_file(): this is a unix socket, not a plain file.
    $socket = function_exists('curl_init') && file_exists('/var/run/nginx.socket')
      ? '/var/run/nginx.socket'
      : '';
  }
  if ($socket === '') {
    return;
  }
  $ch = @curl_init();
  if ($ch === false) {
    return;
  }
  curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, $socket);
  curl_setopt($ch, CURLOPT_URL, 'http://localhost/pub/plugins?buffer_length=1');
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 5);
  $reply = @curl_exec($ch);
  if (getenv('LXC_TASK_DEBUG')) {
    fwrite(STDERR, sprintf("[publish] http=%s reply=%s error=%s message=%s\n", curl_getinfo($ch, CURLINFO_RESPONSE_CODE), var_export($reply, true), curl_error($ch), $message));
  }
  curl_close($ch);
}

/**
 * Emit one piece of operation output.
 *
 * Web requests keep the HTML the pages already render. A task worker turns the
 * same markup into plain text lines for the task log and remembers that the
 * operation reported an error, so the runner can exit non-zero and let the
 * task queue mark the task failed.
 */
function lxc_out($html)
{
  if (!lxc_task_mode()) {
    echo $html;
    return;
  }
  if (strpos($html, 'lxc-error') !== false) {
    $GLOBALS['lxc_task_failed'] = true;
  }
  $text = str_replace(array('<br/>', '<br />', '<br>'), "\n", (string)$html);
  $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
  if (getenv('LXC_TASK_DEBUG')) {
    fwrite(STDERR, "[out] $text\n");
  }
  lxc_task_line($text);
}

/**
 * Run a command and publish its output line by line.
 *
 * LXC prints progress while it downloads and unpacks, but exec() only returns
 * once the command exits: the task window then stays silent for minutes and
 * shows everything at once. Reading the pipes while they fill keeps the window
 * live and still fills $output for callers that log it afterwards.
 */
function lxc_exec_stream($command, &$output, &$retval)
{
  $output = array();
  $retval = 1;
  $descriptors = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
  $process = @proc_open($command, $descriptors, $pipes);
  if (!is_resource($process)) {
    return false;
  }
  $pending = array(1 => '', 2 => '');
  $open = array(1 => $pipes[1], 2 => $pipes[2]);
  foreach ($open as $pipe) {
    stream_set_blocking($pipe, false);
  }
  while (!empty($open)) {
    $status = proc_get_status($process);
    $read = array_values($open);
    $write = null;
    $except = null;
    if (@stream_select($read, $write, $except, 1) === false) {
      break;
    }
    $received = false;
    foreach ($open as $fd => $pipe) {
      $chunk = @fread($pipe, 8192);
      if ($chunk === false || $chunk === '') {
        if (feof($pipe)) {
          fclose($pipe);
          unset($open[$fd]);
        }
        continue;
      }
      $received = true;
      $pending[$fd] .= $chunk;
      while (($pos = strpos($pending[$fd], "\n")) !== false) {
        $line = rtrim(substr($pending[$fd], 0, $pos), "\r");
        $pending[$fd] = substr($pending[$fd], $pos + 1);
        if (trim($line) === '') {
          continue;
        }
        $output[] = $line;
        lxc_out(htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "<br/>");
      }
    }
    if (!$received && !$status['running']) {
      break;
    }
  }
  foreach ($pending as $rest) {
    $rest = rtrim($rest, "\r\n");
    if (trim($rest) !== '') {
      $output[] = $rest;
      lxc_out(htmlspecialchars($rest, ENT_QUOTES, 'UTF-8') . "<br/>");
    }
  }
  $retval = proc_close($process);
  return true;
}

/**
 * Store a container icon in <default_path>/custom-icons/<name>.png.
 *
 * The add page and the template flow accept an http(s) URL; the edit page
 * prefills the current file path, so an existing local file is accepted too.
 * Returns true when a usable icon is in place.
 */
function lxc_download_icon($name, $icon)
{
  $name = (string)$name;
  $icon = trim((string)$icon);
  if ($name === '' || $icon === '') {
    return false;
  }
  $settings = new Settings();
  $directory = rtrim($settings->default_path, '/') . '/custom-icons';
  if (!is_dir($directory)) {
    @mkdir($directory, 0755, true);
  }
  $target = $directory . '/' . $name . '.png';

  if (preg_match('#^https?://#i', $icon)) {
    exec('wget -q -O ' . escapeshellarg($target) . ' ' . escapeshellarg($icon), $output, $retval);
    if ($retval != 0) {
      @unlink($target);
      exec('logger "LXC: error: download from icon for container ' . $name . ' failed"');
      return false;
    }
    @chmod($target, 0644);
    return true;
  }

  if (is_file($icon)) {
    if (realpath($icon) === realpath($target)) {
      return true;
    }
    if (@copy($icon, $target)) {
      @chmod($target, 0644);
      return true;
    }
    exec('logger "LXC: error: copy icon for container ' . $name . ' failed"');
    return false;
  }

  exec('logger "LXC: error: icon for container ' . $name . ' is neither an http(s) URL nor an existing file"');
  return false;
}

/**
 * Reduce a user supplied container name to the safe subset the LXC tools
 * accept, or to null when nothing usable is left.
 */
function sanitizeContainerName($name)
{
  $clean = basename(trim((string)$name));
  if (preg_match('/^[a-zA-Z0-9_.-]+$/', $clean)) {
    return $clean;
  }
  return null;
}

/**
 * Validation for names that are about to be created. unRAID 7.4's
 * OpenTerminal.php only accepts identifiers that start with a letter or a
 * digit (terminal_identifier()), so a container called ".foo" or "_foo"
 * cannot be opened in the built-in terminal. Existing containers keep the
 * looser sanitizeContainerName() above, otherwise they could no longer be
 * stopped or deleted.
 */
function sanitizeNewContainerName($name)
{
  $clean = sanitizeContainerName($name);
  if ($clean !== null && preg_match('/^[a-zA-Z0-9]/', $clean)) {
    return $clean;
  }
  return null;
}

function getNewMacAddress() {
  return "52:54:00:" .strtoupper(implode(':', str_split(substr(md5(mt_rand()), 0, 6), 2)));
}

function getVariable($path, $option) {
  if (empty($path) || !file_exists($path)) {
    return null;
  }
  $contents = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if ($contents === false) {
    return null;
  }
  foreach ($contents as $line) {
    $line = trim(preg_replace('/\s*=\s*/', '=', $line));
    if (strpos($line, $option . '=') === 0) {
      $parts = explode('=', $line, 2);
      return isset($parts[1]) ? trim(htmlspecialchars(trim($parts[1]), ENT_QUOTES, 'UTF-8')) : '';
    }
  }
  return null;
}

function getContainerStats($container, $option) {
  if (empty($container)) {
    return '';
  }
  exec("lxc-info " . escapeshellarg($container) . " 2>/dev/null", $content);
  foreach ($content as $index => $string) {
    if (strpos($string, $option) !== FALSE) {
      $parts = explode(':', $string, 2);
      return isset($parts[1]) ? trim($parts[1]) : '';
    }
  }
  return '';
}

function getAvailableInterfaces() {
  $interfaces = array();
  exec("ip -o link show 2>/dev/null | awk -F': ' '{print $2}'", $output);
  foreach ($output as $line) {
    $interfaceName = trim($line);
    $interfaceName = explode('@', $interfaceName)[0];
    if (preg_match('/^(virbr|vhost|bond|eth|br)\d\S*/', $interfaceName)) {
      $interfaces[] = $interfaceName;
    }
  }
  $interfaces = array_values(array_unique($interfaces));
  $sortOrder = ['br', 'eth', 'bond', 'vhost', 'virbr'];
  usort($interfaces, function ($a, $b) use ($sortOrder) {
    preg_match('/^([a-zA-Z]+)(\d+)?/', $a, $matchA);
    preg_match('/^([a-zA-Z]+)(\d+)?/', $b, $matchB);
    $prefixA = $matchA[1] ?? $a;
    $prefixB = $matchB[1] ?? $b;
    $numA = isset($matchA[2]) ? (int)$matchA[2] : 0;
    $numB = isset($matchB[2]) ? (int)$matchB[2] : 0;

    $posA = array_search($prefixA, $sortOrder);
    $posB = array_search($prefixB, $sortOrder);
    $posA = ($posA === false) ? 999 : $posA;
    $posB = ($posB === false) ? 999 : $posB;

    if ($posA === $posB) {
      if ($numA === $numB) {
        return strcmp($a, $b);
      }
      return $numA - $numB;
    }
    return $posA - $posB;
  });
  return $interfaces;
}

function getInterfaces() {
  return getAvailableInterfaces();
}

function getActiveContainers() {
  $containers = array();
  exec("lxc-ls --active 2>/dev/null", $output);
  foreach ($output as $line) {
    $containers[] = $line;
  }

  return $containers;
}

function getAllContainers() {
  $containers = shell_exec("lxc-ls 2>/dev/null");
  if (!empty($containers)) {
    $containers = preg_replace('!\s+!', ' ', $containers);
    $containers = explode(" ",trim($containers));

    $allContainers = array();
    foreach ($containers as $container) {
      $allContainers[] = new Container($container);
    }
  } else {
    $allContainers = array();
  }

  return $allContainers;
}

function setVariable($file, $variable, $value) {
  if (empty($file) || !file_exists($file)) {
    return;
  }
  $contents = @file_get_contents($file);
  if ($contents === false) {
    return;
  }

  $newFile = [];
  $lines = explode("\n", $contents);
  $found = false;

  foreach ($lines as $line) {
    if (strpos($line, $variable . '=') === 0 || strpos($line, $variable . ' =') === 0 || $line === $variable) {
      $newFile[] = $variable . "=" . $value;
      $found = true;
    } else {
      $newFile[] = $line;
    }
  }
  if (!$found) {
    $newFile[] = $variable . "=" . $value;
  }

  file_put_contents($file, implode(PHP_EOL, $newFile));
}

/**
 * Drop a config key entirely. setVariable() can only rewrite or append, but an
 * empty value is not the same as an absent key for several lxc.* options
 * (lxc.cgroup2.memory.max="" is invalid where "no limit" is what we want).
 */
function removeVariable($file, $variable) {
  if (empty($file) || !file_exists($file)) {
    return;
  }
  $contents = @file_get_contents($file);
  if ($contents === false) {
    return;
  }

  $newFile = [];
  foreach (explode("\n", $contents) as $line) {
    $trimmed = ltrim($line);
    if (strpos($trimmed, '#') !== 0 && (strpos($trimmed, $variable . '=') === 0 || strpos($trimmed, $variable . ' =') === 0)) {
      continue;
    }
    $newFile[] = $line;
  }

  file_put_contents($file, implode(PHP_EOL, $newFile));
}

function getCpus(){
  exec('cat /sys/devices/system/cpu/*/topology/thread_siblings_list 2>/dev/null | sort -nu', $cpus);
  $allCpus = array();
  $vCpus = array();
  foreach ($cpus as $cpu) {
    $cpu = explode(',', trim($cpu));
    if (!empty($cpu[0]) || $cpu[0] === '0') {
      $allCpus[] = $cpu[0];
      $vCpus[] = $cpu[1] ?? $cpu[0];
    }
  }

  sort($allCpus, SORT_NUMERIC);
  sort($vCpus, SORT_NUMERIC);

  return array('allcpus' => $allCpus, 'vcpus' => $vCpus);
}

function createContainer($name, $description, $distribution, $release, $startcont, $autostart, $mac, $memory = '', $cpus = '', $initcmd = '', $nettype = '', $netlink = '', $icon = '') {
  $settings = new Settings();
  $default_path = rtrim($settings->default_path, '/');
  $pool = explode('/', $default_path)[2] ?? '';

  // Optional resources are written into the container config after the create
  // step, so they get the same validation the edit page applies.
  $memory = trim((string)$memory);
  $cpus = trim((string)$cpus);
  $initcmd = trim((string)$initcmd);
  $nettype = trim((string)$nettype);
  $netlink = trim((string)$netlink);
  if ($memory !== '' && (!ctype_digit($memory) || (int)$memory < 16 || (int)$memory > 4194304)) {
    lxc_out('<p class="lxc-error" style="color:red;">' . lxc_t('ERROR, invalid memory limit!') . '</p>');
    return;
  }
  if ($cpus !== '' && !preg_match('/^[0-9]+(-[0-9]+)?(,[0-9]+(-[0-9]+)?)*$/', $cpus)) {
    lxc_out('<p class="lxc-error" style="color:red;">' . lxc_t('ERROR, invalid CPU list!') . '</p>');
    return;
  }
  if ($nettype !== '' && !in_array($nettype, ['veth', 'none'], true)) {
    lxc_out('<p class="lxc-error" style="color:red;">' . lxc_t('ERROR, invalid network type!') . '</p>');
    return;
  }
  if ($netlink !== '' && !preg_match('/^[a-zA-Z0-9_.-]{1,32}$/', $netlink)) {
    lxc_out('<p class="lxc-error" style="color:red;">' . lxc_t('ERROR, invalid bridge name!') . '</p>');
    return;
  }

  // The task runs in the background, so the form underneath stays usable and
  // can be submitted again before the first container exists.
  if (is_dir($default_path . '/' . $name)) {
    lxc_out('<p class="lxc-error" style="color:red;">' . lxc_tf('ERROR, failed to create container %s<br/>Already exists!', htmlspecialchars($name, ENT_QUOTES, 'UTF-8')) . '</p>');
    exec('logger "LXC: error: Failed to create Container ' . $name . ', container already exists"');
    return;
  }

  if($settings->default_bdevtype == "zfs") {
    $bdev = "--bdev=zfs --zfsroot=" . escapeshellarg($pool . "/zfs_lxccontainers/" . $name);
  } elseif($settings->default_bdevtype == "btrfs") {
    $bdev = "--bdev=btrfs";
  } else {
    $bdev = "--bdev=dir";
  }
  exec('logger "LXC: Creating container ' . $name . '"');
  while (@ ob_end_flush());
  lxc_exec_stream("lxc-create --name " . escapeshellarg($name) . " " . $bdev . " --template download -- --dist " . escapeshellarg($distribution) . " --release " . escapeshellarg($release) . " --arch amd64 2>&1", $output, $retval);
  if ($retval == 1) {
    lxc_out('<p class="lxc-error" style="color:red;">' . lxc_tf('ERROR, failed to create container %s!', htmlspecialchars($name, ENT_QUOTES, 'UTF-8')) . "<br/><br/>");
    exec('logger "LXC: error: Failed to create Container ' . $name . '"');
    foreach ($output as $error) {
      exec('logger "LXC: ' . $error . '"');
    }
    lxc_out('</p>');
  } else {
    if($settings->default_bdevtype == "zfs") {
      exec("zfs set canmount=on " . escapeshellarg($pool . "/zfs_lxccontainers/" . $name . "/" . $name));
      exec("zfs mount " . escapeshellarg($pool . "/zfs_lxccontainers/" . $name . "/" . $name));
    }
    exec('logger "LXC: Container ' . $name . ' created"');
    $container = new Container($name);
    $container->setMac($mac);
    $container->setAutostart(($autostart == "true" || $autostart == "1") ? 1 : 0);
    $container->setMemoryLimit($memory === '' ? '' : ((int)$memory * 1048576));
    $container->setCpuset($cpus);
    $container->setInitCmd($initcmd);
    if ($nettype !== '') {
      $container->setNetworkType($nettype);
    }
    if ($nettype === 'none') {
      // A "none" network has no bridge to point at.
      $container->setNetworkLink('');
    } elseif ($netlink !== '') {
      $container->setNetworkLink($netlink);
    }
    // lxc_exec_stream() already published every line as it arrived.
    if ($startcont == "true" || $startcont == "1") {
      $container->startContainer();
    }
  }
  if (!empty($description) && isset($container)) {
    $container->setDescription($description);
  }
  if (!empty($icon) && isset($container)) {
    lxc_download_icon($name, $icon);
  }
}

function copyContainer($name, $description, $container, $autostart, $mac) {
  $oldContainer = new Container($container);
  $running = $oldContainer->state;
  $oldContainer->stopContainer();
  exec('logger "LXC: Copying container ' . $container . '"');
  exec('lxc-copy -n ' . escapeshellarg($container) . ' -N ' . escapeshellarg($name));
  exec('logger "LXC: Container ' . $container . ' copied to ' . $name . '"');
  $newContainer = new Container($name);
  $newContainer->setMac($mac);
  $newContainer->setAutostart(($autostart == "true" || $autostart == "1") ? 1 : 0);

  if (!empty($description)) {
    $newContainer->setDescription($description);
  }

  if ($running == "RUNNING") {
    $oldContainer->startContainer();
  }
}

function createFromSnapshot($name, $description, $container, $snapshot, $autostart, $mac) {
  exec('logger "LXC: Creating ' . $name . ' from container ' . $container . '-' . $snapshot . '"');
  exec('lxc-snapshot -n ' . escapeshellarg($container) . ' -r ' . escapeshellarg($snapshot) . ' -N ' . escapeshellarg($name));
  exec('logger "LXC: Container ' . $name . ' created from ' . $container . '-' . $snapshot . '"');
  $newContainer = new Container($name);
  $newContainer->setMac($mac);
  $newContainer->setAutostart(($autostart == "true" || $autostart == "1") ? 1 : 0);

  if (!empty($description)) {
    $newContainer->setDescription($description);
  }
}

function createFromBackup($name, $description, $container, $backup, $autostart, $mac) {
  exec('lxc-autobackup --restore --gui-restore=' . escapeshellarg($container . '_' . $backup) . ' --name=' . escapeshellarg($container) . ' --newname=' . escapeshellarg($name));
  $newContainer = new Container($name);
  $newContainer->setMac($mac);
  $newContainer->setAutostart(($autostart == "true" || $autostart == "1") ? 1 : 0);

  if (!empty($description)) {
    $newContainer->setDescription($description);
  }
}

function downloadLXCproducts($url) {
  $filename = "lxcimages";
  $path = '/tmp/lxc';
  $clean_url = trim($url);
  if (!filter_var($clean_url, FILTER_VALIDATE_URL)) {
    return;
  }
  if (!is_dir($path)) {
    mkdir($path, 0755, true);
  }
  $target = $path . '/' . $filename . '.json';
  if (!file_exists($target) || (time() - filemtime($target)) > 3600) {
    if (file_exists($target)) {
      unlink($target);
    }
    shell_exec("wget -q -O " . escapeshellarg($target) . " " . escapeshellarg($clean_url));
  }
}

/**
 * Where imported and hand-written LXC templates live. Kept on the flash drive
 * so a template survives a reboot, unlike /tmp.
 */
function lxc_template_dir() {
  return '/boot/config/plugins/lxc/templates';
}

/**
 * Normalise a template name: drop the .xml suffix, refuse path separators and
 * anything outside the safe set. Returns '' when the caller has to pick another
 * name.
 */
function lxc_template_name($value) {
  $name = basename(trim((string)$value));
  $name = preg_replace('/\.xml$/i', '', $name);

  return ($name !== '' && preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $name)) ? $name : '';
}

function createfromTemplate($name, $description, $repository, $webui, $icon, $startcont, $autostart, $convertbdev, $mac, $supportlink, $donatelink, $memory = '', $cpus = '', $initcmd = '', $nettype = '', $netlink = '') {
  $settings = new Settings();
  $default_path = rtrim($settings->default_path, '/');
  $repositoryurl = parse_url($repository);
  $repositorypath = isset($repositoryurl['path']) ? explode('/', trim($repositoryurl['path'], '/')) : [];

  if (count($repositorypath) < 2) {
    lxc_out('<p class="lxc-error" style="color:red;">' . lxc_t('ERROR, invalid repository URL!') . '</p>');
    return;
  }

  $repoOwner = preg_replace('/[^a-zA-Z0-9_.-]/', '', $repositorypath[0]);
  $repoName = preg_replace('/[^a-zA-Z0-9_.-]/', '', $repositorypath[1]);

  exec('logger "LXC: Creating container ' . $name . ' from repository: ' . $repository . '"');

  if (is_dir($default_path . "/" . $name)) {
    lxc_out('<p class="lxc-error" style="color:red;">' . lxc_tf('ERROR, failed to create container %s<br/>Already exists!', htmlspecialchars($name, ENT_QUOTES, 'UTF-8')) . '</p>');
    exec('logger "LXC: error: Failed to create Container ' . $name . ', container already exists"');
    return;
  } else {
    mkdir($default_path . "/" . $name, 0755, true);
  }

  if (!is_dir($default_path . "/cache/template_cache")) {
    mkdir($default_path . "/cache/template_cache", 0755, true);
  }

  if (!empty($settings->github_user) && !empty($settings->github_token)) {
    $githubauth = "-u " . escapeshellarg($settings->github_user . ":" . $settings->github_token) . " ";
  } else {
    $githubauth = "";
  }

  $githubjson = shell_exec("curl " . $githubauth . "-s " . escapeshellarg("https://api.github.com/repos/" . $repoOwner . "/" . $repoName . "/releases/latest"));
  $githubjson = json_decode($githubjson, true);

  if (isset($githubjson['assets']) && is_array($githubjson['assets'])) {
    $assets = $githubjson['assets'];
    $download_assets = [];
    foreach ($assets as $asset) {
      if (strpos($asset['name'], '.tar.xz') !== false && strpos($asset['name'], '.md5') === false) {
        $download_assets[] = ['filename' => $asset['name'], 'url' => $asset['browser_download_url']];
      }
    }
  } else {
    lxc_out('<p class="lxc-error" style="color:red;">' . lxc_tf('ERROR, failed to create container %s<br/>Found no assets on GitHub, please try again later!', htmlspecialchars($name, ENT_QUOTES, 'UTF-8')) . '</p>');
    exec('logger "LXC: error: Failed to create Container ' . $name . ', found no assets on GitHub, please try again later"');
    rmdir($default_path . "/" . $name);
    return;
  }

  if (empty($download_assets)) {
    lxc_out('<p class="lxc-error" style="color:red;">' . lxc_tf('ERROR, failed to create container %s<br/>Found no archive assets on GitHub!', htmlspecialchars($name, ENT_QUOTES, 'UTF-8')) . '</p>');
    rmdir($default_path . "/" . $name);
    return;
  }

  exec('logger "LXC: Downloading container archive for ' . $name . '"');

  $archivePath = $default_path . '/cache/template_cache/' . basename($download_assets[0]['filename']);
  exec('wget -q -O ' . escapeshellarg($archivePath) . ' ' . escapeshellarg($download_assets[0]['url']), $output, $retval);
  if ($retval == 1) {
    lxc_out('<p class="lxc-error" style="color:red;">' . lxc_tf('ERROR, failed to create container %s<br/>Download failed!', htmlspecialchars($name, ENT_QUOTES, 'UTF-8')) . '</p>');
    exec('logger "LXC: error: Failed to create Container ' . $name . ', download failed"');
    rmdir($default_path . "/" . $name);
    if (file_exists($archivePath)) {
      unlink($archivePath);
    }
    return;
  } else {
    $md5Path = $archivePath . '.md5';
    exec('wget -q -O ' . escapeshellarg($md5Path) . ' ' . escapeshellarg($download_assets[0]['url'] . '.md5'), $output, $retval);
    if ($retval == 1) {
      lxc_out('<p class="lxc-error" style="color:red;">' . lxc_tf('ERROR, failed to create container %s<br/>Download from md5 failed!', htmlspecialchars($name, ENT_QUOTES, 'UTF-8')) . '</p>');
      exec('logger "LXC: error: Failed to create Container ' . $name . ', download from md5 failed"');
      rmdir($default_path . "/" . $name);
      if (file_exists($archivePath)) {
        unlink($archivePath);
      }
      if (file_exists($md5Path)) {
        unlink($md5Path);
      }
      return;
    }
  }

  exec('logger "LXC: Download from container archive for ' . $name . ' successful"');

  $md5file = exec('md5sum ' . escapeshellarg($archivePath) . ' | awk \'{print $1}\'');
  $md5check = exec('cat ' . escapeshellarg($md5Path) . ' | awk \'{print $1}\'');

  if ($md5file != $md5check) {
    lxc_out('<p class="lxc-error" style="color:red;">' . lxc_tf('ERROR, failed to create container %s<br/>MD5 Checksum Error!', htmlspecialchars($name, ENT_QUOTES, 'UTF-8')) . '</p>');
    exec('logger "LXC: error: Failed to create Container ' . $name . ', checksum error"');
    rmdir($default_path . "/" . $name);
    if (file_exists($archivePath)) {
      unlink($archivePath);
    }
    if (file_exists($md5Path)) {
      unlink($md5Path);
    }
    return;
  }

  lxc_out('<p style="color:green;">' . lxc_t('Unpacking the archive') . '<br/><br/>---</p>');
  exec('logger "LXC: Unpacking archive for container ' . $name . '"');

  exec('tar -C ' . escapeshellarg($default_path . '/' . $name) . ' -xf ' . escapeshellarg($archivePath) . ' 2>/dev/null', $output, $retval);
  if ($retval == 1) {
    lxc_out('<p class="lxc-error" style="color:red;">' . lxc_tf('ERROR, failed to create container %s<br/>Extraction failed!', htmlspecialchars($name, ENT_QUOTES, 'UTF-8')) . '</p>');
    exec('logger "LXC: error: Failed to create Container ' . $name . ', extraction failed"');
    rmdir($default_path . "/" . $name);
    if (file_exists($archivePath)) {
      unlink($archivePath);
    }
    if (file_exists($md5Path)) {
      unlink($md5Path);
    }
    return;
  }

  $default_conf = file_exists('/boot/config/plugins/lxc/default.conf') ? file_get_contents('/boot/config/plugins/lxc/default.conf') : '';
  $lxc_config = "# Container specific configuration\n" .
                "lxc.rootfs.path = dir:" . $default_path . "/" . $name . "/rootfs\n" .
                "lxc.uts.name = " . $name . "\n\n" .
                "# Network configuration\n" .
                $default_conf . "\n" .
                "lxc.net.0.hwaddr=00:00:00:00:00:00\n" .
                "lxc.start.auto=0\n";

  file_put_contents($default_path . "/" . $name . "/config", $lxc_config, FILE_APPEND | LOCK_EX);

  $container = new Container($name);
  $container->setMac($mac);
  $container->setAutostart(($autostart == "true" || $autostart == "1") ? 1 : 0);
  $container->setMemoryLimit($memory === '' ? '' : ((int)$memory * 1048576));
  $container->setCpuset($cpus);
  $container->setInitCmd($initcmd);
  if ($nettype !== '') {
    $container->setNetworkType($nettype);
  }
  if ($nettype === 'none') {
    $container->setNetworkLink('');
  } elseif ($netlink !== '') {
    $container->setNetworkLink($netlink);
  }

  if (!empty($description)) {
    $container->setDescription($description);
  }

  if (!empty($webui)) {
    $container->setWebuiurl($webui);
  }

  if (!empty($supportlink)) {
    $container->setSupportlink($supportlink);
  }

  if (!empty($donatelink)) {
    $container->setDonatelink($donatelink);
  }

  if (!empty($icon)) {
    lxc_download_icon($name, $icon);
  }

  exec("sed -i '/lxc\.mount\.entry.*/d' " . escapeshellarg($default_path . "/" . $name . "/config")); 

  if (file_exists($archivePath)) {
    unlink($archivePath);
  }
  if (file_exists($md5Path)) {
    unlink($md5Path);
  }

  if ($convertbdev == "true" || $convertbdev == "1") {
    $bdevtype = "";
    if (preg_match('/\b(zfs)\b/', $settings->default_bdevtype)) {
      exec('lxc-dirtozfs -q ' . escapeshellarg($name), $output, $retval);
      $bdevtype = "ZFS";
    } elseif (preg_match('/\b(btrfs)\b/', $settings->default_bdevtype)) {
      exec('lxc-dirtobtrfs -q ' . escapeshellarg($name), $output, $retval);
      $bdevtype = "BTRFS";
    }

    lxc_out('<p style="color:green;">' . lxc_tf('Converting container %s to %s', htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), $bdevtype) . '</p>');
    exec('logger "LXC: Converting Container ' . $name . ' to ' . $bdevtype . '"');

    if ($retval == 1) {
      lxc_out('<p class="lxc-error" style="color:red;">' . lxc_tf('ERROR, Conversion from container %s<br/> to %s failed!', htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), $bdevtype) . '<br/><br/>---</p>');
      exec('logger "LXC: error: Converstion from Container ' . $name . ' to ' . $bdevtype . ' failed"');
      rmdir($default_path . "/" . $name);
      if (file_exists($archivePath)) {
        unlink($archivePath);
      }
    } else {
      lxc_out('<p style="color:green;">' . lxc_tf('Conversion to %s from container %s done', $bdevtype, htmlspecialchars($name, ENT_QUOTES, 'UTF-8')) . '<br/><br/>---</p>');
      exec('logger "LXC: Conversion to ' . $bdevtype . ' from Container ' . $name . ' done"');
    }
  }


  lxc_out('<p style="color:green;">' . lxc_tf('You just created a container from the repository: %1$s<br/>Please check out the  <a href="%2$s" target="_blank" rel="noopener noreferrer">README</a> from the repository for further infromation!', htmlspecialchars($repository, ENT_QUOTES, 'UTF-8'), htmlspecialchars($repository, ENT_QUOTES, 'UTF-8')) . '</p>');
  exec('logger "LXC: Container ' . $name . ' created"');

  if ($startcont == "true" || $startcont == "1") {
    $container->startContainer();
  }
}

/**
 * Render a template's <Overview> as the small HTML block the add page shows.
 *
 * Community Applications templates keep HTML bracketed as [b]…[/b] so the XML
 * stays valid; turn that back into tags, drop any tag that is left, then apply
 * the handful of markdown conventions those templates actually use (bold, code,
 * links and bullet lists).
 */
function lxc_template_overview_html($text)
{
  $text = str_replace(["\r\n", "\r"], "\n", (string)$text);
  $text = str_replace(['[', ']'], ['<', '>'], $text);
  $text = strip_tags($text);
  $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

  $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
  $text = preg_replace('/`([^`]+)`/s', '<code>$1</code>', $text);
  $text = preg_replace(
    '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
    '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>',
    $text
  );

  // Turn runs of "- item" / "* item" lines into a real list; the remaining
  // newlines become line breaks.
  $text = preg_replace_callback(
    '/(?:^|\n)((?:[ \t]*[-*][ \t]+[^\n]*(?:\n|$))+)/',
    function ($matches) {
      $items = preg_split('/\n/', trim($matches[1]));
      $html = '<ul>';
      foreach ($items as $item) {
        $html .= '<li>' . preg_replace('/^[ \t]*[-*][ \t]+/', '', $item) . '</li>';
      }
      return "\n" . $html . '</ul>' . "\n";
    },
    $text
  );

  return trim(nl2br($text));
}
