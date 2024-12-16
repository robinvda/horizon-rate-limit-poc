# In-Redis rate limiting PoC

### Quick-start
- Run Docker containers
  - `docker compose up -d`
- Open bash in base container
  - `docker compose exec base bash`
- Run Horizon
  - php artisan horizon
- Open another bash instance in base container
  - `docker compose exec base bash`
- Create jobs
  - `php artisan flood-jobs`

Horizon dashboard is accessible at http://localhost:9999/horizon

### How it works

- Jobs are pushed onto their own "sub-queue" in Redis. The key of this "sub-queue" is the queue the job is pushed on, suffixed with the key of the rate limit. These rate limit keys must be defined in the `rate-limit` config, and the job must have a property `rateLimitKey`.
- Every time Horizon tries to get a job to execute, it will now loop through all rate limiting keys (defined in `rate-limit` config). When an attempt succeeds, it will get a job from that sub-queue.

Pros:
- No deserialization into PHP objects.
- No major changes to Laravel (Horizon) code, so it should be compatible with other features (must be tested).

Cons:
- Horizon will always try to run jobs that have no rate limiting first, even if they're added later.
- Extended some core Horizon classes which may require extra attention when updating Horizon.
- Requires testing to see if other features till work (like retries, middleware, etc)

### Code
The following code is required to make this work:
- `App\Providers\HorizonServiceProvider`
- `App\Jobs\TestJob`
- Everything in `App\Horizon`
