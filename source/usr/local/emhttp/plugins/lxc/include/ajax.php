<?php
require_once 'functions.php';
require_once 'Settings.php';
require_once 'Container.php';

if (isset($_POST['lxc'])) {
  $settings = new Settings();
  $action = $_POST['action'] ?? '';
  switch ($action) {
    case 'startCONT':
      $cont = sanitizeContainerName($_POST['container'] ?? '');
      if ($cont !== null) {
        $container = new Container($cont);
        $container->startContainer();
      }
      break;
    case 'stopCONT':
      $cont = sanitizeContainerName($_POST['container'] ?? '');
      if ($cont !== null) {
        $container = new Container($cont);
        $container->stopContainer();
      }
      break;
    case 'startALLCONT':
      $allContainers = getAllContainers();
      foreach ($allContainers as $cont) {
        if ($cont->state == "STOPPED" || $cont->state == "FROZEN") {
          $container = new Container($cont->name);
          $container->startContainer();
        }
      }
      break;
    case 'stopALLCONT':
      $allContainers = getAllContainers();
      foreach ($allContainers as $cont) {
        if ($cont->state == "RUNNING" || $cont->state == "FROZEN") {
          $container = new Container($cont->name);
          $container->stopContainer();
        }
      }
      break;
    case 'restartCONT':
      $cont = sanitizeContainerName($_POST['container'] ?? '');
      if ($cont !== null) {
        $container = new Container($cont);
        $container->restartContainer();
      }
      break;
    case 'freezeCONT':
      $cont = sanitizeContainerName($_POST['container'] ?? '');
      if ($cont !== null) {
        $container = new Container($cont);
        $container->freezeContainer();
      }
      break;
    case 'freezeALLCONT':
      $allContainers = getAllContainers();
      foreach ($allContainers as $cont) {
        if ($cont->state == "RUNNING") {
          $container = new Container($cont->name);
          $container->freezeContainer();
        }
      }
      break;
    case 'unfreezeCONT':
      $cont = sanitizeContainerName($_POST['container'] ?? '');
      if ($cont !== null) {
        $container = new Container($cont);
        $container->unfreezeContainer();
      }
      break;
    case 'unfreezeALLCONT':
      $allContainers = getAllContainers();
      foreach ($allContainers as $cont) {
        if ($cont->state == "FROZEN") {
          $container = new Container($cont->name);
          $container->unfreezeContainer();
        }
      }
      break;
    case 'killCONT':
      $cont = sanitizeContainerName($_POST['container'] ?? '');
      if ($cont !== null) {
        $container = new Container($cont);
        $container->killContainer();
      }
      break;
    case 'autostart':
      $cont = sanitizeContainerName($_POST['container'] ?? '');
      if ($cont !== null) {
        $container = new Container($cont);
        $autostart = (isset($_POST['autostart']) && ($_POST['autostart'] === 'true' || $_POST['autostart'] === '1' || $_POST['autostart'] === 1)) ? 1 : 0;
        $container->setAutostart($autostart);
      }
      break;
    case 'destroyCONT':
      $cont = sanitizeContainerName($_POST['container'] ?? '');
      if ($cont !== null) {
        $container = new Container($cont);
        $container->destroyContainer();
      }
      break;
    case 'snapshotCONT':
      $cont = sanitizeContainerName($_POST['container'] ?? '');
      if ($cont !== null) {
        $container = new Container($cont);
        $container->createSnapshot();
      }
      break;
    case 'deleteSNAP':
      $cont = sanitizeContainerName($_POST['container'] ?? '');
      $snapshot = basename(trim((string)($_POST['snapshot'] ?? '')));
      if ($cont !== null && preg_match('/^[a-zA-Z0-9_.-]+$/', $snapshot)) {
        $container = new Container($cont);
        $container->deleteSnapshot($snapshot);
      }
      break;
    case 'backupCONT':
      $cont = sanitizeContainerName($_POST['container'] ?? '');
      if ($cont !== null) {
        $container = new Container($cont);
        $container->createBackup();
      }
      break;
    case 'deleteBACKUP':
      $cont = sanitizeContainerName($_POST['container'] ?? '');
      $backup = basename(trim((string)($_POST['backup'] ?? '')));
      if ($cont !== null && preg_match('/^[a-zA-Z0-9_.-]+$/', $backup)) {
        $container = new Container($cont);
        $container->deleteBackup($backup);
      }
      break;
    case 'createCONT':
      $name = sanitizeNewContainerName($_POST['name'] ?? '');
      if ($name !== null) {
        createContainer($name, $_POST['description'] ?? '', $_POST['distribution'] ?? '', $_POST['release'] ?? '', $_POST['startcont'] ?? '', $_POST['autostart'] ?? '', $_POST['mac'] ?? '', $_POST['memory'] ?? '', $_POST['cpus'] ?? '', $_POST['initcmd'] ?? '', $_POST['nettype'] ?? '', $_POST['netlink'] ?? '', $_POST['icon'] ?? '');
      }
      break;
    case 'copyCONT':
      $name = sanitizeNewContainerName($_POST['name'] ?? '');
      $source = sanitizeContainerName($_POST['container'] ?? '');
      if ($name !== null && $source !== null) {
        copyContainer($name, $_POST['description'] ?? '', $source, $_POST['autostart'] ?? '', $_POST['mac'] ?? '');
      }
      break;
    case 'showConfig':
      $cont = sanitizeContainerName($_POST['container'] ?? '');
      if ($cont !== null) {
        $container = new Container($cont);
        $container->showConfig();
      }
      break;
    case 'saveConfig':
      $updatedConfig = trim((string)($_POST['updatedConfig'] ?? ''));
      $container = sanitizeContainerName($_POST['container'] ?? '');
      if ($container === null) {
        break;
      }
      $containerObj = new Container($container);
      if ($containerObj->state == "RUNNING") {
        // LXC reads the configuration only when the container starts, so the
        // raw editor stays read-only while it runs - same rule as the VMs.
        http_response_code(409);
        lxc_out('<p class="lxc-error" style="color:red;">' . lxc_t('The container is running. Stop it before editing the configuration.') . '</p>');
        break;
      }
      $containerConfig = rtrim($settings->default_path, '/') . "/" . $container . "/config";
      if (file_exists(dirname($containerConfig))) {
        file_put_contents($containerConfig, $updatedConfig);
      }
      break;
    case 'saveSettings':
      // GUI side of the edit page: only the values the plugin owns. Anything
      // LXC parses itself is left to saveConfig above.
      header('Content-Type: application/json');
      $cont = sanitizeContainerName($_POST['container'] ?? '');
      if ($cont === null) {
        echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, invalid container name!')]);
        break;
      }
      $containerObj = new Container($cont);
      if (!file_exists($containerObj->config)) {
        echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, container not found!')]);
        break;
      }
      $mac = trim((string)($_POST['mac'] ?? ''));
      if ($mac !== '' && !preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $mac)) {
        echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, invalid MAC address!')]);
        break;
      }
      $single = function ($key) {
        return trim(str_replace(["\r", "\n"], ' ', (string)($_POST[$key] ?? '')));
      };
      $memory = trim((string)($_POST['memory'] ?? ''));
      if ($memory !== '' && (!ctype_digit($memory) || (int)$memory < 16 || (int)$memory > 4194304)) {
        echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, invalid memory limit!')]);
        break;
      }
      $cpus = trim((string)($_POST['cpus'] ?? ''));
      if ($cpus !== '' && !preg_match('/^[0-9]+(-[0-9]+)?(,[0-9]+(-[0-9]+)?)*$/', $cpus)) {
        echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, invalid CPU list!')]);
        break;
      }
      $nettype = trim((string)($_POST['nettype'] ?? ''));
      if ($nettype !== '' && !in_array($nettype, ['veth', 'none'], true)) {
        echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, invalid network type!')]);
        break;
      }
      $netlink = trim((string)($_POST['netlink'] ?? ''));
      if ($netlink !== '' && !preg_match('/^[a-zA-Z0-9_.-]{1,32}$/', $netlink)) {
        echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, invalid bridge name!')]);
        break;
      }
      $running = $containerObj->state === 'RUNNING';
      $containerObj->setDescription($single('description'));
      $containerObj->setAutostart((string)($_POST['autostart'] ?? '0') === '1' ? 1 : 0);
      $containerObj->setWebuiurl($single('webui'));

      // An empty icon field means "use the distribution icon", which is the
      // absence of custom-icons/<name>.png; a filled one replaces it.
      $icon = $single('icon');
      $iconTarget = rtrim($settings->default_path, '/') . '/custom-icons/' . $cont . '.png';
      if ($icon === '') {
        if (is_file($iconTarget)) {
          @unlink($iconTarget);
        }
      } elseif (realpath($icon) !== realpath($iconTarget) && !lxc_download_icon($cont, $icon)) {
        echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, failed to download the icon!')]);
        break;
      }

      if (!$running) {
        // The remaining values are consumed by lxc-start, so they may only be
        // changed while the container is stopped.
        if ($mac !== '') {
          $containerObj->setMac(strtoupper($mac));
        }
        $containerObj->setMemoryLimit($memory === '' ? '' : ((int)$memory * 1048576));
        $containerObj->setCpuset($cpus);
        $containerObj->setInitCmd($single('initcmd'));
        if ($nettype !== '') {
          $containerObj->setNetworkType($nettype);
        }
        if ($nettype === 'none') {
          // A "none" network has no bridge to point at.
          $containerObj->setNetworkLink('');
        } elseif ($netlink !== '') {
          $containerObj->setNetworkLink($netlink);
        }
      }
      echo json_encode(['ok' => true, 'restarted' => false]);
      break;
    case 'fromSnapshot':
      $name = sanitizeNewContainerName($_POST['name'] ?? '');
      $source = sanitizeContainerName($_POST['container'] ?? '');
      $snapshot = basename(trim((string)($_POST['snapshot'] ?? '')));
      if ($name !== null && $source !== null && preg_match('/^[a-zA-Z0-9_.-]+$/', $snapshot)) {
        createFromSnapshot($name, $_POST['description'] ?? '', $source, $snapshot, $_POST['autostart'] ?? '', $_POST['mac'] ?? '');
      }
      break;
    case 'fromBackup':
      $name = sanitizeNewContainerName($_POST['name'] ?? '');
      $source = sanitizeContainerName($_POST['container'] ?? '');
      $backup = basename(trim((string)($_POST['backup'] ?? '')));
      if ($name !== null && $source !== null && preg_match('/^[a-zA-Z0-9_.-]+$/', $backup)) {
        createFromBackup($name, $_POST['description'] ?? '', $source, $backup, $_POST['autostart'] ?? '', $_POST['mac'] ?? '');
      }
      break;
    case 'setDescription':
      $cont = sanitizeContainerName($_POST['container'] ?? '');
      if ($cont !== null) {
        $container = new Container($cont);
        $container->setDescription($_POST['description'] ?? '');
      }
      break;
    case 'delDescription':
      $cont = sanitizeContainerName($_POST['container'] ?? '');
      if ($cont !== null) {
        $container = new Container($cont);
        $container->delDescription();
      }
      break;
    case 'setWebUIURL':
      $cont = sanitizeContainerName($_POST['container'] ?? '');
      if ($cont !== null) {
        $container = new Container($cont);
        $container->setWebuiurl($_POST['webuiurl'] ?? '');
      }
      break;
    case 'delWebUIURL':
      $cont = sanitizeContainerName($_POST['container'] ?? '');
      if ($cont !== null) {
        $container = new Container($cont);
        $container->delWebuiurl();
      }
      break;
    case 'createTEMPLATE':
      $name = sanitizeNewContainerName($_POST['name'] ?? '');
      if ($name !== null) {
        createfromTemplate($name, $_POST['description'] ?? '', $_POST['repository'] ?? '', $_POST['webui'] ?? '', $_POST['icon'] ?? '', $_POST['startcont'] ?? '', $_POST['autostart'] ?? '', $_POST['convertbdev'] ?? '', $_POST['mac'] ?? '', $_POST['supportlink'] ?? '', $_POST['donatelink'] ?? '', $_POST['memory'] ?? '', $_POST['cpus'] ?? '', $_POST['initcmd'] ?? '', $_POST['nettype'] ?? '', $_POST['netlink'] ?? '');
      }
      break;
    case 'importTemplate':
      // Imported templates live in the plugin directory on the flash drive, so
      // they survive a reboot and can be picked again from the add page.
      header('Content-Type: application/json');
      $source = $_POST['source'] ?? '';
      $content = '';
      if ($source === 'text') {
        $content = (string)($_POST['content'] ?? '');
      } elseif ($source === 'url') {
        $url = trim((string)($_POST['url'] ?? ''));
        $parts = $url === '' ? false : parse_url($url);
        if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
          echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, the template URL must be an https:// address')]);
          break;
        }
        $tmp = tempnam('/tmp', 'lxc-template-');
        exec('curl -fsSL --max-time 20 --max-filesize 524288 -o ' . escapeshellarg($tmp) . ' ' . escapeshellarg($url) . ' 2>&1', $curlOutput, $curlStatus);
        if ($curlStatus !== 0 || !is_file($tmp) || filesize($tmp) === 0) {
          @unlink($tmp);
          echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, failed to download the template')]);
          break;
        }
        $content = (string)file_get_contents($tmp);
        @unlink($tmp);
      } else {
        echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, unknown import source')]);
        break;
      }
      if ($content === '') {
        echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, the template is empty')]);
        break;
      }
      // A file picked in the browser may still carry a UTF-8 BOM.
      $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
      if (strlen($content) > 524288) {
        echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, the template is too large')]);
        break;
      }
      $imported = @simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NONET);
      if ($imported === false || $imported->getName() !== 'LXC') {
        echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, the file is not a valid LXC template')]);
        break;
      }
      // Fall back to the <Name> element, then to a timestamped name, so the
      // caller does not have to invent one.
      $templateName = lxc_template_name($_POST['name'] ?? '');
      if ($templateName === '') {
        $templateName = lxc_template_name((string)$imported->Name);
      }
      if ($templateName === '') {
        $templateName = 'template-' . date('Ymd-His');
      }
      $templateDir = lxc_template_dir();
      if (!is_dir($templateDir)) {
        @mkdir($templateDir, 0755, true);
      }
      $templateFile = $templateDir . '/' . $templateName . '.xml';
      if (file_put_contents($templateFile, $content) === false) {
        echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, failed to save the template')]);
        break;
      }
      @chmod($templateFile, 0644);
      echo json_encode(['ok' => true, 'name' => $templateName]);
      break;
    case 'saveTemplate':
      // Save the text the template editor shows, keeping the same file name.
      header('Content-Type: application/json');
      $templateName = lxc_template_name($_POST['name'] ?? '');
      $content = (string)($_POST['content'] ?? '');
      if ($templateName === '') {
        echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, invalid template name!')]);
        break;
      }
      $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
      $saved = @simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NONET);
      if ($saved === false || $saved->getName() !== 'LXC') {
        echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, the file is not a valid LXC template')]);
        break;
      }
      $templateDir = lxc_template_dir();
      if (!is_dir($templateDir)) {
        @mkdir($templateDir, 0755, true);
      }
      $templateFile = $templateDir . '/' . $templateName . '.xml';
      if (file_put_contents($templateFile, $content) === false) {
        echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, failed to save the template')]);
        break;
      }
      @chmod($templateFile, 0644);
      echo json_encode(['ok' => true]);
      break;
    case 'deleteTemplate':
      header('Content-Type: application/json');
      $templateName = lxc_template_name($_POST['name'] ?? '');
      $templateFile = $templateName === '' ? '' : lxc_template_dir() . '/' . $templateName . '.xml';
      if ($templateFile === '' || !is_file($templateFile)) {
        echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, the template does not exist!')]);
        break;
      }
      @unlink($templateFile);
      echo json_encode(['ok' => true]);
      break;
    case 'templateCache':
      // Maintenance for the downloaded archives: list, delete one, or clear all.
      header('Content-Type: application/json');
      $cacheDir = rtrim($settings->default_path, '/') . '/cache/template_cache';
      $operation = $_POST['op'] ?? 'list';
      if ($operation === 'list') {
        $files = [];
        foreach ((array)@glob($cacheDir . '/*') as $cached) {
          if (!is_file($cached) || !preg_match('/\.(tar\.xz|md5)$/', $cached)) {
            continue;
          }
          $files[] = ['name' => basename($cached), 'size' => filesize($cached), 'mtime' => filemtime($cached)];
        }
        usort($files, function ($a, $b) { return $b['mtime'] <=> $a['mtime']; });
        echo json_encode(['ok' => true, 'files' => $files]);
        break;
      }
      if ($operation === 'delete') {
        $file = basename((string)($_POST['file'] ?? ''));
        if (!preg_match('/\.(tar\.xz|md5)$/', $file) || !is_file($cacheDir . '/' . $file)) {
          echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, the cache file does not exist!')]);
          break;
        }
        @unlink($cacheDir . '/' . $file);
        echo json_encode(['ok' => true]);
        break;
      }
      if ($operation === 'clear') {
        foreach ((array)@glob($cacheDir . '/*') as $cached) {
          if (is_file($cached) && preg_match('/\.(tar\.xz|md5)$/', $cached)) {
            @unlink($cached);
          }
        }
        echo json_encode(['ok' => true]);
        break;
      }
      echo json_encode(['ok' => false, 'error' => lxc_t('ERROR, unknown cache operation!')]);
      break;
    default:
      break;
  }
}

if (isset($_POST['action']) && $_POST['action'] == 'updateValues') {
    if (!is_dir('/tmp/lxc/containers')) {
        @mkdir('/tmp/lxc/containers', 0755, true);
    }
    file_put_contents('/tmp/lxc/containers/active', time());
    $rawNames = $_POST['containerNames'] ?? '[]';
    $containerNames = is_string($rawNames) ? json_decode($rawNames, true) : (is_array($rawNames) ? $rawNames : []);
    if (!is_array($containerNames)) {
        $containerNames = [];
    }
    $data = [];
    // 一次性列出所有采集器进程：每个容器单独 pgrep 会白 fork 一次。
    $workerProcs = (string) shell_exec("pgrep -af 'lxc_stats_worker' 2>/dev/null");
    $seen = [];
    foreach ($containerNames as $name) {
        $cleanName = sanitizeContainerName($name);
        if ($cleanName === null || isset($seen[$cleanName])) {
            continue;
        }
        $seen[$cleanName] = true;
        $container = new Container($cleanName);
        // 容器可能是页面打开之后才启动的：页面渲染时只为当时的运行中容器拉起
        // 采集器，所以轮询发现新的运行中容器时补一次。
        if ($container->state === 'RUNNING'
            && strpos($workerProcs, 'lxc_stats_worker "' . $cleanName . '"') === false) {
            shell_exec('/usr/local/emhttp/plugins/lxc/scripts/lxc_stats_worker "' . $cleanName . '" > /dev/null 2>/dev/null &');
        }
        $cpu_usage = $container->cpu_usage;
        $memoryUse = $container->memoryUse;
        $ips = $container->ips;
        // 动态统计关闭时没有采集器写地址，这里现场取一次：否则启动后的行在
        // 免刷新更新时会留下空的 Address 列。
        if ($ips === '' && $container->state === 'RUNNING') {
            $rawIps = (string) shell_exec('lxc-info ' . escapeshellarg($cleanName) . ' -iH 2>/dev/null');
            $v4 = [];
            $docker = [];
            $v6 = [];
            foreach (preg_split('/\R/', trim($rawIps)) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (strpos($line, ':') !== false) {
                    $v6[] = $line;
                } elseif (strpos($line, '172.') === 0) {
                    $docker[] = $line;
                } elseif (strpos($line, '.') !== false) {
                    $v4[] = $line;
                }
            }
            $ips = implode('<br/>', array_merge($v4, $docker, $v6));
        }
        $totalBytes = $container->totalBytes;
        $data[] = [
            'name' => $cleanName,
            'state' => $container->state,
            'pid' => $container->pid,
            'uptime' => $container->uptime,
            // 没在配置里限制 cpuset 的容器只有运行中才拿得到亲和性（读 /proc/<pid>/status），
            // 启动后不刷新页面时这一列会一直是空的。
            'cpus' => $container->cpus,
            'cpu_usage' => $cpu_usage,
            'memoryUse' => $memoryUse,
            'ips' => $ips,
            'totalBytes' => $totalBytes,
        ];
    }
    echo json_encode($data);
}
