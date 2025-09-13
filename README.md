# Laravel + Vue + Tailwind (Breeze)

Проект на Laravel с фронтендом на Vue 3 и Tailwind CSS, установлен через Laravel Breeze.

## 📦 Установка

```bash
git clone https://github.com/Novarg93/phpPractice.git
cd phpPractice
composer install
npm install
cp .env.example .env
php artisan storage:link
php artisan key:generate
php artisan migrate
npm run dev
php artisan serve


-------
pusher 
php artisan queue:work (рт ноты в админке)
php artisan schedule:work (удаление заказов через 24ч)