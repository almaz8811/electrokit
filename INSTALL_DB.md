# Установка ElectroKit с MariaDB на Synology

## Требования

- Synology DSM 7.x
- Web Station
- PHP 7.4+ с PDO MySQL
- MariaDB 10 (установить из Package Center)
- phpMyAdmin (опционально, для удобства)

## Шаг 1: Установка MariaDB

1. Откройте **Package Center**
2. Найдите и установите **MariaDB 10**
3. После установки откройте **MariaDB 10**
4. Установите пароль root (запомните его!)

## Шаг 2: Создание базы данных

### Вариант А: Через phpMyAdmin (проще)

1. Установите **phpMyAdmin** из Package Center
2. Откройте phpMyAdmin: `http://synology-ip:порт/phpMyAdmin`
3. Войдите как root с паролем из шага 1
4. Перейдите на вкладку **SQL**
5. Скопируйте содержимое файла `setup.sql` и выполните
6. **ВАЖНО:** Измените пароль в SQL скрипте на свой:
   ```sql
   CREATE USER 'electrokit_user'@'localhost' IDENTIFIED BY 'ваш_надёжный_пароль';
   ```

### Вариант Б: Через SSH

```bash
# Подключитесь по SSH
ssh admin@synology-ip

# Войдите в MySQL
mysql -u root -p

# Выполните SQL скрипт
source /volume1/web/electrokit/setup.sql
```

## Шаг 3: Настройка конфигурации

1. Скопируйте `db_config.php.example` в `db_config.php`:
   ```bash
   cp db_config.php.example db_config.php
   ```

2. Отредактируйте `db_config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'electrokit');
   define('DB_USER', 'electrokit_user');
   define('DB_PASS', 'ваш_пароль_из_setup.sql');
   ```

3. Установите права доступа (через SSH):
   ```bash
   chmod 600 /volume1/web/electrokit/db_config.php
   chown http:http /volume1/web/electrokit/db_config.php
   ```

## Шаг 4: Переименование API файла

Переименуйте `api_db.php` в `api.php` (заменив старый):

```bash
cd /volume1/web/electrokit
mv api.php api_json.php.backup
mv api_db.php api.php
```

Или через File Station:
- Переименовать `api.php` → `api_json.php.backup`
- Переименовать `api_db.php` → `api.php`

## Шаг 5: Инициализация базы (опционально)

Таблицы создаются автоматически при первом запросе, но можете создать их вручную:

```
http://synology-ip:port/api.php?action=init
```

Должен вернуть:
```json
{"success":true,"message":"Database initialized"}
```

## Шаг 6: Проверка работы

1. Откройте ElectroKit в браузере
2. Измените любую настройку (например, цену кабеля)
3. Проверьте в phpMyAdmin что данные сохранились:
   - Таблица `settings` — должна содержать JSON с настройками
   - Создайте проект и проверьте таблицу `projects`

## Структура базы данных

### Таблица `settings`
```
id          INT (auto_increment)
data        JSON (все настройки: цены, тема, валюта)
updated_at  TIMESTAMP
created_at  TIMESTAMP
```

### Таблица `projects`
```
id          VARCHAR(64) (уникальный ID проекта)
name        VARCHAR(255) (название проекта)
type        VARCHAR(50) (apartment/house/office)
data        JSON (весь проект: rooms, appliances, панель)
saved_at    TIMESTAMP
created_at  TIMESTAMP
```

## Миграция с JSON на MariaDB

Если у вас уже есть данные в `data/`:

1. **Настройки:** Откройте ElectroKit, перейдите в настройки, нажмите "Сохранить" — настройки автоматически запишутся в БД
2. **Проекты:** Используйте функцию экспорта/импорта проектов

## Резервное копирование

### Автоматический backup (рекомендуется)

Создайте скрипт `/volume1/scripts/backup_electrokit.sh`:

```bash
#!/bin/bash
BACKUP_DIR="/volume1/backups/electrokit"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Backup базы данных
mysqldump -u electrokit_user -p'ваш_пароль' electrokit > $BACKUP_DIR/electrokit_$DATE.sql

# Удалить бэкапы старше 30 дней
find $BACKUP_DIR -name "electrokit_*.sql" -mtime +30 -delete
```

Права:
```bash
chmod +x /volume1/scripts/backup_electrokit.sh
```

Добавьте в **Control Panel** → **Task Scheduler**:
- User: root
- Schedule: Daily 3:00 AM
- Command: `/volume1/scripts/backup_electrokit.sh`

### Ручной backup

```bash
mysqldump -u electrokit_user -p electrokit > backup.sql
```

### Восстановление

```bash
mysql -u electrokit_user -p electrokit < backup.sql
```

## Безопасность

1. **Не коммитьте `db_config.php` в git** (уже в .gitignore)
2. **Используйте надёжный пароль** для БД
3. **Ограничьте доступ к phpMyAdmin** через настройки брандмауэра DSM
4. **Регулярно делайте бэкапы** базы данных

## Устранение неполадок

### Ошибка "Database connection failed"

1. Проверьте что MariaDB запущен (Package Center → MariaDB 10)
2. Проверьте настройки в `db_config.php`
3. Проверьте что пользователь создан:
   ```sql
   SELECT User, Host FROM mysql.user WHERE User='electrokit_user';
   ```

### Ошибка "Access denied"

Проверьте пароль в `db_config.php` и права пользователя:
```sql
SHOW GRANTS FOR 'electrokit_user'@'localhost';
```

### Данные не сохраняются

1. Проверьте логи PHP: Control Panel → Log Center → PHP
2. Проверьте что API отвечает: `http://synology-ip/api.php?action=getSettings`
3. Откройте консоль браузера (F12) и проверьте вкладку Network

## Производительность

MariaDB оптимизирована для JSON в столбцах. Индексы созданы для быстрого поиска проектов по имени и дате.

Для больших объёмов проектов (>1000) рассмотрите добавление индексов на JSON поля:

```sql
ALTER TABLE projects ADD INDEX idx_json_name ((CAST(data->>'$.name' AS CHAR(255))));
```

## Преимущества MariaDB vs JSON файлы

✅ Одновременный доступ без блокировок
✅ ACID транзакции
✅ Быстрый поиск и фильтрация проектов
✅ Встроенные бэкапы через mysqldump
✅ Меньше прав файловой системы
✅ Проще масштабирование
