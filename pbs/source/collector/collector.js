var p = JSON.parse(value);
var req = new HttpRequest();
req.addHeader('Authorization: PBSAPIToken=' + p.token + ':' + p.secret);
var base = 'https://' + p.host + ':' + p.port + '/api2/json';
var permissions = req.get(base + '/access/permissions?path=/system/tasks');
if (req.getStatus() !== 200) { throw 'Cannot verify task audit permissions: HTTP ' + req.getStatus(); }
var acl = JSON.parse(permissions).data;
if (!acl || !acl['/system/tasks'] || !Object.prototype.hasOwnProperty.call(acl['/system/tasks'], 'Sys.Audit')) { throw 'Sys.Audit on /system/tasks required; refusing partial per-user task list'; }
var now = Math.floor(Date.now() / 1000);
var hours = Number(p.lookback);
if (!isFinite(hours) || hours < 1 || hours > 168) { throw 'Dashboard lookback must be 1..168 hours'; }
var since = now - hours * 3600;
// No since filter: PBS also filters by START time, losing long-running jobs.
// limit=0 is the documented unbounded list, avoiding moving offset pagination.
var response = req.get(base + '/nodes/localhost/tasks?limit=0');
if (req.getStatus() !== 200) { throw 'PBS task query: HTTP ' + req.getStatus(); }
if (response.length > 8000000) { throw 'PBS task archive exceeds 8 MB; use an incremental collector'; }
var list = JSON.parse(response).data;
if (!Array.isArray(list)) { throw 'Invalid task list'; }
var shards = [];
for (var i = 0; i < 16; i++) { shards.push({version: 1, since: since, until: now, tasks: []}); }
var seen = {};
list.forEach(function (t) {
    if (!t.endtime || t.endtime < since || t.endtime > now) { return; }
    if (!t.upid || !t.worker_type || !isFinite(t.starttime) || t.starttime > t.endtime) { throw 'Invalid completed task'; }
    if (seen[t.upid]) { return; }
    seen[t.upid] = true;
    var hash = 0;
    for (var j = 0; j < t.upid.length; j++) { hash = ((hash * 31) + t.upid.charCodeAt(j)) >>> 0; }
    shards[hash % 16].tasks.push({upid: t.upid, node: t.node || '', worker_type: t.worker_type, worker_id: t.worker_id || '', starttime: Number(t.starttime), endtime: Number(t.endtime), status: t.status || 'unknown'});
});
shards.forEach(function (s) {
    // ASCII JSON gives a reliable byte bound even for non-ASCII error messages.
    var encoded = JSON.stringify(s);
    var size = 0;
    for (var k = 0; k < encoded.length; k++) { size += encoded.charCodeAt(k) < 127 ? 1 : 6; }
    if (size > 60000) { throw 'Task shard exceeds 60000 bytes; lower lookback or use incremental ingestion'; }
});
return JSON.stringify({shards: shards});
