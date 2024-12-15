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

Rate limiting is performed in Redis using a Lua script. For each job in the queue Redis needs to decode the JSON data and do the rate limit check (until it has found a valid job). This may cause extra load on Redis (needs to be tested).

Note: Unlike the *rate limited* middleware of Laravel, this way of rate limiting does not increase the number of attempts of a job.

Pros:
- No deserialization into PHP objects.
- No major changes to Laravel (Horizon) code, so it should be compatible with other features (must be tested).

Cons:
- Possibly extra load on Redis because of JSON decoding.
- Extended some core Horizon classes which may require extra attention when updating Horizon.

### Code
The following code is required to make this work:
- `App\Providers\HorizonServiceProvider`
- `App\Jobs\TestJob`
- Everything in `App\Horizon`
