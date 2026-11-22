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
        createContainer($name, $_POST['description'] ?? '', $_POST['distribution'] ?? '', $_POST['release'] ?? '', $_POST['startcont'] ?? '', $_POST['autostart'] ?? '', $_POST['mac'] ?? '');
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
      $containerConfig = rtrim($settings->default_path, '/') . "/" . $container . "/config";
      if (file_exists(dirname($containerConfig))) {
        file_put_contents($containerConfig, $updatedConfig);
        $containerObj = new Container($container);
        if ($containerObj->state == "RUNNING") {
          $containerObj->restartContainer();
        }
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
      $containerObj->setDescription($single('description'));
      $containerObj->setAutostart((string)($_POST['autostart'] ?? '0') === '1' ? 1 : 0);
      if ($mac !== '') {
        $containerObj->setMac(strtoupper($mac));
      }
      $containerObj->setWebuiurl($single('webui'));
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
      $restarted = false;
      if ($containerObj->state === 'RUNNING') {
        $containerObj->restartContainer();
        $restarted = true;
      }
      echo json_encode(['ok' => true, 'restarted' => $restarted]);
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
        createfromTemplate($name, $_POST['description'] ?? '', $_POST['repository'] ?? '', $_POST['webui'] ?? '', $_POST['icon'] ?? '', $_POST['startcont'] ?? '', $_POST['autostart'] ?? '', $_POST['convertbdev'] ?? '', $_POST['mac'] ?? '', $_POST['supportlink'] ?? '', $_POST['donatelink'] ?? '');
      }
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
