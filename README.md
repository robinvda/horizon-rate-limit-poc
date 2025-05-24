# Tasks PoC

### Quick-start
- Run Docker containers
  - `docker compose up -d`
- Open bash in base container
  - `docker compose exec base bash`
- Run task supervisor
  - `php artisan task:supervisor`
- Open another bash instance in base container
  - `docker compose exec base bash`
- Create jobs
  - `php artisan task:flood`

A simple dashboard is available at http://localhost:9999
