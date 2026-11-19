# Unraid LXC Plugin

[![Release Plugin](https://github.com/moqiaoduo/unraid-lxc-plugin/actions/workflows/release-plugin.yml/badge.svg)](https://github.com/moqiaoduo/unraid-lxc-plugin/actions/workflows/release-plugin.yml)
[![GitHub Release](https://img.shields.io/github/v/release/moqiaoduo/unraid-lxc-plugin?include_prereleases)](https://github.com/moqiaoduo/unraid-lxc-plugin/releases)

A modern, native management plugin for running **Linux Containers (LXC)** on [Unraid](https://unraid.net).

LXC is a lightweight operating-system-level virtualization technology that allows running full Linux distributions (Ubuntu, Debian, Alpine, Arch, Fedora, Rocky, Void, etc.) with near-native performance, full system init systems (systemd, OpenRC, runit), and complete kernel feature access.

---

## Features

- **WebUI Container Management**: Start, stop, freeze, restart, copy, destroy, and edit containers directly from the Unraid dashboard.
- **In-Browser Web Terminal**: Interactive terminal shell console for any running container with one click.
- **Storage Backends Supported**:
  - **Directory (`dir`)**: Universal standard directory rootfs storage.
  - **BTRFS (`btrfs`)**: Native copy-on-write subvolumes and instantaneous snapshots.
  - **ZFS (`zfs`)**: Native dataset integration, copy-on-write cloning, snapshot management, and send/receive support.
- **Automated Snapshots & Backups**:
  - Scheduled automated snapshots (`lxc-autosnapshot`) with retention policies.
  - Full-system container backup & restore (`lxc-autobackup`) with multi-threaded XZ compression.
- **Cgroup v2 Resource Accounting**:
  - Real-time CPU and active working memory (`memory.current` excluding reclaimable file cache) usage display.
  - Dynamic container resource graphs on the Unraid Dashboard.
- **Intelligent Network IP Classification**:
  - Automatically identifies container LAN IPv4, Docker bridge interfaces, and IPv6 addresses without mislabeling RFC 1918 `172.16.0.0/12` private networks.
- **Zero-Downtime Array Integration**:
  - Clean array stop hook ensuring all containers gracefully shut down and all container submounts / ZFS datasets unmount cleanly to prevent `device busy` errors.
  - Robust startup hook with automatic `atd` detection and detached fallback execution.

---

## Installation

1. In the Unraid WebGUI, navigate to **Plugins** > **Install Plugin**.
2. Paste the plugin URL into the URL field:
   ```text
   https://raw.githubusercontent.com/moqiaoduo/unraid-lxc-plugin/master/lxc.plg
   ```
3. Click **Install**.

---

## Configuration & Best Practices

### Recommended Storage Path
- Go to **Settings** > **LXC Settings**.
- Set **Default LXC storage path** to a dedicated cache share or pool:
  ```text
  /mnt/cache/lxc
  ```
  > [!IMPORTANT]
  > **Do NOT use FUSE `/mnt/user/...` paths.** Always use a direct pool path like `/mnt/cache/lxc` or `/mnt/diskX/lxc` to ensure optimal performance and avoid lockups with container filesystems.
  > Ensure the Unraid Mover is set to ignore your LXC share (set to "Cache only" or "Primary: Cache, Secondary: None").

---

## Command-Line Utilities

The plugin installs several convenient CLI tools under `/usr/bin`:

### `lxc-autobackup`
Create compressed backups or restore existing containers:
```bash
# Backup a container using global settings
lxc-autobackup <container_name>

# Backup container to a specific path, keeping last 3 backups
lxc-autobackup -n <container_name> -p /mnt/user/backups/lxc -b 3 -c 6 -t 4

# Backup from a temporary snapshot (minimizes container downtime)
lxc-autobackup -s -n <container_name> -p /mnt/user/backups/lxc -b 3

# Restore a container from backup
lxc-autobackup -r <source_container_name> /mnt/user/backups/lxc <new_container_name>
```

### `lxc-autosnapshot`
Take an instant snapshot of a container and retain a specified number of snapshots:
```bash
# Create a snapshot of MyContainer and keep the last 3 snapshots
lxc-autosnapshot MyContainer 3
```

### `lxc-dirtobtrfs` & `lxc-dirtozfs`
Convert an existing directory-based container to BTRFS subvolumes or ZFS datasets:
```bash
# Convert container to BTRFS
lxc-dirtobtrfs <container_name>

# Convert container to ZFS
lxc-dirtozfs <container_name>
```

---

## Development & Packaging

To build the plugin package `.txz` locally from the repository source:
```bash
# Package the current state into dist/
./source/makepkg-unraid.sh 2026.11.20.0100
```
This script will:
1. Stage all files with root permissions.
2. Build `dist/lxc-${VERSION}.txz`.
3. Compute the MD5 checksum into `dist/lxc-${VERSION}.txz.md5`.
4. Automatically update the version and MD5 entities in `lxc.plg`.

### LXC runtime packages

The LXC runtime is not built here. It is compiled, verified and published from
[moqiaoduo/unraid-lxc-runtime](https://github.com/moqiaoduo/unraid-lxc-runtime),
and `lxc.plg` downloads the version named in its `lxcver` entity from that
repository. The two release lines are independent on purpose: publishing a
plugin never rebuilds the runtime, and a runtime bump is a one-line change to
`lxc.plg` that shows up in review, instead of a release moving underneath every
installed machine.

Every runtime version is published as two packages, because Unraid 6.x and 7.x
have different glibc baselines:

| Package | Build environment | glibc baseline | Target Unraid |
|---|---|---|---|
| `LXC-<ver>-1.txz` | Debian 10 (buster) | `<= 2.28` | 6.10 - 6.12 |
| `LXC-<ver>-2.txz` | Debian 12 (bookworm) | `<= 2.36` | 7.x |

`lxc.plg` picks the suffix at install time from `/etc/unraid-version`, so both
must be published under the same runtime tag.

To move to a new runtime version: publish it in the runtime repository, then
change `<!ENTITY lxcver "...">` in `lxc.plg` and release a plugin. `lxc-start
--version` must report exactly the entity's value, otherwise the plugin
reinstalls the runtime on every boot.

### Upstream version and backports

The `7.0.1` runtime is **not** an upstream release. Upstream `lxc/lxc` has
published no tag past `v7.0.0`, while GHSA-4xwm-pr6h-55r8 lists `>= v7.0.1` as
the patched version. The runtime repository therefore builds `v7.0.0` with two
commits cherry-picked from upstream `main`, and sets the project version to
`7.0.1` so `lxc-start --version` matches the tag `lxc.plg` names:

- `06f62609` — commands: validate string in `lxc_cmd_get_config_item_callback`
- `19ad80ed` — commands: validate datalen in `lxc_cmd_console_log_callback`

### Automated Releases

Both repositories release from GitHub Actions, on independent schedules:

- **Runtime** (`unraid-lxc-runtime`): the tag is the LXC version, e.g.
  `git tag 7.0.1 && git push origin 7.0.1`.
- **Plugin** (this repository): run the `Release Plugin` workflow manually. It
  takes no inputs — the plugin version is the build date and time, and the
  runtime version is read out of `lxc.plg`. Pushing a tag releases nothing here.

---

## License

LGPL-2.1 License. See [LICENSE](LICENSE) for details.
