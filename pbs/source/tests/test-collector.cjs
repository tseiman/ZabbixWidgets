const fs = require('fs');
const assert = require('assert/strict');
const vm = require('vm');
const now = 1800000000;
const task = (id, start, end, status = 'OK') => ({upid: id, node: 'pbs', worker_type: 'backup', worker_id: 'store:vm/111', starttime: start, endtime: end, status});
function run(file, tasks, code = 200, audit = true) {
    const urls = [];
    const context = {value: JSON.stringify({host: 'test', port: '8007', token: 'audit', secret: 'test', lookback: 48}), Date: {now: () => now * 1000}, HttpRequest: class {addHeader() {} get(url) {urls.push(url); return JSON.stringify({data: url.includes('/access/permissions') ? {'/system/tasks': audit ? {'Sys.Audit': 0} : {}} : tasks});} getStatus() {return code;}}};
    const output = JSON.parse(vm.runInNewContext('(function(){' + fs.readFileSync(__dirname + '/../collector/' + file, 'utf8') + '})()', context));
    return {output, urls};
}
let {output, urls} = run('collector.js', [task('long', now - 500000, now - 20), task('old', now - 900000, now - 200000), task('live', now - 10, undefined), task('long', now - 500000, now - 20), task('warn', now - 100, now - 30, 'WARNINGS: 1')]);
assert.equal(output.shards.length, 16);
assert.equal(output.shards.flatMap(s => s.tasks).length, 2);
assert(output.shards.flatMap(s => s.tasks).some(t => t.upid === 'long'));
assert(!urls[1].includes('since='));
assert(urls[1].includes('limit=0'));
assert.throws(() => run('collector.js', [], 200, false));
assert.throws(() => run('collector.js', [], 403));
assert.throws(() => run('collector.js', [task('huge', now - 100, now - 1, 'x'.repeat(61000))]));
const empty = run('collector.js', []).output;
assert(empty.shards.every(s => s.tasks.length === 0 && s.until === now));
const live = run('running.js', [task('live', now - 100, undefined), task('finished', now - 100, now - 1)]);
assert.equal(live.output.tasks.length, 1);
assert(live.urls[1].includes('running=true'));
assert.throws(() => run('running.js', [], 500));
console.log('Collector tests passed: long tasks, deduplication, limits, errors, empty success, running filter.');
