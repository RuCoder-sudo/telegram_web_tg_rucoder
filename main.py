import os
from flask import Flask, request, jsonify, send_from_directory
from flask_cors import CORS
import logging
import json

# Настройка логирования
logging.basicConfig(level=logging.DEBUG)

# Создание Flask-приложения
app = Flask(__name__, static_folder='public')
CORS(app)  # Добавляем поддержку CORS

# Загрузка конфигурации
app.config['SECRET_KEY'] = os.environ.get("SESSION_SECRET", "telegram_mini_app_secret")

@app.route('/')
def index():
    """Основной маршрут - возвращает главную страницу Telegram Mini App"""
    return send_from_directory('public', 'index.html')

@app.route('/<path:path>')
def static_files(path):
    """Маршрут для обслуживания статических файлов"""
    return send_from_directory('public', path)

@app.route('/api/order', methods=['POST'])
def create_order():
    """Обработка заказа услуги"""
    try:
        # Получаем данные из запроса
        data = request.json
        app.logger.debug(f"Order received: {data}")
        
        # В реальном приложении здесь была бы отправка сообщения в Telegram
        # Например, отправка сообщения в бот или канал
        
        # Формируем сообщение для отправки
        message = f"""
⇨ Новый заказ из Telegram Mini App!
        
Клиент: {data.get('name')}
Услуга: {data.get('service')}
Контакт: {data.get('contact')}

Описание:
{data.get('description')}
        """
        
        # Для демонстрации просто записываем в лог
        app.logger.info(message)
        
        # Здесь была бы отправка в Telegram
        # send_to_telegram(message)
        
        # Сохраняем заявку в файл для демонстрации
        save_order_to_file(data)
        
        return jsonify({
            'status': 'success',
            'message': 'Заявка успешно отправлена!'
        })
    except Exception as e:
        app.logger.error(f"Error processing order: {e}")
        return jsonify({
            'status': 'error',
            'message': str(e)
        }), 500

def save_order_to_file(data):
    """Сохраняет заказ в файл JSON (для демонстрации)"""
    try:
        # Создаем папку для хранения заказов, если ее нет
        os.makedirs('orders', exist_ok=True)
        
        # Генерируем уникальное имя файла
        import datetime
        timestamp = datetime.datetime.now().strftime("%Y%m%d%H%M%S")
        filename = f"orders/order_{timestamp}.json"
        
        # Записываем данные в файл
        with open(filename, 'w', encoding='utf-8') as f:
            json.dump(data, f, ensure_ascii=False, indent=4)
            
        app.logger.debug(f"Order saved to file: {filename}")
    except Exception as e:
        app.logger.error(f"Error saving order to file: {e}")

if __name__ == '__main__':
    # Запуск приложения на порту 5000
    app.run(host='0.0.0.0', port=5000, debug=True)
