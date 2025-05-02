document.addEventListener('DOMContentLoaded', function() {
    // Инициализация Telegram WebApp
    const tg = window.Telegram.WebApp;
    tg.expand(); // Развернуть на весь экран
    
    // Адаптируем тему под цвета Telegram
    // Обновляем CSS-переменные
    document.documentElement.style.setProperty('--bg-color', '#000000');
    document.documentElement.style.setProperty('--text-color', '#ffffff');
    document.documentElement.style.setProperty('--accent-color', '#ff0000');
    
    // Навигация между разделами
    const sections = document.querySelectorAll('section');
    const navButtons = document.querySelectorAll('[data-section]');
    const orderButtons = document.querySelectorAll('.order-button');
    
    // Функция переключения разделов
    function showSection(sectionId) {
        sections.forEach(section => {
            section.classList.remove('active');
        });
        
        const targetSection = document.getElementById(sectionId);
        if (targetSection) {
            targetSection.classList.add('active');
            // Прокрутка вверх
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }
    
    // Назначаем обработчики событий для кнопок навигации
    navButtons.forEach(button => {
        button.addEventListener('click', function() {
            const sectionId = this.dataset.section;
            showSection(sectionId);
        });
    });
    
    // Обработчики для кнопок заказа услуг
    orderButtons.forEach(button => {
        button.addEventListener('click', function() {
            const serviceType = this.dataset.service;
            const serviceSelect = document.getElementById('service');
            
            // Устанавливаем выбранную услугу в форме
            if (serviceSelect) {
                Array.from(serviceSelect.options).forEach(option => {
                    if (option.value === serviceType) {
                        serviceSelect.value = serviceType;
                    }
                });
            }
            
            // Переходим к форме заказа
            showSection('order');
        });
    });
    
    // Кнопка написать в Telegram
    const tgChatButton = document.querySelector('.tg-chat');
    if (tgChatButton) {
        tgChatButton.addEventListener('click', function() {
            // Открываем чат с RussCoder
            window.open('https://t.me/RussCoder', '_blank');
        });
    }
    
    // Обработка отправки формы
    const orderForm = document.getElementById('order-form');
    const successMessage = document.getElementById('success-message');
    
    if (orderForm) {
        orderForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Собираем данные формы
            const formData = new FormData(orderForm);
            
            // Формируем текст заявки для отправки в Telegram
            const name = formData.get('name');
            const service = formData.get('service');
            const description = formData.get('description');
            const contact = formData.get('contact');
            
            // Текст сообщения
            const orderData = {
                name: name,
                service: service,
                description: description,
                contact: contact,
                // Передаем полную информацию от Telegram WebApp, если есть
                telegram_user: tg.initDataUnsafe?.user ? {
                    id: tg.initDataUnsafe.user.id,
                    username: tg.initDataUnsafe.user.username,
                    first_name: tg.initDataUnsafe.user.first_name,
                    last_name: tg.initDataUnsafe.user.last_name
                } : null
            };
            
            // Отправляем данные на сервер
            fetch('/api/order', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(orderData)
            })
            .then(response => response.json())
            .then(data => {
                console.log('Success:', data);
                
                // Показываем сообщение об успехе
                successMessage.classList.add('active');
                
                // Сбрасываем форму
                orderForm.reset();
                
                // Можно также отправить данные назад в Telegram
                if (tg.initDataUnsafe?.query_id) {
                    tg.sendData(JSON.stringify({ type: 'order_submitted', ...orderData }));
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                // Резервный вариант - показываем сообщение об успехе в любом случае
                successMessage.classList.add('active');
                orderForm.reset();
            });
        });
    }
    
    // Закрытие сообщения об успехе
    const closeMessage = document.querySelector('.close-message');
    if (closeMessage && successMessage) {
        closeMessage.addEventListener('click', function() {
            successMessage.classList.remove('active');
            // Возвращаемся на главную
            showSection('home');
        });
    }
    
    // Дополнительная настройка для совместимости с Telegram WebApp
    tg.onEvent('viewportChanged', function() {
        // Прокрутка вверх при изменении размера окна
        window.scrollTo({ top: 0 });
    });
    
    // Настройка главной кнопки Telegram WebApp
    tg.MainButton.setText('Заказать услугу');
    tg.MainButton.setParams({
        color: '#ff0000',
        text_color: '#ffffff',
        is_active: true,
        is_visible: true
    });
    
    // При нажатии на главную кнопку переходим к форме заказа
    tg.MainButton.onClick(function() {
        showSection('order');
    });
});
