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

## Дополнительные ресурсы

- [Документация Telegram Mini Apps](https://core.telegram.org/bots/webapps)
- [Документация Flask](https://flask.palletsprojects.com/)
- [Документация Gunicorn](https://docs.gunicorn.org/)
