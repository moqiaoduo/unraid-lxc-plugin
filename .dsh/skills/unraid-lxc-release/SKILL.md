---
name: unraid-lxc-release
description: Publish a new version of the unRAID LXC plugin (moqiaoduo/unraid-lxc-plugin). Use when the user asks to cut a release, publish the plugin, bump its version, or generate the CHANGES section. It summarises the commits since the last release into lxc.plg, validates with source/release-verify.sh, commits, tags plugin-<version> and pushes; GitHub Actions then builds the package, writes the md5 into lxc.plg and creates the release.
---

# Publish the unRAID LXC plugin

发布由三样东西组成：`lxc.plg` 里的 CHANGES 节、`plugin-<version>` tag、push。
CI（`.github/workflows/release-plugin.yml`）负责构建、算 md5、把产物提交回 master、建 release。
本地不做打包，也不手写 `<!ENTITY version>` 和 `<!ENTITY md5>`。

## 前置检查

在工作区根执行，任何一项不满足就停下报告：

```bash
git status --short          # 必须为空
git branch --show-current   # 必须是 master
git pull --ff-only
git tag --list 'plugin-*' --sort=-v:refname | head -1   # 上一个版本
```

## 1. 定版本号

默认用 UTC 时间戳：

```bash
date -u +'%Y.%m.%d.%H%M'
```

用户指定了版本号（比如 `2026.11.22`）就用用户的。无论哪种，必须严格大于上一个 tag；第 4 步的校验会拦住回退或重复的版本号。

## 2. 从 commit 总结 CHANGES

列出自上一个 tag 以来的提交：

```bash
PREV="$(git tag --list 'plugin-*' --sort=-v:refname | head -1)"
git log --no-merges --format='%s' "${PREV}..HEAD"
```

把提交总结成面向用户的条目，规则：

- **只收用户能感知的变化**：新功能、行为变化、修复、安装或兼容性变化。
- **跳过**：纯内部重构、测试、CI 配置、注释、格式、依赖升级——除非它改变了用户看到的行为。
- **跳过** `release: ...` 开头的提交（那是上一次发布动作本身）。
- 祈使句开头，首字母大写，不加句号，一行一条。
- 合并同类条目：同一主题的多个 commit 写一条，不按 commit 逐条罗列。
- 不写 commit hash、不写「various fixes」「update」这类空话。
- 数量 1–8 条，按重要性排序。
- 用英文写：现有 CHANGES 全是英文，插件管理器里也按英文显示。

例：

```
- Restore a container in place from one of its snapshots
- Take the LXC runtime version from the plugin pin instead of the latest upstream release
```

## 3. 写进 lxc.plg

在 `<CHANGES>` 之后、上一版 `###` 之前插入新节，保持空行结构：

```
<CHANGES>

###<version>
- 条目一
- 条目二

###<上一版>
```

用 `edit` 工具匹配 `<CHANGES>\n\n###<上一版>` 做插入，不要整文件重写。

## 4. 校验

```bash
PREV="$(git tag --list 'plugin-*' --sort=-v:refname | head -1)"
bash source/release-verify.sh "<version>" "${PREV#plugin-}"
```

必须退出码 0，并把本节正文打印出来。失败时会说明原因，按提示修：
版本号必须大于上一个 tag；首节标题必须等于版本号，且至少有一条 `- ` 条目。

## 5. 提交、打 tag、推送

```bash
git add lxc.plg
git commit -m "release: plugin <version>"
git tag "plugin-<version>"
git push origin master
git push origin "plugin-<version>"
```

顺序不能反：先 push master，再 push tag。tag 的 push 触发 CI。

## 6. 确认发布

```bash
gh run list --workflow release-plugin.yml --limit 3
gh release view "plugin-<version>"
```

成功标准：verify / build-plugin / release 三个 job 全绿，release 带三个资产
（`lxc-<version>.txz`、`.txz.md5`、`lxc.plg`）。
构建完成后 master 上会多一个 bot 提交（改 `<!ENTITY version>` 和 `<!ENTITY md5>`、往 `packages/` 放包），这是正常的。

## 失败处理

| 失败点 | 处理 |
|---|---|
| verify：tag 与 CHANGES 首节不符 | 改 CHANGES，删本地 tag 重打，或先修再发新版本 |
| verify：版本没有递增 | 换一个更大的版本号，重写节标题 |
| verify：tag 不在 master 上或有非 release 提交 | 把 tag 打到当前 master，重新 push |
| build：打包失败 | 修代码，升版本号重发；不要复用已经 push 的 tag |
| build 后 push master 失败（non-fast-forward） | 停下报告，让用户决定 rebase 还是手工合并 |
| release job 失败但包已构建 | `gh workflow run release-plugin.yml -f tag=plugin-<version>` 重跑 |

已经 push 的 tag 不能用 `git push --force` 覆盖。要撤，先删远端 tag 再重打：

```bash
git push origin ":refs/tags/plugin-<version>"
git tag -d "plugin-<version>"
```

## 禁止

- 手工改 `<!ENTITY version>` / `<!ENTITY md5>`，或手工往 `packages/` 放包。
- 跳过 `source/release-verify.sh` 直接打 tag。
- 在非 master 分支发版。
- `git push --force`。
- 在版本号未递增时发布（unRAID 会把同版本当成已是最新）。