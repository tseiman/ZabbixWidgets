class CWidgetPbsRunning extends CWidget {
    onInitialize() {
        super.onInitialize();
        this._pbsDialog = null;
        this._pbsClick = event => {
            const button = event.target.closest('.pbs-task-button');
            if (button && this._body.contains(button)) {
                this._pbsShowTasks(button);
            }
        };
    }
    onActivate() { super.onActivate(); this._body.addEventListener('click', this._pbsClick); this._pbsTimer = setInterval(() => { const now = Math.floor(Date.now() / 1000); for (const cell of this._body.querySelectorAll('[data-pbs-start]')) { cell.textContent = now - Number(cell.dataset.pbsObserved) > 180 ? t('Data is stale') : this._pbsDuration(now - Number(cell.dataset.pbsStart)); } }, 1000); }
    onDeactivate() { clearInterval(this._pbsTimer); this._body.removeEventListener('click', this._pbsClick); this._pbsClose(); super.onDeactivate(); }
    onDestroy() { this._pbsClose(); super.onDestroy(); }
    _pbsClose() { this._pbsLogAbort?.abort(); if (this._pbsDialog) { const dialog = this._pbsDialog; dialog.close(); dialog.remove(); if (this._pbsDialog === dialog) { this._pbsDialog = null; } } }
    _pbsDuration(seconds) { seconds = Math.max(0, Math.floor(seconds)); return (seconds >= 86400 ? Math.floor(seconds / 86400) + 'd ' : '') + (seconds >= 3600 ? Math.floor(seconds % 86400 / 3600) + 'h ' : '') + Math.floor(seconds % 3600 / 60) + 'm ' + seconds % 60 + 's'; }
    _pbsShowTasks(button) {
        this._pbsClose();
        const context = button.closest('[data-pbs-hostid]');
        const tasks = JSON.parse(button.dataset.pbsTasks).sort((a, b) => b.starttime - a.starttime);
        const locale = button.closest('[data-pbs-locale]')?.dataset.pbsLocale || 'en';
        const dialog = document.createElement('dialog');
        dialog.className = 'pbs-task-dialog pbs-panel';
        dialog.setAttribute('aria-label', button.dataset.pbsTitle);
        // Avoid global Zabbix page-header rules.
        const heading = document.createElement('div'); heading.className = 'pbs-dialog-heading';
        const title = document.createElement('div'); title.className = 'pbs-dialog-title';
        title.textContent = button.dataset.pbsTitle;
        const close = document.createElement('button');
        close.type = 'button'; close.className = 'pbs-dialog-close'; close.textContent = '×'; close.setAttribute('aria-label', t('Close'));
        close.addEventListener('click', () => this._pbsClose());
        heading.append(title, close);
        const note = document.createElement('p');
        note.className = 'pbs-meta'; note.textContent = t('Snapshot when opened') + ' · ' + t('newest start time first') + ' · ' + tasks.length + ' ' + t('tasks');
        const table = document.createElement('table'); table.className = 'pbs-table pbs-tasks';
        const head = table.createTHead().insertRow();
        for (const label of [t('Task'), t('Start time ↓'), t('Duration'), t('Status')]) { const th = document.createElement('th'); th.textContent = label; head.append(th); }
        const body = table.createTBody();
        let shown = 0;
        const more = document.createElement('button'); more.type = 'button'; more.textContent = t('Show more tasks');
        const appendRows = () => {
            const next = Math.min(shown + 200, tasks.length);
            for (; shown < next; shown++) {
                const task = tasks[shown];
                const row = body.insertRow(); row.title = task.upid;
                let seconds = Math.max(0, (task.endtime || Math.floor(Date.now() / 1000)) - task.starttime);
                const duration = this._pbsDuration(seconds);
                for (const text of [task.label || task.worker_type + ' · ' + (task.worker_id || ''), task.start_label || new Date(task.starttime * 1000).toLocaleString(locale), duration, task.status === 'RUNNING' ? t('Running') : task.status || t('Unknown')]) { row.insertCell().textContent = text; }
                const taskLink = document.createElement('button'); taskLink.type = 'button'; taskLink.className = 'pbs-task-button'; taskLink.textContent = row.cells[0].textContent; taskLink.title = t('Open task log'); row.cells[0].replaceChildren(taskLink); taskLink.addEventListener('click', () => this._pbsOpenLog(dialog, content, task, context));
            }
            more.hidden = shown >= tasks.length;
        };
        more.addEventListener('click', appendRows); appendRows();
        const content = document.createElement('div'); content.className = 'pbs-dialog-content';
        content.append(note, table, more);
        dialog.append(heading, content);
        if (!tasks.length) { const empty = document.createElement('p'); empty.textContent = t('No tasks in this category.'); content.append(empty); }
        let surface = this._body;
        let background = 'Canvas';
        while (surface) {
            const color = getComputedStyle(surface).backgroundColor;
            if (color !== 'transparent' && color !== 'rgba(0, 0, 0, 0)') { background = color; break; }
            surface = surface.parentElement;
        }
        dialog.style.setProperty('--pbs-dialog-bg', background);
        dialog.style.setProperty('--pbs-dialog-fg', getComputedStyle(this._body).color);
        const borderCell = this._body.querySelector('th');
        if (borderCell) { dialog.style.setProperty('--pbs-dialog-border', getComputedStyle(borderCell).borderBottomColor); }
        dialog.addEventListener('close', () => { if (this._pbsDialog === dialog) { this._pbsLogAbort?.abort(); } dialog.remove(); if (this._pbsDialog === dialog) { this._pbsDialog = null; } if (button.isConnected) { button.focus(); } });
        document.body.append(dialog); this._pbsDialog = dialog; dialog.showModal(); close.focus();
        if (tasks.length === 1) { this._pbsOpenLog(dialog, content, tasks[0], context); }
    }
    _pbsOpenLog(dialog, content, task, context) {
        this._pbsLogAbort?.abort();
        content.querySelector('.pbs-log-section')?.remove();
        const section = document.createElement('section'); section.className = 'pbs-log-section';
        const title = document.createElement('h3'); title.textContent = t('Task log') + ' · ' + (task.label || task.worker_type);
        const id = document.createElement('div'); id.className = 'pbs-meta'; id.textContent = task.upid;
        const toolbar = document.createElement('div'); toolbar.className = 'pbs-log-toolbar';
        const reload = document.createElement('button'); reload.type = 'button'; reload.textContent = t('Reload');
        const more = document.createElement('button'); more.type = 'button'; more.textContent = t('More log lines'); more.hidden = true;
        const status = document.createElement('span'); status.setAttribute('role', 'status');
        const pre = document.createElement('pre'); pre.className = 'pbs-log-text'; pre.tabIndex = 0;
        toolbar.append(reload, more, status); section.append(title, id, toolbar, pre); content.prepend(section);
        let next = 1; let count = 0; let byteCount = 0;
        const load = async reset => {
            this._pbsLogAbort?.abort();
            const controller = new AbortController(); this._pbsLogAbort = controller;
            if (reset) { next = 1; count = 0; byteCount = 0; pre.textContent = ''; }
            reload.disabled = true; more.disabled = true; status.textContent = t('Loading log …');
            const timeout = setTimeout(() => controller.abort(), 20000);
            try {
                if (!context?.dataset.pbsHostid || !context.dataset.pbsLogAction) { throw new Error(t('Refresh the widget; host mapping is missing.')); }
                const url = new URL('zabbix.php', window.location.href); url.searchParams.set('action', context.dataset.pbsLogAction);
                const response = await fetch(url, {method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({hostid: context.dataset.pbsHostid, upid: task.upid, start: next}), signal: controller.signal});
                if (!response.ok) { throw new Error(t('Log request failed (HTTP %d).').replace('%d', response.status)); }
                const result = await response.json();
                if (result.error) { throw new Error((result.error.messages || [t('Log unavailable.')]).join(' ')); }
                if (!Array.isArray(result.lines) || !Number.isInteger(result.next) || result.next < next || (result.more && result.next === next)) { throw new Error(t('Invalid response; check the Zabbix login and module activation.')); }
                if (controller.signal.aborted || !dialog.isConnected || !section.isConnected) { return; }
                const chunk = result.lines.map(line => line.n + '  ' + line.t).join('\n');
                if (byteCount + chunk.length > 8000000 || count + result.lines.length > 20000) { more.hidden = true; throw new Error(t('Display limit reached (20,000 lines / 8 million characters). Open the complete log in PBS.')); }
                pre.append(document.createTextNode((count && chunk ? '\n' : '') + chunk));
                byteCount += chunk.length; count += result.lines.length; next = result.next; more.hidden = !result.more;
                status.textContent = t('%d of %d lines').replace('%d', count).replace('%d', result.total) + (result.active ? ' · ' + t('Task is running; reload to refresh') : '');
                if (!count) { status.textContent += ' · ' + t('No log lines yet'); }
            }
            catch (error) {
                if (section.isConnected && this._pbsLogAbort === controller) { status.textContent = error.name === 'AbortError' ? t('Timed out. Please reload.') : error instanceof SyntaxError ? t('No valid response. Check the Zabbix login and module activation.') : error.message; }
            }
            finally { clearTimeout(timeout); if (this._pbsLogAbort === controller) { reload.disabled = false; more.disabled = false; } }
        };
        reload.addEventListener('click', () => load(true)); more.addEventListener('click', () => load(false));
        load(true);
    }
}
