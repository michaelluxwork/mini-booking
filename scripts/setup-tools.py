from pathlib import Path
import urllib.request, json, hashlib, zipfile, subprocess
root = Path(__file__).resolve().parents[1]
tools = root / '.tools'
tools.mkdir(exist_ok=True)
def download(url, target):
    with urllib.request.urlopen(url, timeout=90) as response:
        target.write_bytes(response.read())
metadata = json.load(urllib.request.urlopen('https://downloads.php.net/~windows/releases/releases.json', timeout=30))['8.4']
release = metadata['nts-vs17-x64']['zip']
archive = tools / release['path']
download('https://downloads.php.net/~windows/releases/' + release['path'], archive)
assert hashlib.sha256(archive.read_bytes()).hexdigest() == release['sha256'], 'PHP checksum mismatch'
phpdir = tools / 'php'
phpdir.mkdir(exist_ok=True)
with zipfile.ZipFile(archive) as z:
    for member in z.infolist():
        assert (phpdir / member.filename).resolve().is_relative_to(phpdir.resolve())
    z.extractall(phpdir)
archive.unlink()
ini = (phpdir / 'php.ini-development').read_text()
ini += '\n' + '\n'.join(['extension_dir="' + str(phpdir / 'ext').replace('\\','/') + '"'] + ['extension=' + e for e in ['curl','fileinfo','mbstring','openssl','pdo_sqlite','sqlite3','zip','intl']] + ['date.timezone=Europe/Moscow','memory_limit=512M']) + '\n'
cacert = tools / 'cacert.pem'
download('https://curl.se/ca/cacert.pem', cacert)
ini += 'curl.cainfo="' + cacert.as_posix() + '"\nopenssl.cafile="' + cacert.as_posix() + '"\n'
(phpdir / 'php.ini').write_text(ini)
installer = tools / 'composer-setup.php'
download('https://getcomposer.org/installer', installer)
expected = urllib.request.urlopen('https://composer.github.io/installer.sig', timeout=30).read().decode().strip()
assert hashlib.sha384(installer.read_bytes()).hexdigest() == expected, 'Composer installer checksum mismatch'
php = str(phpdir / 'php.exe')
subprocess.run([php, str(installer), '--install-dir=' + str(tools), '--filename=composer.phar'], check=True)
installer.unlink()
subprocess.run([php, '--version'], check=True)
subprocess.run([php, str(tools / 'composer.phar'), '--version'], check=True)
(tools / 'provenance.json').write_text(json.dumps({'php_version':metadata['version'], 'php_sha256':release['sha256'], 'composer_installer_sha384':expected, 'sources':['https://downloads.php.net/~windows/releases/releases.json','https://getcomposer.org/installer','https://composer.github.io/installer.sig','https://curl.se/ca/cacert.pem']},indent=2))
