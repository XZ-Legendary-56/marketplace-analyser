<?php
require_once __DIR__ . '/config.php';

session_start();

$page_title = 'Главная | MP Analytics';

require_once __DIR__ . '/templates/header.php';
?>

<main class="container">
    <section class="hero-section">
        <h1>Ваш центр управления продажами на маркетплейсах</h1>
        <p class="subtitle">
            Превратите разрозненные отчеты в понятную аналитику. Принимайте решения, основанные на данных, а не на догадках.
        </p>
    </section>

    <section class="features-grid">
        <div class="feature-card">
            <svg class="feature-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-12h2v4h-2zm0 6h2v2h-2z"/></svg>
            <h3>Понимайте реальную прибыль</h3>
            <p>Система автоматически рассчитает маржинальность по каждой площадке и каждому товару, учитывая все комиссии, возвраты и услуги.</p>
        </div>

        <div class="feature-card">
            <svg class="feature-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M10 20h4V4h-4v16zm-6 0h4v-8H4v8zM16 9v11h4V9h-4z"/></svg>
            <h3>Сравнивайте площадки</h3>
            <p>Узнайте, где продавать выгоднее. Наглядные дашборды покажут эффективность каждого маркетплейса и помогут правильно распределить усилия.</p>
        </div>
        
        <div class="feature-card">
            <svg class="feature-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>
            <h3>Простая загрузка данных</h3>
            <p>Просто загрузите стандартные Excel-отчеты с маркетплейсов. Система сама обработает и приведёт к единому виду данные от Ozon, Wildberries и Я.Маркет.</p>
        </div>
    </section>

    <section class="cta-section">
    <?php if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
        

        <h2>Административная панель</h2>
        <p>Управление пользователями и системой доступно в соответствующем разделе.</p>
        <a href="<?php echo BASE_URL; ?>pages/users.php" class="btn btn-primary btn-large">Управление пользователями</a>

    <?php elseif (isset($_SESSION['user_id'])): ?>
        

        <h2>Добро пожаловать, <?php echo htmlspecialchars($_SESSION['user_login']); ?>!</h2>
        <p>Ваши данные готовы к анализу. Перейдите в раздел отчетов, чтобы увидеть полную картину вашего бизнеса.</p>

        <a href="<?php echo BASE_URL; ?>pages/reports.php" class="btn btn-primary btn-large">Перейти к отчетам</a>

    <?php else: ?>

        <h2>Готовы начать?</h2>
        <p>Войдите в свою учетную запись, чтобы загрузить первые отчеты и получить ценные инсайты о ваших продажах.</p>
        <a href="<?php echo BASE_URL; ?>pages/login.php" class="btn btn-primary btn-large">Войти и начать анализ</a>
        
    <?php endif; ?>
</section>

</main>

<?php
require_once __DIR__ . '/templates/footer.php';
?>