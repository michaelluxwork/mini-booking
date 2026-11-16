# Подготовка окружения · исторический снимок 16.11.2026, 18:14 МСК

Этот файл описывает этап до реализации. Текущее состояние — README.md и quality/.

Подготовка завершена: PHP8.4.27NTSx64, Composer2.11.2, Laravel13.42.0, Vue3.5.45, Vite8.3.8, NaiveUI2.45.3, SQLite. Использован установленный Node22.23.2.

Проверено:
- Официальный SHA256архиваPHP совпал; SHA384установщикаComposer совпал с composer.github.io/installer.sig. Локальные контрольные суммы и URL — в игнорируемом .tools/provenance.json.
- Расширения PHPpdo_sqlite,openssl,mbstring,zip включены.
- Миграции стандартного шаблонаLaravel применены к локальному SQLite.
- `artisan test`:3passed,4assertions; это2штатных теста шаблона и1тест здоровья окружения, не тесты бронирования.
- `npm run build`:PASS, Vue/NaiveUI собираются.
- npmпри установке:0известных уязвимостей; Composer audit: advisories=[],abandoned=[]. Это проверка известных advisories, не полноценный security-аудит приложения.
- Браузер: http://127.0.0.1:5177, кнопка проверки API показываетSQLiteиEurope/Moscow; GET/api/health черезVite возвращает200.
- STOPиSTARTпроверены; после повторного запуска GET/api/health=200.

Laravelслушает127.0.0.1:8017, Vite127.0.0.1:5177; внешние адреса/туннели не подключались. Репозиторий не публиковался. Секрет.env,база,.tools,vendor,node_modules,.runtime исключены изGit.

Откат подготовки: остановить только эти серверы черезSTOP.cmd. Переносные инструменты находятся внутри проекта и не добавлялись в системныйPATH. Существующий штаб не изменялся.

Бронирование, слоты, Seederрасписания, конкурентная уникальность и бизнес-тесты отсутствуют. Двухчасовой отсчёт реализации не запущен. Проверка на телефоне/финальный дизайнN/A: пока лишь техническая стартовая страница, не экран бронирования.

Источники:
- https://downloads.php.net/~windows/releases/releases.json
- https://getcomposer.org/download/
- https://laravel.com/docs/13.x/installation
- https://vuejs.org/guide/quick-start.html
- https://github.com/tusen-ai/naive-ui
