# Руководство по установке Telegram Mini App

Это руководство поможет вам установить и настроить Telegram Mini App на вашем веб-сервере.

## Требования

- Веб-сервер с поддержкой HTTP/HTTPS (Apache, Nginx или другой)
- Python 3.6 или новее
- Pip (менеджер пакетов Python)
- Gunicorn для запуска Flask-приложения

## Шаг 1: Скачивание проекта

1. Скачайте все файлы проекта из репозитория или с Replit.
2. Загрузите файлы на ваш сервер.

## Шаг 2: Установка зависимостей

Выполните следующие команды в терминале на вашем сервере:

```bash
# Создайте виртуальное окружение Python (рекомендуется)
python -m venv venv

# Активируйте виртуальное окружение
source venv/bin/activate  # Для Linux/Mac
# или
venv\Scripts\activate     # Для Windows

# Установите необходимые пакеты
pip install flask flask-cors gunicorn
```

## Шаг 3: Настройка файлов

1. Убедитесь, что файл `main.py` находится в корневой директории проекта.
2. Проверьте, что директория `public` содержит все необходимые файлы:
   - `index.html`
   - `css/style.css`
   - `js/app.js`
   - `img/` (директория с изображениями)
3. Убедитесь, что файл Lottie-анимации `lf30_editor_jqtxdpu7.json` находится в корневой директории проекта.

## Шаг 4: Запуск приложения

### Для тестирования

```bash
# Активируйте виртуальное окружение, если еще не активировано
source venv/bin/activate  # Для Linux/Mac
# или
venv\Scripts\activate     # Для Windows

# Запустите приложение через gunicorn
gunicorn --bind 0.0.0.0:5000 main:app
```

### Для запуска в производственной среде

Для постоянной работы приложения рекомендуется настроить systemd сервис (для Linux) или использовать PM2 (для Node.js окружения).

#### Настройка systemd сервиса (Linux)

1. Создайте файл сервиса:

```bash
sudo nano /etc/systemd/system/telegram-mini-app.service
```

2. Добавьте следующий конфигурационный код (измените пути и пользователя на ваши):

```
[Unit]
Description=Telegram Mini App Service
After=network.target

[Service]
User=your_username
WorkingDirectory=/path/to/your/project
ExecStart=/path/to/your/project/venv/bin/gunicorn --workers 3 --bind 0.0.0.0:5000 main:app
Restart=always

[Install]
WantedBy=multi-user.target
```

3. Включите и запустите сервис:

```bash
sudo systemctl enable telegram-mini-app.service
sudo systemctl start telegram-mini-app.service
```

4. Проверьте статус сервиса:

```bash
sudo systemctl status telegram-mini-app.service
```

## Шаг 5: Настройка веб-сервера

### Пример конфигурации Nginx

```
server {
    listen 80;
    server_name your-domain.com;

    location / {
        proxy_pass http://127.0.0.1:5000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```

### Пример конфигурации Apache

```
<VirtualHost *:80>
    ServerName your-domain.com
    
    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:5000/
    ProxyPassReverse / http://127.0.0.1:5000/

    ErrorLog ${APACHE_LOG_DIR}/telegram-mini-app-error.log
    CustomLog ${APACHE_LOG_DIR}/telegram-mini-app-access.log combined
</VirtualHost>
```

## Шаг 6: Настройка HTTPS (рекомендуется)

Для Telegram Mini App рекомендуется использовать HTTPS. Вы можете использовать Let's Encrypt для получения бесплатного SSL-сертификата.

```bash
sudo apt install certbot python3-certbot-nginx  # для Nginx
# или
sudo apt install certbot python3-certbot-apache  # для Apache

# Получение сертификата для Nginx
sudo certbot --nginx -d your-domain.com
# или для Apache
sudo certbot --apache -d your-domain.com
```

## Шаг 7: Создание Telegram Mini App

1. Откройте [@BotFather](https://t.me/BotFather) в Telegram
2. Создайте нового бота или выберите существующего
3. Отправьте команду `/newapp`
4. Следуйте инструкциям BotFather для настройки Mini App
5. В URL укажите адрес вашего сервера с HTTPS, например: `https://your-domain.com`

## Шаг 8: Проверка работоспособности

1. Перейдите по ссылке вашего Mini App в Telegram
2. Убедитесь, что все элементы отображаются корректно
3. Проверьте анимации и интерактивные элементы
4. Проверьте форму заказа и отправку данных

## Устранение неполадок

Если у вас возникли проблемы:

1. Проверьте логи приложения:
   ```bash
   sudo journalctl -u telegram-mini-app.service
   ```

2. Проверьте логи веб-сервера (Nginx/Apache)

3. Убедитесь, что порт 5000 открыт и не блокируется файрволом

4. Проверьте права доступа к файлам проекта

Если анимации не отображаются, убедитесь, что файл Lottie-анимации доступен по правильному пути и имеет корректные права доступа.

# Проверка Telegram бота
echo "Проверка Telegram бота:"
ps -ef | grep "bot.py" | grep -v grep
if [ $? -eq 0 ]; then
  echo "✓ Telegram бот запущен и работает"
else
  echo "✗ Telegram бот не запущен"
fi
# Проверка доступности веб-приложения через curl
echo -e "\nПроверка веб-приложения Telegram (https://rucoderweb.website/):"
curl -s -o /dev/null -w "%{http_code}" https://rucoderweb.website/
if [ $? -eq 0 ]; then
  echo "✓ Веб-приложение доступно"
else
  echo "✗ Веб-приложение недоступно"
fi
# Проверка процесса веб-приложения
echo -e "\nПроверка процесса веб-приложения:"
ps -ef | grep "main.py" | grep -v grep
if [ $? -eq 0 ]; then
  echo "✓ Процесс веб-приложения запущен"
else
  echo "✗ Процесс веб-приложения не запущен"
fi
# Проверка связи с Nginx
echo -e "\nПроверка настройки Nginx для доменов:"
nginx -t
echo -e "\nНастройка доменов в Nginx:"
grep "server_name" /etc/nginx/sites-enabled/* | grep -E "marketingmaster.space|rucoderweb.website"
# Проверка работоспособности MarketingMaster
echo -e "\nПроверка MarketingMaster (https://marketingmaster.space/):"
curl -s -o /dev/null -w "%{http_code}" https://marketingmaster.space/
if [ $? -eq 0 ]; then
  echo "✓ MarketingMaster доступен"
else
  echo "✗ MarketingMaster недоступен"
fi
# Проверка логов последней активности бота
echo -e "\nПоследние логи Telegram бота:"
tail -n 10 /root/logs/telegrambot/output.log
# Проверка настройки мониторинга
echo -e "\nНастройка мониторинга:"
crontab -l | grep -E "check_services|startup_all"
echo -e "\nВсе сервисы настроены и работают."

Перезапуск Telegram бота
# Остановка бота
pkill -f "python3 bot.py"
# Запуск бота с записью логов
cd /root/telegram_web_tg_rucoder
nohup python3 bot.py > /root/logs/telegrambot/output.log 2>&1 &
# Проверка, что бот запустился
ps -ef | grep "bot.py" | grep -v grep
Выключение бота
# Остановка бота
pkill -f "python3 bot.py"
# Проверка, что бот остановлен (не должно быть вывода)
ps -ef | grep "bot.py" | grep -v grep
Включение бота
# Запуск бота
cd /root/telegram_web_tg_rucoder
mkdir -p /root/logs/telegrambot
nohup python3 bot.py > /root/logs/telegrambot/output.log 2>&1 &
# Проверка, что бот запустился
ps -ef | grep "bot.py" | grep -v grep
Проверка статуса бота
# Проверка запущен ли бот
if pgrep -f "python3 bot.py" > /dev/null; then
  echo "✓ Бот запущен и работает"
  # Показываем последние 5 строк лога
  echo -e "\nПоследние записи лога:"
  tail -n 5 /root/logs/telegrambot/output.log
else
  echo "✗ Бот не запущен"
fi
Универсальная команда для перезапуска
# Универсальный скрипт перезапуска бота
echo "Останавливаю бота..."
pkill -f "python3 bot.py" || true
sleep 2
echo "Запускаю бота..."
cd /root/telegram_web_tg_rucoder
mkdir -p /root/logs/telegrambot
nohup python3 bot.py > /root/logs/telegrambot/output.log 2>&1 &
sleep 2
if pgrep -f "python3 bot.py" > /dev/null; then
  echo "✓ Бот успешно перезапущен"
else
  echo "✗ Ошибка при запуске бота. Проверьте логи:"
  tail -n 10 /root/logs/telegrambot/output.log
fi


## Дополнительные ресурсы

- [Документация Telegram Mini Apps](https://core.telegram.org/bots/webapps)
- [Документация Flask](https://flask.palletsprojects.com/)
- [Документация Gunicorn](https://docs.gunicorn.org/)
