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
    onActivate() { super.onActivate(); this._body.addEventListener('click', this._pbsClick); this._pbsTimer = setInterval(() => { const now = Math.floor(Date.now() / 1000); for (const cell of this._body.querySelectorAll('[data-pbs-start]')) { cell.textContent = now - Number(cell.dataset.pbsObserved) > 180 ? 'Daten veraltet' : this._pbsDuration(now - Number(cell.dataset.pbsStart)); } }, 1000); }
    onDeactivate() { clearInterval(this._pbsTimer); this._body.removeEventListener('click', this._pbsClick); this._pbsClose(); super.onDeactivate(); }
    onDestroy() { this._pbsClose(); super.onDestroy(); }
    _pbsClose() { this._pbsLogAbort?.abort(); if (this._pbsDialog) { const dialog = this._pbsDialog; dialog.close(); dialog.remove(); if (this._pbsDialog === dialog) { this._pbsDialog = null; } } }
    _pbsDuration(seconds) { seconds = Math.max(0, Math.floor(seconds)); return (seconds >= 86400 ? Math.floor(seconds / 86400) + 'd ' : '') + (seconds >= 3600 ? Math.floor(seconds % 86400 / 3600) + 'h ' : '') + Math.floor(seconds % 3600 / 60) + 'm ' + seconds % 60 + 's'; }
    _pbsShowTasks(button) {
        this._pbsClose();
        const context = button.closest('[data-pbs-hostid]');
        const tasks = JSON.parse(button.dataset.pbsTasks).sort((a, b) => b.starttime - a.starttime);
        const dialog = document.createElement('dialog');
        dialog.className = 'pbs-task-dialog pbs-panel';
        dialog.setAttribute('aria-label', button.dataset.pbsTitle);
        // Avoid global Zabbix page-header rules.
        const heading = document.createElement('div'); heading.className = 'pbs-dialog-heading';
        const title = document.createElement('div'); title.className = 'pbs-dialog-title';
        title.textContent = button.dataset.pbsTitle;
        const close = document.createElement('button');
        close.type = 'button'; close.className = 'pbs-dialog-close'; close.textContent = '×'; close.setAttribute('aria-label', 'Schließen');
        close.addEventListener('click', () => this._pbsClose());
        heading.append(title, close);
        const note = document.createElement('p');
        note.className = 'pbs-meta'; note.textContent = 'Stand beim Öffnen · neueste Startzeit zuerst · ' + tasks.length + ' Tasks';
        const table = document.createElement('table'); table.className = 'pbs-table pbs-tasks';
        const head = table.createTHead().insertRow();
        for (const label of ['Task', 'Start Time ↓', 'Duration', 'Status']) { const th = document.createElement('th'); th.textContent = label; head.append(th); }
        const body = table.createTBody();
        let shown = 0;
        const more = document.createElement('button'); more.type = 'button'; more.textContent = 'Weitere Tasks anzeigen';
        const appendRows = () => {
            const next = Math.min(shown + 200, tasks.length);
            for (; shown < next; shown++) {
                const task = tasks[shown];
                const row = body.insertRow(); row.title = task.upid;
                let seconds = Math.max(0, (task.endtime || Math.floor(Date.now() / 1000)) - task.starttime);
                const duration = this._pbsDuration(seconds);
                for (const text of [task.label || task.worker_type + ' · ' + (task.worker_id || ''), task.start_label || new Date(task.starttime * 1000).toLocaleString(), duration, task.status === 'RUNNING' ? 'Läuft gerade' : task.status || 'Unbekannt']) { row.insertCell().textContent = text; }
                const taskLink = document.createElement('button'); taskLink.type = 'button'; taskLink.className = 'pbs-task-button'; taskLink.textContent = row.cells[0].textContent; taskLink.title = 'Task-Log öffnen'; row.cells[0].replaceChildren(taskLink); taskLink.addEventListener('click', () => this._pbsOpenLog(dialog, content, task, context));
            }
            more.hidden = shown >= tasks.length;
        };
        more.addEventListener('click', appendRows); appendRows();
        const content = document.createElement('div'); content.className = 'pbs-dialog-content';
        content.append(note, table, more);
        dialog.append(heading, content);
        if (!tasks.length) { const empty = document.createElement('p'); empty.textContent = 'Keine Tasks in dieser Kategorie.'; content.append(empty); }
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
        const title = document.createElement('h3'); title.textContent = 'Task-Log · ' + (task.label || task.worker_type);
        const id = document.createElement('div'); id.className = 'pbs-meta'; id.textContent = task.upid;
        const toolbar = document.createElement('div'); toolbar.className = 'pbs-log-toolbar';
        const reload = document.createElement('button'); reload.type = 'button'; reload.textContent = 'Neu laden';
        const more = document.createElement('button'); more.type = 'button'; more.textContent = 'Weitere Log-Zeilen'; more.hidden = true;
        const status = document.createElement('span'); status.setAttribute('role', 'status');
        const pre = document.createElement('pre'); pre.className = 'pbs-log-text'; pre.tabIndex = 0;
        toolbar.append(reload, more, status); section.append(title, id, toolbar, pre); content.prepend(section);
        let next = 1; let count = 0; let byteCount = 0;
        const load = async reset => {
            this._pbsLogAbort?.abort();
            const controller = new AbortController(); this._pbsLogAbort = controller;
            if (reset) { next = 1; count = 0; byteCount = 0; pre.textContent = ''; }
            reload.disabled = true; more.disabled = true; status.textContent = 'Log wird geladen …';
            const timeout = setTimeout(() => controller.abort(), 20000);
            try {
                if (!context?.dataset.pbsHostid || !context.dataset.pbsLogAction) { throw new Error('Widget bitte aktualisieren; Host-Zuordnung fehlt.'); }
                const url = new URL('zabbix.php', window.location.href); url.searchParams.set('action', context.dataset.pbsLogAction);
                const response = await fetch(url, {method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({hostid: context.dataset.pbsHostid, upid: task.upid, start: next}), signal: controller.signal});
                if (!response.ok) { throw new Error('Log-Anfrage fehlgeschlagen (HTTP ' + response.status + ').'); }
                const result = await response.json();
                if (result.error) { throw new Error((result.error.messages || ['Log nicht verfügbar.']).join(' ')); }
                if (!Array.isArray(result.lines) || !Number.isInteger(result.next) || result.next < next || (result.more && result.next === next)) { throw new Error('Ungültige Antwort; Anmeldung und Modulaktivierung prüfen.'); }
                if (controller.signal.aborted || !dialog.isConnected || !section.isConnected) { return; }
                const chunk = result.lines.map(line => line.n + '  ' + line.t).join('\n');
                if (byteCount + chunk.length > 8000000 || count + result.lines.length > 20000) { more.hidden = true; throw new Error('Anzeigegrenze erreicht (20.000 Zeilen / 8 Mio. Zeichen). Vollständiges Log in PBS öffnen.'); }
                pre.append(document.createTextNode((count && chunk ? '\n' : '') + chunk));
                byteCount += chunk.length; count += result.lines.length; next = result.next; more.hidden = !result.more;
                status.textContent = count + ' von ' + result.total + ' Zeilen' + (result.active ? ' · Task läuft, zum Aktualisieren neu laden' : '');
                if (!count) { status.textContent += ' · Noch keine Log-Zeilen vorhanden'; }
            }
            catch (error) {
                if (section.isConnected && this._pbsLogAbort === controller) { status.textContent = error.name === 'AbortError' ? 'Zeitüberschreitung. Bitte erneut laden.' : error instanceof SyntaxError ? 'Keine gültige Antwort. Zabbix-Anmeldung und Modulaktivierung prüfen.' : error.message; }
            }
            finally { clearTimeout(timeout); if (this._pbsLogAbort === controller) { reload.disabled = false; more.disabled = false; } }
        };
        reload.addEventListener('click', () => load(true)); more.addEventListener('click', () => load(false));
        load(true);
    }
}
