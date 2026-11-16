from pathlib import Path
import datetime, json, os, shutil, subprocess, time
root = Path(__file__).resolve().parents[1]
clone = root / '.runtime' / ('clean-' + str(time.time_ns()))
php = root / '.tools/php/php.exe'
composer = root / '.tools/composer.phar'
env = os.environ.copy()
env['PATH'] = str(php.parent) + os.pathsep + env['PATH']
# Do not inherit a running server's database or cached configuration.
for key in ['DB_DATABASE', 'APP_CONFIG_CACHE', 'APP_ENV', 'APP_DEBUG']:
    env.pop(key, None)
steps=[]
def run(args, cwd):
    result = subprocess.run([str(a) for a in args], cwd=cwd, env=env, capture_output=True, encoding='utf-8', errors='replace')
    steps.append({'command':[str(a) for a in args], 'exit_code':result.returncode})
    if result.returncode:
        raise RuntimeError(result.stdout[-1800:] + result.stderr[-1800:])
    return result.stdout
revision=run(['git','-c','safe.directory='+root.as_posix(),'rev-parse','HEAD'],root).strip()
run(['git','-c','safe.directory='+root.as_posix(),'clone','--quiet','--no-hardlinks',root,clone],root)
assert not (clone/'backend/.env').exists()
assert not (clone/'backend/database/database.sqlite').exists()
run([php,composer,'install','--no-interaction','--prefer-dist'],clone/'backend')
shutil.copyfile(clone/'backend/.env.example', clone/'backend/.env')
(clone/'backend/database/database.sqlite').touch()
run([php,'artisan','key:generate','--no-interaction'],clone/'backend')
run([php,'artisan','migrate','--seed','--no-interaction'],clone/'backend')
run([php,'artisan','db:seed','--no-interaction'],clone/'backend')
tests=run([php,'artisan','test'],clone/'backend')
run(['npm.cmd','ci','--no-audit','--no-fund'],clone/'frontend')
run(['npm.cmd','run','build'],clone/'frontend')
import sqlite3
with sqlite3.connect(clone/'backend/database/database.sqlite') as db:
    slots=db.execute('select count(*) from slots').fetchone()[0]
    bookings=db.execute('select count(*) from bookings').fetchone()[0]
assert slots==63 and bookings==0
report={'passed':True,'revision':revision,'checked_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'clone':str(clone),'steps':steps,'seeded_slots_after_two_runs':slots,'bookings':bookings,'test_output':tests[-2400:],'uses_shared_portable_php_composer':True,'global_php_install_not_tested':True}
(root/'quality/clean-result.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({'passed':True,'revision':revision,'steps':len(steps),'slots':slots,'bookings':bookings}))
