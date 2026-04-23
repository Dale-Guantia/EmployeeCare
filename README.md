# LARAVEL HELPDESK (EMPLOYEE CARE) WITH BACKPACK

This project aims to provide a web-based helpdesk system using Laravel 8 and Backpack 5.0. A helpdesk is a system that allows users to submit questions, request assistance, or report issues related to an organization’s services.


## System Requirements

- PHP 7.4
- Composer
- Database (eg: MySQL, PostgreSQL, SQLite)
- Web Server (eg: Apache, Nginx, IIS)


## Installation

Clone the repository: 
```bash
git clone https://github.com/Dale-Guantia/EmployeeCare.git
```

Configure ".env" file (eg: DB_DATABASE=employeecare)

Run the command below to create .env copy:
```bash
cp .env.example .env
```

Install PHP dependencies:
```bash
composer install
```

Generate application key: 
```bash
php artisan key:generate
```

Create a symlink to the storage:
```bash
php artisan storage:link
```

Run database migration:
```bash
php artisan migrate --seed
```

Run the server:
```bash
php artisan serve
```

## Login using Admin account

#### Admin
Email: admin@example.com.<br>
Password: 12341234

## License
The project is open-sourced software licensed under the [MIT license](https://choosealicense.com/licenses/mit/).

