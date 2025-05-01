<?php
/**
 * Main admin page template.
 *
 * @since      1.0.0
 * @package    WooMoySklad
 * @subpackage WooMoySklad/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap woo-moysklad-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="subheader">
        <?php _e('Добро пожаловать в интеграцию WooCommerce с МойСклад! Это главная панель управления.', 'woo-moysklad-integration'); ?>
    </div>
    
    <div class="woo-moysklad-author-box">
        <h3><?php _e('Информация о разработчике', 'woo-moysklad-integration'); ?></h3>
        <div class="woo-moysklad-author-content">
            <p><strong>🆁🆄🅲🅾🅳🅴🆁 - Разработка сайтов</strong></p>
            <p>Я являюсь Front-End разработчиком и предлагаю следующие услуги для вашего бизнеса:</p>
            <ul>
                <li>🔸 Подбор и регистрация уникального домена и хостинга</li>
                <li>🔸 Обработка или разработка с нуля недостающего контента для сайта</li>
                <li>🔸 Создание уникальной концепции сайта на основании анализа</li>
                <li>🔸 Разработка сайта и качественная верстка на WordPress (▪landing page▪сайт-визитка▪интернет-магазин ▪корпоративный сайт) любой сложности</li>
                <li>🔸 Создание и выкладка контента, который будет максимально привлекать клиентов</li>
            </ul>
            <p><strong>♦️ КОНТАКТЫ:</strong></p>
            <ul>
                <li><strong>Фриланс портфолио:</strong> по запросу</li>
                <li><strong>Email:</strong> <a href="mailto:rucoder.rf@yandex.ru">rucoder.rf@yandex.ru</a></li>
                <li><strong>Вконтакте:</strong> <a href="https://vk.com/rucoderweb" target="_blank">https://vk.com/rucoderweb</a></li>
                <li><strong>Инстаграм:</strong> <a href="https://www.instagram.com/rucoder.web/" target="_blank">https://www.instagram.com/rucoder.web/</a></li>
                <li><strong>Телеграм:</strong> <a href="https://t.me/RussCoder" target="_blank">https://t.me/RussCoder</a></li>
                <li><strong>Whatsapp:</strong> <a href="https://wapp.click/79859855397" target="_blank">https://wapp.click/79859855397</a></li>
                <li><strong>Сайт-портфолио:</strong> <a href="https://рукодер.рф/" target="_blank">https://рукодер.рф/</a></li>
            </ul>
            <p class="copyright-notice"><strong>ВНИМАНИЕ:</strong> Распространение, копирование, модификация или продажа данного плагина без письменного разрешения владельца строго запрещены и преследуются по закону.</p>
        </div>
    </div>
    
    <?php
    // Show warning if API credentials are not configured
    $api_login = get_option('woo_moysklad_api_login', '');
    $api_password = get_option('woo_moysklad_api_password', '');
    
    if (empty($api_login) || empty($api_password)) {
        ?>
        <div class="notice notice-warning">
            <p>
                <?php _e('Учетные данные API МойСклад не настроены.', 'woo-moysklad-integration'); ?>
                <a href="<?php echo admin_url('admin.php?page=woo-moysklad-settings'); ?>">
                    <?php _e('Настроить сейчас', 'woo-moysklad-integration'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    // Show active tab for products
    include_once plugin_dir_path(__FILE__) . 'woo-moysklad-admin-products.php';
    ?>
</div>
