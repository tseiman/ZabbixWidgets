# PBS Dashboard 1.0.5

Complete package with five Zabbix widgets, a template, collectors, an installation script, and tests. It builds on version 1.0.4.

## New in 1.0.5

- **PBS Task List:** completed tasks with start time, end time, user, description, and status, sorted by newest start time first. Errors are red and warnings are yellow. The default is 50 rows; the widget setting accepts 1 to 1000 rows and displays the total count. As before, the dashboard period is evaluated against each task's completion time.
- **Logs directly in Zabbix:** select a task in Task List, Longest Tasks, or Running Tasks. In Summary, first select the category/status count and then the task. When exactly one task matches, its log opens immediately. **More log lines** and **Reload** actions are available.
- The template and collectors are unchanged. For existing snapshots, the user name is parsed from the PBS UPID.
- Summary filtering, time periods, deduplication, and all other functions remain available. The Summary filter applies only to the individual Summary widget; Task List displays all available completed tasks.

## Upgrade from 1.0.4

Run as root on the Zabbix frontend server:

```sh
tar -xzf pbs-dashboard-1.0.5.tar.gz
sh pbs-dashboard-1.0.5/update.sh /usr/share/zabbix/ui
```

`/usr/share/zabbix` is also supported when the frontend is located in its `ui` subdirectory. The script backs up existing PBS modules to `/var/backups/pbs-dashboard/<timestamp-process-number>` and displays the resulting path. Other modules remain untouched. In container deployments, the modules must reside in the persistent frontend volume.

1. Run **Administration → General → Modules → Scan directory** and enable **PBS Task List**. Keep the existing modules enabled.
2. Fully reload the dashboard with **Ctrl+F5** or **Cmd+Shift+R**.
3. Edit the dashboard, add **PBS Task List**, and select the PBS host and number of rows.
4. Configure the following connection once to enable logs. The widgets continue to work without it; attempting to open a log displays a setup notice.

**No template re-import is required. Do not delete or reinstall the template.** Module versions are consistently set to 1.0.5, and existing widget IDs and settings remain unchanged.

## One-time setup for direct task logs

Logs are retrieved from the **Zabbix frontend/PHP runtime** to PBS. This requires PHP cURL and HTTPS access from the frontend to PBS. When the server, proxy, and frontend run on different systems or in different containers, connectivity from only the Zabbix server or proxy is insufficient.

Secret Zabbix macros are not read. A file outside the web root maps allowed Zabbix host IDs to PBS connections. The existing monitoring token can be used; reading tasks belonging to other users requires effective **Sys.Audit on `/system/tasks`**, including the effective permissions when token privilege separation is enabled.

For the initial setup, copy the example file and edit it with **mcedit**. Replace the `www-data` group with the actual PHP-FPM or webserver group when it differs. If `/etc/zabbix` does not exist, create it first without changing the permissions of an existing directory.

```sh
install -m 0640 -o root -g www-data pbs-dashboard-1.0.5/config/pbs-dashboard.example.json /etc/zabbix/pbs-dashboard.json
mcedit /etc/zabbix/pbs-dashboard.json
```

Edit an existing configuration file in place; do not overwrite it with the example again. Example content—all values must be replaced:

```json
{
  "hosts": {
    "12345": {
      "url": "https://pbs.example.invalid:8007",
      "token_id": "monitor@pbs!zabbix",
      "token_secret": "REPLACE_WITH_THE_EXISTING_TOKEN_SECRET"
    }
  }
}
```

`12345` is the **Zabbix host ID of the PBS host**, visible, for example, as `hostid=12345` in the host-edit URL. Add more entries below `hosts` for additional PBS hosts. The URL is the HTTPS base address without an API path. Write credentials only to this protected file, never to widget settings or JavaScript. The update script does not overwrite this file. In container deployments, mount the file persistently into the frontend container.

TLS certificates are verified. For a private CA, optionally add `"ca_file": "/etc/zabbix/pbs-ca.pem"` to the host entry and place the trusted CA file where PHP can read it. The DNS name must match the certificate. Disabling certificate verification is not supported.

Every log request validates the Zabbix session and the user's host and task-item read permissions. **Anyone who can read the configured PBS host and its task items in Zabbix can read that host's PBS task logs with the permissions of the configured token.** Add only host mappings whose logs those users are intended to access. The endpoint cannot stop, delete, or start PBS tasks.

After setup, open a known task. For HTTP 401/403, check the token and permissions. For a connection or TLS notice, check reachability and certificate trust from the PHP frontend's perspective. Logs already removed from PBS cannot be reconstructed from Zabbix history.

## Existing widgets and Summary filter

- **PBS Task Summary:** Failed/Warning/OK counts by category with details.
- **PBS Longest Tasks:** longest completed tasks in the selected period.
- **PBS Running Tasks:** running tasks with their runtimes.
- **PBS Datastore:** current capacity, usage, forecast, and history chart. This widget contains no tasks and therefore has no log function.

The following expression remains preconfigured under **PBS Task Summary → Edit → Exclude tasks (expression)**:

```text
worker_type === 'reader' && status === 'connection error: not connected'
```

It can be changed independently in each widget; **an empty field means no filtering**. Custom saved filters and empty settings remain unchanged. Only an exact match is removed from counts and details; `connection error: unexpected EOF` remains Other/Failed. Other widgets and data sources are unaffected.

Fields: `worker_type`, `status`, `worker_id`, `user`, and `node`. Operators: `===`, `!==`, `&&`, `||`, `!`, and parentheses. Strings use single or double quotation marks, comparisons are case-sensitive, and the maximum length is 255 bytes. The expression does not execute PHP or JavaScript. Missing fields are treated as `null`; the existing collector does not provide `user` as a separate field, so Task List parses the displayed user from the UPID.

## New installation

Install the modules as described above and enable all five. Import `templates/pbs-template-extended.yaml`, assign it to the PBS host, and configure the host, port, and token macros. For an existing template, review the import comparison, keep **Delete missing** disabled, and account for local customizations. Run **Execute now** for **PBS dashboard: Collect completed tasks** and **PBS dashboard: Running tasks**. Check their values under **Latest data**; a successful empty task list is valid. Add the widgets and configure direct logs separately as described above.

## Data and limits

Completed tasks are collected every five minutes and running tasks every minute. The initial lookback is 48 hours and can be configured from 1 to 168 hours with `{$PBS.DASHBOARD.LOOKBACK.HOURS}`. History then grows up to the configured 90-day retention period. Older PBS tasks that were never collected remain unavailable. Dashboard periods are limited to 90 days, and gaps are displayed. Zabbix housekeeping and PBS retention affect availability.

The 16 overlapping text snapshots are deduplicated by UPID. Limits: 60,000 conservatively estimated JSON bytes per part and an 8-million-character PBS archive response. Overflows are reported.

Logs are not stored permanently in Zabbix. Each request returns at most 500 lines and 2 MiB, with a 5-second connection timeout and 15-second total timeout. The display is limited to 20,000 lines or 8 million characters per opened log and reports when the limit is reached. Use **Reload** to update a running log. Inspect larger logs directly in PBS.

## Verification and files

Verified: PHP, JavaScript, and shell syntax; existing regression tests; task ordering; user display; filters; log pages; input validation; authorization decisions; and HTTP errors using a simulated transport. DOM tests cover all four task widgets with simulated responses. Installation and upgrade are tested against a simulated frontend. PHP tests run in a WebAssembly runtime. A real browser preview was unavailable in the build environment. **No live test was performed against a running Zabbix/PBS installation.**

```sh
php tests/test.php
php tests/test-expression.php
php tests/test-summary-filter.php
php tests/test-task-list.php
php tests/test-log.php
php tests/test-log-access.php
php tests/test-log-transport.php
node tests/test-collector.cjs
```

Included: `modules/` with five complete widgets, `templates/`, `collector/`, `config/pbs-dashboard.example.json` without real credentials, `update.sh`, `tests/`, `SHA256SUMS`, `LICENSE`, and this README.

References: [PBS task/log API](https://github.com/proxmox/proxmox-backup/blob/master/src/api2/node/tasks.rs) and [Zabbix module actions](https://github.com/zabbix/zabbix/blob/release/7.0/ui/include/classes/core/CModuleManager.php).
