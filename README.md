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

## Tools needed to Enable OCR Processing of PDF Files for the HR Policy Chatbot.

Install Tesseract:
- Go to ```https://github.com/UB-Mannheim/tesseract/wiki```
- Download tesseract-ocr-w64-setup-5.x.x.exe
- Run the installer — on the "Additional language data" screen, expand it and check both English and Filipino or (Add your prefered language)
- Default install path is C:\Program Files\Tesseract-OCR\ — keep it
- Add C:\Program Files\Tesseract-OCR to your Windows PATH (System Environment Variables → Path → New)

Install Poppler (for pdftoppm):

- Go to ```https://github.com/oschwartz10612/poppler-windows/releases```
- Download the latest Release-xx.xx.x-0.zip
- Extract to C:\poppler\
- Add C:\poppler\Library\bin to your Windows PATH
- Restart XAMPP after PATH changes — Apache inherits PATH from Windows but only reads it at startup

Verify if you install it successfully in Command Prompt:
```bash
tesseract --version
tesseract --list-langs    (should show 'eng' and 'fil')
pdftoppm -v
```

Add this in your .env file:
```
OCR_TESSERACT_PATH="C:/Program Files/Tesseract-OCR/tesseract.exe"
OCR_PDFTOPPM_PATH="C:/poppler/Library/bin/pdftoppm.exe"
OCR_LANGUAGES=eng+fil
```

## Login using Admin account

#### Admin
Email: admin@example.com.<br>
Password: 12341234

## License
The project is open-sourced software licensed under the [MIT license](https://choosealicense.com/licenses/mit/).

