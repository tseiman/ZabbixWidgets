class CWidgetPbsDatastore extends CWidget {
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
    _pbsClose() { if (this._pbsDialog) { this._pbsDialog.close(); this._pbsDialog.remove(); this._pbsDialog = null; } }
    _pbsDuration(seconds) { seconds = Math.max(0, Math.floor(seconds)); return (seconds >= 86400 ? Math.floor(seconds / 86400) + 'd ' : '') + (seconds >= 3600 ? Math.floor(seconds % 86400 / 3600) + 'h ' : '') + Math.floor(seconds % 3600 / 60) + 'm ' + seconds % 60 + 's'; }
    _pbsShowTasks(button) {
        this._pbsClose();
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
        dialog.addEventListener('close', () => { dialog.remove(); if (this._pbsDialog === dialog) { this._pbsDialog = null; } if (button.isConnected) { button.focus(); } });
        document.body.append(dialog); this._pbsDialog = dialog; dialog.showModal(); close.focus();
    }
}
