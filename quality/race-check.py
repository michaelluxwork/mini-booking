from pathlib import Path
import datetime, hashlib, json, os, sqlite3, subprocess, time

root = Path(__file__).resolve().parents[1]
quality = root / 'quality'
php = root / '.tools/php/php.exe'
source_paths = [
    'backend/app/Http/Controllers/BookingController.php',
    'backend/app/Http/Controllers/SlotController.php',
    'backend/app/Models/Booking.php', 'backend/app/Models/Slot.php',
    'backend/bootstrap/app.php', 'backend/config/database.php',
    'backend/routes/api.php',
    'backend/database/migrations/2026_11_16_150000_create_slots_and_bookings.php',
]
def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest() if path.exists() else None

def manifest(): return {p: digest(root / p) for p in source_paths}

started = datetime.datetime.now(datetime.timezone.utc).isoformat()
runtime = root / '.runtime' / ('race-' + str(time.time_ns()))
runtime.mkdir(parents=True)
database = runtime / 'isolated.sqlite'
database.touch()
working_db = root / 'backend/database/database.sqlite'
working_hash_before = digest(working_db)
source_before = manifest()
env = os.environ.copy()
env.update({'APP_ENV':'testing', 'DB_CONNECTION':'sqlite', 'DB_DATABASE':str(database),
            'SESSION_DRIVER':'array', 'CACHE_STORE':'array', 'LOG_CHANNEL':'stderr',
            'APP_DEBUG':'false', 'APP_CONFIG_CACHE':str(runtime/'no-config-cache.php')})
commands = []
def run(command):
    commands.append([str(x) for x in command])
    result = subprocess.run([str(x) for x in command], cwd=root/'backend', env=env, capture_output=True, text=True, encoding='utf-8')
    if result.returncode: raise RuntimeError('command failed: ' + result.stderr[-1000:])
    return result.stdout

run([php, 'artisan', 'migrate', '--force', '--no-interaction'])
with sqlite3.connect(database) as db:
    start = int(time.time()) + 86400
    db.execute('INSERT INTO slots(starts_at,ends_at,created_at,updated_at) VALUES(?,?,?,?)',(start,start+3600,'2026-11-16 00:00:00','2026-11-16 00:00:00'))
    slot_id = db.execute('SELECT id FROM slots').fetchone()[0]

def request(mode,value):
    return json.loads(run([php, quality/'kernel-worker.php', mode, str(value), '']))

rounds = []
for number in range(3):
    gate = runtime / ('gate-'+str(number))
    children=[]
    command=[str(php), str(quality/'kernel-worker.php'),'create',str(slot_id),str(gate)]
    commands.append(command)
    for _ in range(4):
        children.append(subprocess.Popen(command,cwd=root/'backend',env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True,encoding='utf-8'))
    deadline=time.monotonic()+15
    while len(list(runtime.glob(gate.name+'.*.ready'))) < 4:
        if time.monotonic()>deadline:
            for child in children: child.kill()
            raise RuntimeError('workers did not reach barrier')
        time.sleep(.01)
    gate.write_text('go')
    responses=[]
    for child in children:
        out,err=child.communicate(timeout=20)
        if child.returncode: raise RuntimeError('worker failed: '+err[-1000:])
        responses.append(json.loads(out))
    statuses=sorted(x['status'] for x in responses)
    with sqlite3.connect(database) as db:
        count=db.execute('SELECT COUNT(*) FROM bookings WHERE slot_id=?',(slot_id,)).fetchone()[0]
    persisted=request('list',slot_id)
    booking_id=next(x['booking_id'] for x in responses if x['status']==201)
    deleted=request('delete',booking_id)
    deleted_again=request('delete',booking_id)
    after_cancel=request('list',slot_id)
    passed=statuses==[201,409,409,409] and count==1 and persisted['slot_status']=='booked' and persisted['booking_id']==booking_id and deleted['status']==204 and deleted_again['status']==204 and after_cancel['slot_status']=='available'
    rounds.append({'round':number+1,'http_statuses':statuses,'active_bookings':count,
                   'new_process_sees_booking':persisted['slot_status']=='booked',
                   'cancellation_statuses':[deleted['status'],deleted_again['status']],
                   'new_process_sees_free_slot':after_cancel['slot_status']=='available','passed':passed})
    if not passed: raise AssertionError(rounds[-1])
source_after=manifest()
working_hash_after=digest(working_db)
result={'started_at_utc':started,'finished_at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),
        'kind':'real multi-process Laravel HTTP kernel, no listener',
        'workers_per_round':4,'rounds':rounds,'source_sha256':source_before,
        'source_unchanged_during_test':source_before==source_after,
        'working_database_unchanged':working_hash_before==working_hash_after,
        'isolated_database':str(database.relative_to(root)),
        'commands':commands,
        'passed':all(r['passed'] for r in rounds) and source_before==source_after and working_hash_before==working_hash_after}
(quality/'race-result.json').write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({'passed':result['passed'],'rounds':rounds,'working_database_unchanged':result['working_database_unchanged'],'source_unchanged':result['source_unchanged_during_test']},ensure_ascii=False))
if not result['passed']: raise SystemExit(1)
