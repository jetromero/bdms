# 🩸 EVSU-OCC Blood Donation Management System

This project is a web application built using **PHP and MySQL**. Follow the steps below to set up the project and run the server locally.

---

## ## Requirements

Make sure the following are installed on your system:

- PHP 8.1 or newer
- MySQL (XAMPP / Laragon)
- Composer
- Git

## Clone the Repository

Bash
git clone [https://github.com/yourusername/evsu-occ-blood-donation.git](https://github.com/yourusername/evsu-occ-blood-donation.git)
cd evsu-occ-blood-donation

## Database Configuration

Open phpMyAdmin.

Create a new database named blood_donation_db.

Import the database.sql file found in the project root.

If you want the forgot-password OTP flow, also import `schema_password_resets.sql` and set the SMTP environment variables used by PHPMailer:

- `BDMS_SMTP_HOST`
- `BDMS_SMTP_PORT`
- `BDMS_SMTP_USERNAME`
- `BDMS_SMTP_PASSWORD`
- `BDMS_SMTP_FROM_EMAIL`
- `BDMS_SMTP_FROM_NAME`
- `BDMS_SMTP_ENCRYPTION` (optional, `tls` or `ssl`)

## Run the Development Server

Start the local PHP development server:

Bash
php -S localhost:8000
By default, the application will be available at:

http://localhost:8000

## Access the Admin Panel

To manage donors and inventory, visit:

http://localhost:8000/admin

Login using the administrator credentials created during setup.

## Deployment (CI/CD)

This project includes a GitHub Actions workflow that builds Composer dependencies and deploys the site to Hostinger via FTP on pushes to the `main` branch: `.github/workflows/deploy.yml`.

Before pushing to `main`, add the following GitHub repository secrets in **Settings → Secrets**:

- `FTP_SERVER` — your Hostinger FTP host (e.g. `ftp.example.com`)
- `FTP_USERNAME` — FTP account username
- `FTP_PASSWORD` — FTP account password
- `FTP_SERVER_DIR` — remote directory to deploy to (e.g. `/public_html`)

Ensure your Hostinger environment variables are set for runtime configuration (database and SMTP): `BDMS_DB_HOST`, `BDMS_DB_USER`, `BDMS_DB_PASS`, `BDMS_DB_NAME`, `BDMS_SMTP_HOST`, `BDMS_SMTP_PORT`, `BDMS_SMTP_USERNAME`, `BDMS_SMTP_PASSWORD`, `BDMS_SMTP_FROM_EMAIL`, `BDMS_SMTP_FROM_NAME`, and `BDMS_SMTP_ENCRYPTION`.

To deploy: push a commit to `main` and monitor the Actions tab — the workflow will upload the site to your Hostinger account.
