/* UniFi Topology widget, Zabbix 7.4. No external libraries or network requests. */
class UniFiTopologyView {
    constructor(root) {
        this.root = root;
        this.selected = null;
        this.data = null;
        this.zoom = 1;
        this.expired = false;
        this.trafficDialog = null;
        this.dismissedWarnings = new Set();
        this.root.classList.add('ut-root');
    }
    el(tag, text, cls) {
        const node = document.createElement(tag);
        if (text !== undefined && text !== null) node.textContent = String(text);
        if (cls) node.className = cls;
        return node;
    }
    svg(tag, attrs = {}, text = null) {
        const node = document.createElementNS('http://www.w3.org/2000/svg', tag);
        for (const [key, value] of Object.entries(attrs)) node.setAttribute(key, String(value));
        if (text !== null) node.textContent = String(text);
        return node;
    }
    setData(data) {
        this._closeTraffic();
        this.data = data;
        this.failed = false;
        if (!data.nodes.some(node => node.mac === this.selected)) this.selected = null;
        this.expired = this.isExpired();
        this.render();
    }
    showError(message) {
        this._closeTraffic();
        this.failed = true;
        this.data = null;
        this.root.replaceChildren(this.el('div', message, 'ut-alert'));
    }
    isExpired() {
        return this.data && (Date.now() / 1000 - this.data.checked_at > this.data.max_age);
    }
    tick() {
        if (this.data && this.isExpired() !== this.expired) {
            this.expired = this.isExpired();
            this.render();
        }
    }
    status(node) {
        if (this.expired) return {color: '#94a3b8', label: 'Status veraltet', severity: -1};
        if (node.problems.length) {
            const severity = Math.max(...node.problems.map(problem => problem.severity));
            const configured = this.data.severities[severity] || {name: 'Problem', color: '#e45959'};
            const color = /^#[0-9a-f]{6}$/i.test(configured.color) ? configured.color : '#e45959';
            return {color, label: configured.name + ' · ' + node.problems.length, severity};
        }
        if (node.manual && !node.hostid) return {color: '#94a3b8', label: 'Manuell · Status unbekannt', severity: -1};
        if (!node.hostid || !node.monitored) return {color: '#94a3b8', label: node.hostid ? 'Monitoring deaktiviert' : 'Status unbekannt', severity: -1};
        return {color: '#2c9975', label: 'Keine offenen Probleme', severity: -1};
    }
    layout(nodes, edges) {
        const ids = new Set(nodes.map(node => node.mac));
        const children = new Map(nodes.map(node => [node.mac, new Set()]));
        const parents = new Map(nodes.map(node => [node.mac, new Set()]));
        edges.forEach(edge => {
            if (ids.has(edge.up) && ids.has(edge.down)) {
                children.get(edge.up).add(edge.down);
                parents.get(edge.down).add(edge.up);
            }
        });
        const byId = new Map(nodes.map(node => [node.mac, node]));
        const compare = (a, b) => byId.get(a).name.localeCompare(byId.get(b).name) || a.localeCompare(b);
        const roots = nodes.filter(node => parents.get(node.mac).size === 0).map(node => node.mac).sort(compare);
        const queue = roots.map(mac => [mac, 0]);
        const levels = new Map();
        while (levels.size < nodes.length) {
            if (!queue.length) queue.push([[...ids].filter(mac => !levels.has(mac)).sort(compare)[0], 0]);
            const [mac, level] = queue.shift();
            if (levels.has(mac)) continue;
            levels.set(mac, level);
            [...children.get(mac)].sort(compare).forEach(child => {
                if (!levels.has(child)) queue.push([child, level + 1]);
            });
        }
        const rows = new Map();
        for (const [mac, level] of levels) {
            if (!rows.has(level)) rows.set(level, []);
            rows.get(level).push(mac);
        }
        const width = Math.max(780, ...[...rows.values()].map(row => row.length * 285 + 80));
        const points = new Map();
        for (const [level, row] of rows) {
            const score = mac => { const xs = [...parents.get(mac)].filter(id => points.has(id)).map(id => points.get(id).x); return xs.length ? xs.reduce((a, b) => a + b, 0) / xs.length : width / 2; };
            row.sort((a, b) => score(a) - score(b) || compare(a, b)).forEach((mac, i) => points.set(mac, {x: (i + 0.5) * width / row.length, y: 36 + level * 185}));
        }
        return {points, width, height: Math.max(250, rows.size * 185 + 20)};
    }
    button(label, action) {
        const button = this.el('button', label, 'ut-button');
        button.type = 'button';
        button.addEventListener('click', action);
        return button;
    }
    _trafficPoints(node) {
        return ['rx', 'tx'].flatMap(direction => node.traffic?.[direction]?.points || []);
    }
    _formatBits(value) {
        if (!Number.isFinite(Number(value))) return '–';
        const units = ['bit/s', 'Kbit/s', 'Mbit/s', 'Gbit/s', 'Tbit/s'];
        let number = Math.max(0, Number(value));
        let unit = 0;
        while (number >= 1000 && unit < units.length - 1) {
            number /= 1000;
            unit++;
        }
        const digits = number >= 100 || unit === 0 ? 0 : number >= 10 ? 1 : 2;
        return number.toLocaleString(undefined, {maximumFractionDigits: digits}) + ' ' + units[unit];
    }
    _niceMaximum(value) {
        if (!Number.isFinite(value) || value <= 0) return 1;
        const power = Math.pow(10, Math.floor(Math.log10(value)));
        const normalized = value / power;
        const nice = normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10;
        return nice * power;
    }
    _seriesPath(points, dimensions, period, maximum) {
        const usable = (points || [])
            .map(point => [Number(point[0]), Number(point[1])])
            .filter(point => Number.isFinite(point[0]) && Number.isFinite(point[1])
                && point[0] >= period.from && point[0] <= period.to)
            .sort((a, b) => a[0] - b[0]);
        if (!usable.length) return '';
        const deltas = usable.slice(1).map((point, index) => point[0] - usable[index][0]).filter(value => value > 0).sort((a, b) => a - b);
        const typical = deltas.length ? deltas[Math.floor(deltas.length / 2)] : period.to - period.from;
        const gap = Math.max(typical * 3, (period.to - period.from) / 320 * 3);
        let previous = null;
        return usable.map(point => {
            const x = dimensions.left + (point[0] - period.from) / (period.to - period.from) * dimensions.width;
            const y = dimensions.top + dimensions.height - Math.min(point[1], maximum) / maximum * dimensions.height;
            const command = previous === null || point[0] - previous > gap ? 'M' : 'L';
            previous = point[0];
            return command + x.toFixed(2) + ' ' + y.toFixed(2);
        }).join(' ');
    }
    _seriesAreaPaths(points, dimensions, period, maximum) {
        const usable = (points || [])
            .map(point => [Number(point[0]), Number(point[1])])
            .filter(point => Number.isFinite(point[0]) && Number.isFinite(point[1])
                && point[0] >= period.from && point[0] <= period.to)
            .sort((a, b) => a[0] - b[0]);
        if (!usable.length) return [];
        const deltas = usable.slice(1).map((point, index) => point[0] - usable[index][0]).filter(value => value > 0).sort((a, b) => a - b);
        const typical = deltas.length ? deltas[Math.floor(deltas.length / 2)] : period.to - period.from;
        const gap = Math.max(typical * 3, (period.to - period.from) / 320 * 3);
        const segments = [];
        let segment = [];
        let previous = null;
        for (const point of usable) {
            if (previous !== null && point[0] - previous > gap) {
                segments.push(segment);
                segment = [];
            }
            segment.push({
                x: dimensions.left + (point[0] - period.from) / (period.to - period.from) * dimensions.width,
                y: dimensions.top + dimensions.height - Math.min(point[1], maximum) / maximum * dimensions.height
            });
            previous = point[0];
        }
        if (segment.length) segments.push(segment);
        const baseline = dimensions.top + dimensions.height;
        return segments.map(pointsInSegment => {
            const first = pointsInSegment[0];
            const last = pointsInSegment[pointsInSegment.length - 1];
            const line = pointsInSegment.map(point => 'L' + point.x.toFixed(2) + ' ' + point.y.toFixed(2)).join(' ');
            return 'M' + first.x.toFixed(2) + ' ' + baseline.toFixed(2) + ' '
                + line + ' L' + last.x.toFixed(2) + ' ' + baseline.toFixed(2) + ' Z';
        });
    }
    _seriesMarker(points, dimensions, period, maximum) {
        const usable = (points || [])
            .map(point => [Number(point[0]), Number(point[1])])
            .filter(point => Number.isFinite(point[0]) && Number.isFinite(point[1])
                && point[0] >= period.from && point[0] <= period.to)
            .sort((a, b) => a[0] - b[0]);
        if (!usable.length) return null;
        const point = usable[usable.length - 1];
        return {
            x: dimensions.left + (point[0] - period.from) / (period.to - period.from) * dimensions.width,
            y: dimensions.top + dimensions.height - Math.min(point[1], maximum) / maximum * dimensions.height
        };
    }
    _miniTraffic(node, point) {
        const period = this.data?.traffic_period;
        if (!period) return null;
        const values = this._trafficPoints(node).map(point => Number(point[1])).filter(Number.isFinite);
        const maximum = this._niceMaximum(Math.max(1, ...values));
        const dimensions = {left: 2, top: 2, width: 68, height: 26};
        const mini = this.svg('g', {
            transform: 'translate(' + (point.x + 39) + ',' + (point.y + 7) + ')',
            class: 'ut-traffic-mini',
            tabindex: 0,
            role: 'button',
            'data-traffic-mac': node.mac,
            'aria-label': node.name + ': Traffic-Diagramm vergrößern'
        });
        const available = values.length > 0;
        mini.append(this.svg('title', {}, available
            ? 'RX ' + this._formatBits(node.traffic?.rx?.latest) + ' · TX ' + this._formatBits(node.traffic?.tx?.latest) + ' · zum Vergrößern anklicken'
            : node.name + ': Für diesen Zeitraum sind keine Traffic-Daten verfügbar.'));
        mini.append(this.svg('rect', {width: 72, height: 30, rx: 5, class: 'ut-traffic-mini-bg'}));
        for (const direction of ['rx', 'tx']) {
            for (const area of this._seriesAreaPaths(node.traffic?.[direction]?.points, dimensions, period, maximum)) {
                mini.append(this.svg('path', {d: area, class: 'ut-traffic-area ut-traffic-area-' + direction}));
            }
        }
        const rx = this._seriesPath(node.traffic?.rx?.points, dimensions, period, maximum);
        const tx = this._seriesPath(node.traffic?.tx?.points, dimensions, period, maximum);
        if (rx) mini.append(this.svg('path', {d: rx, class: 'ut-traffic-line ut-traffic-rx'}));
        if (tx) mini.append(this.svg('path', {d: tx, class: 'ut-traffic-line ut-traffic-tx'}));
        for (const [direction, css] of [['rx', 'ut-traffic-marker-rx'], ['tx', 'ut-traffic-marker-tx']]) {
            const marker = this._seriesMarker(node.traffic?.[direction]?.points, dimensions, period, maximum);
            if (marker) mini.append(this.svg('circle', {cx: marker.x, cy: marker.y, r: 1.8, class: css}));
        }
        const activate = event => {
            event.preventDefault();
            event.stopPropagation();
            this._openTraffic(node, mini);
        };
        mini.addEventListener('click', activate);
        mini.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') activate(event);
        });
        return mini;
    }
    _closeTraffic() {
        const dialog = this.trafficDialog;
        this.trafficDialog = null;
        if (dialog?.isConnected) {
            if (dialog.open) dialog.close();
            dialog.remove();
        }
    }
    _openTraffic(node, activator = null) {
        this._closeTraffic();
        if (!this.data?.traffic_period) return;

        const dialog = document.createElement('dialog');
        dialog.className = 'ut-traffic-dialog';
        dialog.setAttribute('aria-labelledby', 'ut-traffic-title-' + node.mac.replaceAll(':', ''));
        const header = this.el('div', null, 'ut-traffic-dialog-header');
        const heading = this.el('div');
        const title = this.el('h3', node.name + ' · RX/TX');
        title.id = 'ut-traffic-title-' + node.mac.replaceAll(':', '');
        heading.append(title, this.el('p', 'Gesamtdurchsatz aller eindeutigen physischen Ports', 'ut-traffic-subtitle'));
        const close = this.button('Schließen', () => dialog.close());
        close.classList.add('ut-traffic-close');
        header.append(heading, close);
        dialog.append(header);

        const legend = this.el('div', null, 'ut-traffic-legend');
        const rxLegend = this.el('span');
        rxLegend.append(this.el('i', null, 'ut-traffic-dot ut-traffic-dot-rx'), document.createTextNode('RX ' + this._formatBits(node.traffic?.rx?.latest)));
        const txLegend = this.el('span');
        txLegend.append(this.el('i', null, 'ut-traffic-dot ut-traffic-dot-tx'), document.createTextNode('TX ' + this._formatBits(node.traffic?.tx?.latest)));
        legend.append(rxLegend, txLegend);
        dialog.append(legend);

        const period = this.data.traffic_period;
        const all = this._trafficPoints(node).map(point => Number(point[1])).filter(Number.isFinite);
        if (!all.length) {
            dialog.append(this.el('p', 'Für diesen Zeitraum sind keine Traffic-Daten verfügbar.', 'ut-traffic-empty'));
        }
        else {
            const maximum = this._niceMaximum(Math.max(1, ...all));
            const view = {width: 900, height: 380};
            const dimensions = {left: 82, top: 18, width: 792, height: 300};
            const graph = this.svg('svg', {viewBox: '0 0 ' + view.width + ' ' + view.height, role: 'img',
                'aria-label': 'RX- und TX-Durchsatz von ' + node.name});
            graph.classList.add('ut-traffic-chart');
            for (let i = 0; i <= 5; i++) {
                const y = dimensions.top + dimensions.height * i / 5;
                const value = maximum * (1 - i / 5);
                graph.append(this.svg('line', {x1: dimensions.left, y1: y, x2: dimensions.left + dimensions.width, y2: y, class: 'ut-traffic-grid'}));
                graph.append(this.svg('text', {x: dimensions.left - 10, y: y + 4, 'text-anchor': 'end', class: 'ut-traffic-axis'}, this._formatBits(value)));
            }
            const duration = period.to - period.from;
            for (let i = 0; i <= 5; i++) {
                const clock = period.from + duration * i / 5;
                const x = dimensions.left + dimensions.width * i / 5;
                const date = new Date(clock * 1000);
                const label = duration > 172800
                    ? date.toLocaleDateString(undefined, {month: 'short', day: '2-digit'})
                    : date.toLocaleTimeString(undefined, {hour: '2-digit', minute: '2-digit'});
                graph.append(this.svg('text', {x, y: dimensions.top + dimensions.height + 28, 'text-anchor': i === 0 ? 'start' : i === 5 ? 'end' : 'middle', class: 'ut-traffic-axis'}, label));
            }
            for (const direction of ['rx', 'tx']) {
                for (const area of this._seriesAreaPaths(node.traffic?.[direction]?.points, dimensions, period, maximum)) {
                    graph.append(this.svg('path', {d: area, class: 'ut-traffic-area ut-traffic-area-' + direction}));
                }
            }
            const rx = this._seriesPath(node.traffic?.rx?.points, dimensions, period, maximum);
            const tx = this._seriesPath(node.traffic?.tx?.points, dimensions, period, maximum);
            if (rx) graph.append(this.svg('path', {d: rx, class: 'ut-traffic-line ut-traffic-rx ut-traffic-line-large'}));
            if (tx) graph.append(this.svg('path', {d: tx, class: 'ut-traffic-line ut-traffic-tx ut-traffic-line-large'}));
            for (const [direction, css] of [['rx', 'ut-traffic-marker-rx'], ['tx', 'ut-traffic-marker-tx']]) {
                const marker = this._seriesMarker(node.traffic?.[direction]?.points, dimensions, period, maximum);
                if (marker) graph.append(this.svg('circle', {cx: marker.x, cy: marker.y, r: 3.5, class: css}));
            }
            const graphScroll = this.el('div', null, 'ut-traffic-chart-scroll');
            graphScroll.append(graph);
            dialog.append(graphScroll);
        }

        const source = direction => node.traffic?.[direction]
            ? (node.traffic[direction].source === 'direct' ? 'Controller-Port-Raten' : 'aus Port-Zählerdifferenzen')
            : 'keine Daten';
        const from = new Date(period.from * 1000).toLocaleString();
        const to = new Date(period.to * 1000).toLocaleString();
        dialog.append(this.el('p', from + ' – ' + to + ' · RX: ' + source('rx') + ' · TX: ' + source('tx'), 'ut-traffic-footnote'));

        dialog.addEventListener('close', () => {
            if (this.trafficDialog === dialog) this.trafficDialog = null;
            dialog.remove();
            if (activator?.isConnected) activator.focus();
        });
        dialog.addEventListener('click', event => {
            if (event.target === dialog) {
                const box = dialog.getBoundingClientRect();
                if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.close();
            }
        });
        this.trafficDialog = dialog;
        document.body.append(dialog);
        dialog.showModal();
        close.focus();
    }
    _dismissibleWarning(warning) {
        const notice = this.el('div', null, 'ut-notice ut-notice-dismissible');
        notice.append(this.el('span', warning));
        const close = this.el('button', '×', 'ut-notice-close');
        close.type = 'button';
        close.setAttribute('aria-label', 'Warnung schließen');
        close.title = 'Warnung schließen';
        close.addEventListener('click', () => {
            this.dismissedWarnings.add(warning);
            notice.remove();
        });
        notice.append(close);
        return notice;
    }
    render() {
        const oldCanvas = this.root.querySelector('.ut-canvas');
        const scroll = oldCanvas ? [oldCanvas.scrollLeft, oldCanvas.scrollTop] : [0, 0];
        this.root.replaceChildren();
        const data = this.data;
        if (!data) return;
        const devices = data.nodes.filter(node => node.type === 'DEVICE');
        const index = new Map(data.nodes.map(node => [node.mac, node]));
        const deviceIds = new Set(devices.map(node => node.mac));
        const edges = data.edges.filter(edge => deviceIds.has(edge.up) && deviceIds.has(edge.down));
        const toolbar = this.el('div', null, 'ut-toolbar');
        const heading = this.el('div');
        heading.append(this.el('strong', devices.length + ' Netzwerkgeräte', 'ut-title'));
        const mapped = devices.filter(node => node.hostid).length;
        heading.append(this.el('span', mapped + '/' + devices.length + ' Hosts zugeordnet · ' + edges.length + ' Verbindungen', 'ut-subtitle'));
        toolbar.append(heading);
        const controls = this.el('div', null, 'ut-controls');
        controls.append(this.button('−', () => this.setZoom(this.zoom - 0.2)), this.button('Anpassen', () => this.setZoom(1)), this.button('+', () => this.setZoom(this.zoom + 0.2)));
        controls.children[0].setAttribute('aria-label', 'Verkleinern');
        controls.children[2].setAttribute('aria-label', 'Vergrößern');
        toolbar.append(controls);
        this.root.append(toolbar);
        if (data.demo) this.root.append(this.el('div', 'DEMO · Älterer Topologie-Schnappschuss. Alle Problemstände sind simuliert, kein Live-Status.', 'ut-notice'));
        if (this.expired) this.root.append(this.el('div', 'Keine aktuellen Statusdaten. Die Anzeige ist veraltet; Dashboard-Aktualisierung prüfen.', 'ut-alert'));
        for (const warning of data.warnings) {
            if (!this.dismissedWarnings.has(warning)) this.root.append(this._dismissibleWarning(warning));
        }
        if (!devices.length) {
            this.root.append(this.el('p', 'Keine Knoten mit type=DEVICE in der Antwort.'));
            return;
        }
        const graph = this.layout(devices, edges);
        const canvas = this.el('div', null, 'ut-canvas');
        const svg = this.svg('svg', {viewBox: '0 0 ' + graph.width + ' ' + graph.height, role: 'group', 'aria-label': 'UniFi Netzwerkübersicht'});
        svg.style.width = (100 * this.zoom) + '%';
        svg.style.minWidth = (Math.min(graph.width, 760) * this.zoom) + 'px';
        const paths = this.svg('g');
        const labels = this.svg('g');
        for (const edge of edges) {
            const a = graph.points.get(edge.up);
            const b = graph.points.get(edge.down);
            const y1 = a.y + 96, y2 = b.y;
            const mid = (y1 + y2) / 2;
            const path = this.svg('path', {d: 'M ' + a.x + ' ' + y1 + ' C ' + a.x + ' ' + mid + ' ' + b.x + ' ' + mid + ' ' + b.x + ' ' + y2, fill: 'none', stroke: '#8594a8', 'stroke-width': 2});
            if (edge.type === 'WIRELESS' || edge.manual) path.setAttribute('stroke-dasharray', edge.manual ? '9 5' : '3 4');
            if (edge.manual) path.setAttribute('stroke', '#8873b2');
            path.append(this.svg('title', {}, this.edgeText(edge, index)));
            paths.append(path);
            const label = edge.manual ? 'Manuell: ' + this.short(edge.label || 'Verbindung', 18) : 'P' + (edge.uplinkPortNumber ?? '?') + ' → P' + (edge.downlinkPortNumber ?? '?');
            const labelWidth = edge.manual ? 170 : 106;
            const x = (a.x + b.x) / 2;
            labels.append(this.svg('rect', {x: x - labelWidth / 2, y: mid - 10, width: labelWidth, height: 21, rx: 6, class: 'ut-link-label-bg'}));
            labels.append(this.svg('text', {x, y: mid + 4, 'text-anchor': 'middle', class: 'ut-link-label'}, label));
        }
        svg.append(paths, labels);
        for (const node of devices) {
            const point = graph.points.get(node.mac);
            const status = this.status(node);
            const selected = this.selected === node.mac;
            const group = this.svg('g', {transform: 'translate(' + (point.x - 125) + ',' + point.y + ')', tabindex: 0, role: 'button', 'aria-label': node.name + ', ' + status.label + ', Endgeräte und Probleme anzeigen', 'aria-expanded': selected ? 'true' : 'false', 'data-mac': node.mac, class: 'ut-node' + (selected ? ' ut-selected' : '')});
            group.append(this.svg('rect', {width: 250, height: 96, rx: 12, class: 'ut-node-bg', stroke: status.color, 'stroke-width': status.severity >= 0 ? 4 : 2}));
            group.append(this.svg('circle', {cx: 19, cy: 24, r: 5, fill: status.color}));
            group.append(this.svg('text', {x: 32, y: 29, class: 'ut-node-name'}, this.short(node.name, 13)));
            group.append(this.svg('text', {x: 14, y: 55, class: 'ut-node-model'}, (node.manual ? 'Manuell ergänzt' : node.wireless ? 'Access Point' : 'Netzwerkgerät') + (node.model ? ' · ' + node.model : '')));
            group.append(this.svg('text', {x: 14, y: 80, class: 'ut-node-status'}, status.label));
            group.append(this.svg('title', {}, node.name + '\n' + node.mac + '\n' + status.label + '\n' + (node.host_name || node.mapping)));
            const traffic = this._miniTraffic(node, point);
            const activate = () => {
                this.selected = this.selected === node.mac ? null : node.mac;
                this.render();
                const replacement = [...this.root.querySelectorAll('[data-mac]')].find(el => el.getAttribute('data-mac') === node.mac);
                replacement?.focus({preventScroll: true});
            };
            group.addEventListener('click', activate);
            group.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    activate();
                }
            });
            svg.append(group);
            if (traffic) svg.append(traffic);
        }
        canvas.append(svg);
        this.root.append(canvas);
        canvas.scrollLeft = scroll[0];
        canvas.scrollTop = scroll[1];
        const legend = this.el('div', null, 'ut-legend');
        data.severities.forEach(severity => {
            const item = this.el('span', null, 'ut-legend-item');
            const dot = this.el('i');
            dot.style.backgroundColor = /^#[0-9a-f]{6}$/i.test(severity.color) ? severity.color : '#94a3b8';
            item.append(dot, document.createTextNode(severity.name));
            legend.append(item);
        });
        if (edges.some(edge => edge.manual)) legend.append(this.el('span', '╌╌ Manuelle Verbindung', 'ut-legend-item'));
        this.root.append(legend);
        if (this.selected && index.has(this.selected)) this.details(index.get(this.selected), index);
        else this.root.append(this.el('p', 'Gerät anklicken: zugeordnete Endgeräte, Verbindungen und aktuelle Probleme.', 'ut-hint'));
        const footer = this.el('div', null, 'ut-footer');
        footer.append(this.el('span', 'Topologie: ' + new Date(data.clock * 1000).toLocaleString() + (data.stale ? ' · VERALTET' : '')));
        footer.append(this.el('span', 'Problemstand: ' + new Date(data.checked_at * 1000).toLocaleTimeString() + ' · ' + (data.show_suppressed ? 'inkl. unterdrückter Meldungen' : 'ohne unterdrückte Meldungen')));
        this.root.append(footer);
    }
    short(text, length) {
        return text.length <= length ? text : text.slice(0, length - 1) + '…';
    }
    setZoom(value) {
        this.zoom = Math.max(0.6, Math.min(2.4, value));
        this.render();
    }
    edgeText(edge, index) {
        if (edge.manual) return (index.get(edge.up)?.name || edge.up) + ' → ' + (index.get(edge.down)?.name || edge.down) + ' · manuell' + (edge.label ? ': ' + edge.label : '');
        return (index.get(edge.up)?.name || edge.up) + ' P' + (edge.uplinkPortNumber ?? '?') + ' → ' + (index.get(edge.down)?.name || edge.down) + ' P' + (edge.downlinkPortNumber ?? '?') + (edge.rateMbps !== null ? ' · ' + edge.rateMbps + ' Mbit/s' : '');
    }
    details(node, index) {
        const panel = this.el('section', null, 'ut-details');
        const title = this.el('div', null, 'ut-toolbar');
        title.append(this.el('h3', node.name));
        title.append(this.button('Schließen', () => { this.selected = null; this.render(); }));
        panel.append(title, this.el('p', (node.manual ? 'Manuelle ID: ' + node.mac.slice(7) : node.mac) + (node.hostid ? ' · Zabbix-Host ' + node.hostid + ': ' + node.host_name : ' · ' + node.mapping), 'ut-meta'));
        if (node.hostid) panel.append(this.el('p', node.mapping + (node.maintenance ? ' · Wartung aktiv' : ''), 'ut-meta'));
        if (node.traffic) {
            const trafficSummary = this.el('div', null, 'ut-traffic-summary');
            trafficSummary.append(
                this.el('span', 'RX ' + this._formatBits(node.traffic.rx?.latest) + ' · TX ' + this._formatBits(node.traffic.tx?.latest)),
                this.button('Traffic vergrößern', event => this._openTraffic(node, event.currentTarget))
            );
            panel.append(trafficSummary);
        }
        panel.append(this.el('h4', 'Probleme'));
        if (this.expired) panel.append(this.el('p', 'Problemstatus ist veraltet.'));
        if (!node.problems.length) panel.append(this.el('p', node.hostid && node.monitored ? 'Keine offenen, für dich sichtbaren Probleme im gewählten Filter.' : 'Kein aktiver, eindeutig zugeordneter Zabbix-Host. Status unbekannt.'));
        const list = this.el('ul', null, 'ut-problems');
        [...node.problems].sort((a, b) => b.severity - a.severity).forEach(problem => {
            const li = this.el('li');
            const severity = this.data.severities[problem.severity];
            const badge = this.el('span', severity?.name || 'Problem', 'ut-badge');
            if (/^#[0-9a-f]{6}$/i.test(severity?.color)) badge.style.backgroundColor = severity.color;
            const link = this.el('a', problem.name);
            if (/^[0-9]+$/.test(problem.triggerid) && /^[0-9]+$/.test(problem.eventid)) {
                link.href = 'tr_events.php?triggerid=' + problem.triggerid + '&eventid=' + problem.eventid;
                if (this.data.demo) link.addEventListener('click', event => { event.preventDefault(); });
            }
            li.append(badge, link, this.el('span', ' · seit ' + new Date(problem.clock * 1000).toLocaleString() + (problem.acknowledged ? ' · bestätigt' : '') + (problem.suppressed ? ' · unterdrückt' : ''), 'ut-meta'));
            list.append(li);
        });
        panel.append(list);
        const clients = this.data.edges.filter(edge => edge.up === node.mac && index.get(edge.down)?.type !== 'DEVICE');
        panel.append(this.el('h4', 'Endgeräte (' + new Set(clients.map(edge => edge.down)).size + ')'));
        if (!clients.length) panel.append(this.el('p', 'Keine Endgeräte unmittelbar diesem Gerät zugeordnet. Endgeräte hinter anderen Switches erscheinen dort.'));
        else {
            const table = this.el('table', null, 'ut-table');
            const header = this.el('tr');
            ['Endgerät', 'MAC', 'Verbindung', 'Details'].forEach(label => header.append(this.el('th', label)));
            const thead = this.el('thead'); thead.append(header); table.append(thead);
            const body = this.el('tbody');
            clients.sort((a, b) => index.get(a.down).name.localeCompare(index.get(b.down).name)).forEach(edge => {
                const row = this.el('tr'), client = index.get(edge.down);
                const details = edge.type === 'WIRELESS'
                    ? [edge.essid, edge.channel !== null ? 'Kanal ' + edge.channel : null, edge.experienceScore !== null ? 'Experience ' + edge.experienceScore : null].filter(value => value !== null).join(' · ')
                    : (edge.rateMbps !== null ? edge.rateMbps + ' Mbit/s' : 'Linkrate unbekannt');
                [client.name, client.mac, edge.type === 'WIRELESS' ? 'WLAN' : 'Port ' + (edge.uplinkPortNumber ?? '?'), details].forEach(value => row.append(this.el('td', value)));
                body.append(row);
            });
            table.append(body);
            const wrap = this.el('div', null, 'ut-table-scroll'); wrap.append(table); panel.append(wrap);
        }
        panel.append(this.el('h4', 'Netzwerkverbindungen'));
        const connections = this.el('ul', null, 'ut-connections');
        this.data.edges.filter(edge => (edge.up === node.mac || edge.down === node.mac) && index.get(edge.up)?.type === 'DEVICE' && index.get(edge.down)?.type === 'DEVICE').forEach(edge => connections.append(this.el('li', this.edgeText(edge, index))));
        if (!connections.children.length) connections.append(this.el('li', 'Keine Verbindung zu einem anderen Netzwerkgerät im JSON.'));
        panel.append(connections);
        this.root.append(panel);
    }
}

class CWidgetUniFiTopology extends CWidget {
    onInitialize() {
        this._utView = null;
        this._utTimer = null;
    }
    onActivate() {
        this._utTimer = setInterval(() => this._utView?.tick(), 10000);
    }
    onDeactivate() {
        clearInterval(this._utTimer);
        this._utTimer = null;
        this._utView?._closeTraffic();
    }
    onDestroy() {
        this.onDeactivate();
        this._utView = null;
    }
    onClearContents() {
        this._utView?._closeTraffic();
        this._utView = null;
    }
    onFeedback({type, value}) {
        if (type === CWidgetsData.DATA_TYPE_TIME_PERIOD && this.getFieldsReferredData().has('time_period')) {
            this._startUpdating();
            this.feedback({time_period: value});
            return true;
        }
        return false;
    }
    setContents(response) {
        if (!this._utView || !this._body.contains(this._utView.root)) {
            const root = document.createElement('div');
            this._body.replaceChildren(root);
            this._utView = new UniFiTopologyView(root);
        }
        if (response.topology_error || !response.topology) this._utView.showError(response.topology_error || 'Keine Topologiedaten erhalten.');
        else this._utView.setData(response.topology);
    }
    processUpdateErrorResponse(error) {
        this._utView?.showError('Status konnte nicht aktualisiert werden. Keine aktuelle Problembewertung verfügbar.');
        super.processUpdateErrorResponse(error);
    }
    promiseUpdate() {
        return super.promiseUpdate().catch(error => {
            if (error.name !== 'AbortError') this._utView?.showError('Verbindung zum Zabbix-Frontend fehlgeschlagen. Status unbekannt.');
            throw error;
        });
    }
}
