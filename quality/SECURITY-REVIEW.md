# Независимый security review

Вердикт: PASS для локального синтетического демо.
Ревизия: 1abccce990101317f7f3237bfc7e36d8dbe41aa9, codex/mini-booking.
Проверяющий: read-only backend_security_reviewer; исходники не изменял.

Обнаруженные Origin/CORS, Host и debug-stack дефекты исправлены и перепроверены. Чужие Origin/Host → 403, тело больше 8 KiB → 413; API не выдаёт trace/file/line. .env и SQLite недоступны через HTTP. Listener только 127.0.0.1:8017 и 5177. PHP ^8.4 согласован с SQLite IMMEDIATE. Секреты/runtime/dependencies исключены из Git.
Security manifest 19 файлов: 2017089991f107cca02bca8d1fffcdd332799aa1c1be46ec05aabfdff3ffa6b1. SHA256 обновлённого race-result совпали с исходниками; race 3×4 PASS, одна бронь и рабочая БД не менялась.

OWASP ASVS 4.0.3, scoped subset V5/V7/V8/V11/V12/V13/V14. Это не полная ASVS-сертификация.
Намеренные границы: публичная отмена по ID без авторизации, вымышленные данные, только loopback. Production и публикация этим review не разрешаются. Изменение проверенных исходников требует повторной проверки. UI/E2E оцениваются отдельным gate.
