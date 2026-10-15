var p = JSON.parse(value);
var req = new HttpRequest();
req.addHeader('Authorization: PBSAPIToken=' + p.token + ':' + p.secret);
var base = 'https://' + p.host + ':' + p.port + '/api2/json';
var permissions = req.get(base + '/access/permissions?path=/system/tasks');
if (req.getStatus() !== 200) { throw 'Cannot verify task audit permissions: HTTP ' + req.getStatus(); }
var acl = JSON.parse(permissions).data;
if (!acl || !acl['/system/tasks'] || !Object.prototype.hasOwnProperty.call(acl['/system/tasks'], 'Sys.Audit')) { throw 'Sys.Audit on /system/tasks required; refusing partial per-user task list'; }
var response = req.get(base + '/nodes/localhost/tasks?running=true&limit=0');
if (req.getStatus() !== 200) { throw 'PBS running query: HTTP ' + req.getStatus(); }
var tasks = JSON.parse(response).data;
if (!Array.isArray(tasks)) { throw 'Invalid running task list'; }
tasks = tasks.filter(function(t) { return !t.endtime; }).map(function(t) {
    if (!t.upid || !t.worker_type || !isFinite(t.starttime)) { throw 'Invalid running task'; }
    return {upid: t.upid, worker_type: t.worker_type, worker_id: t.worker_id || '', starttime: Number(t.starttime), node: t.node || ''};
});
var result = JSON.stringify({version: 1, until: Math.floor(Date.now() / 1000), tasks: tasks});
var size = 0;
for (var i = 0; i < result.length; i++) { size += result.charCodeAt(i) < 127 ? 1 : 6; }
if (size > 60000) { throw 'Running task list exceeds 60000 bytes'; }
return result;
