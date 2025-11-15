## Versions
Composer version 2.8.9
PHP 8.4.8
Laravel Framework 10.49.1
Redis-cli 8.2.2

## DataBase
Postgresql

## Used Packages
Laravel horizon

## System Description
This system does the getting large scale of order data from a csv file via Php command and chunk it by 500 for the performance optimizations, then add to a Job named chunckCSVJob, dispatch those via BUS method. inside of chunck csv job there having other jobs name reserve stock, simulate payment job, finalizing the job. and there calculatin daily KPIs and overall leaderboard and save in REDIS. and send order status by email job.

## Installation and runing process
1. run - Composer install
2. run - php artisan key:generate
3. update env with db, redis, queue connection and mailer details
4. start postgresql create database
5. run - php artisan migrate
6. start redis
7. run - php artisan horizon
8. run - php artisan orders:file-import restaurant_orders.csv
9. get kpi and leaderboard run - php artisan kpis:show {date(2025-11-14)}
10. refund request - run the api with order id

## Explained video url
https://loom.com/share/folder/91372b330b3d44638d3f5a2794025ec9