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

A simple dashboard is available at http://localhost:9999. This page refreshes every second.

## How it works
- When the supervisor is started, it starts 5 worker processes. It then waits until tasks are pushed to the queue.
  - The amount of workers can be set when starting the supervisor command (1st argument).
- When a task is pushed, the supervisor will hit the rate limit to see of a task can be executed. If so, it will pull the task from the queue and assign it to an available worker.
- A worker is available if it is currently not executing a task. If there are no workers available, the supervisor will wait until one becomes available.
- When there are no tasks available on any queue, the supervisor will wait for 1 second until it starts looking for tasks again. It will also wait for 1 second if there are no available workers.
  - This is to avoid high CPU load and limit the amount of Redis calls.
  - The waiting time can be set when starting the supervisor command (2nd argument).
- Each queue has its own rate limit (see `\App\Tasks\Queues\TaskQueue`). Using sub queues you can have multiple queues using the same rate limit config with their own key. This is configured on the job itself (see `\App\Tasks\Task`).
