# Payment Service

![Continuous Integration](https://github.com/aptive-env/payment-service/actions/workflows/ci.yml/badge.svg)
![Continuous Deployment](https://github.com/aptive-env/payment-service/actions/workflows/cd.yml/badge.svg)
![Continuous Delivery](https://github.com/aptive-env/payment-service/actions/workflows/cd-release.yml/badge.svg)

The microservice use pestroutes SDK for payments logic (old API) and interacting with Gateway directly for processing payments (new API)
All the code related to the logic in the microservice can be found in the root directory.

## Framework and Language

This service uses the Laravel Framework based on the PHP language
Reference: https://laravel.com/docs/10.x/documentation

## Environment Variables

The section describes each of the environment variables.

*\*Environment Variables which should be considered secret*

Here you can see a list of all environment variables and descriptions for each

| Variable                                  | Description                                                                                                                                                                                                                                                                                  | Default Value                |
|-------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|------------------------------|
| APP_NAME                                  | The name of the application                                                                                                                                                                                                                                                                  | Payment Service              |
| APP_ENV                                   | The environment that the application is running in. Examples: local, testing, staging, production                                                                                                                                                                                            | local                        |
| APP_KEY                                   | Application encryption key                                                                                                                                                                                                                                                                   | *null*                       |
| APP_DEBUG                                 | Set to "true" to show helpful debugging information for local development. Should be set to "false" in testing and higher environments                                                                                                                                                       | true                         |
| APP_URL                                   | The URL of the application. This is used by the framework for a lot of things and should be set correctly.  Examples: http://localhost:8080, https://api.notifications.tst.goaptive.com Reference: https://blog.quickadminpanel.com/why-its-important-to-change-app_url-in-laravel-env-file/ | http://localhost:8080        |
| APP_TIMEZONE                              | The timezone for the application. In most cases this should be "UTC"                                                                                                                                                                                                                         | UTC                          |
| LOG_CHANNEL                               | The channel to log to.  Channels can be found and configured in /config/logging.php.  Defaults to stack.                                                                                                                                                                                     | stack                        |
| DB_CONNECTION                             | Payment service default database connection name                                                                                                                                                                                                                                             | pgsql                        |
| DB_HOST                                   | Payment service MySQL database host                                                                                                                                                                                                                                                          | localhost                    |
| DB_PORT                                   | Payment service MySQL database port                                                                                                                                                                                                                                                          | 3306                         |
| DB_DATABASE                               | Payment service MySQL database name                                                                                                                                                                                                                                                          | *null*                       |
| DB_USERNAME                               | Payment service MySQL database user                                                                                                                                                                                                                                                          | *null*                       |
| DB_PASSWORD                               | Payment service MySQL database password                                                                                                                                                                                                                                                      | *null*                       |
| DB_POSTGRES_HOST                          | Payment service PostgreSQL database host. Docker Postgres container should be used for local development.                                                                                                                                                                                    | localhost                    |
| DB_POSTGRES_PORT                          | Payment service PostgreSQL database port. Docker Postgres container should be used for local development.                                                                                                                                                                                    | 5432                         |
| DB_POSTGRES_DATABASE                      | Payment service PostgreSQL database name. Docker Postgres container should be used for local development.                                                                                                                                                                                    | *null*                       |
| DB_POSTGRES_USERNAME                      | Payment service PostgreSQL database user. Docker Postgres container should be used for local development.                                                                                                                                                                                    | *null*                       |
| DB_POSTGRES_PASSWORD                      | Payment service database password. Docker Postgres container should be used for local development.                                                                                                                                                                                           | *null*                       |
| PESTROUTES_API_URL                        | Pestroutes API url                                                                                                                                                                                                                                                                           | *empty string*               |
| PESTROUTES_CREDENTIALS_TABLE_DYNAMO_DB    | Dynamo DB table which store for PestRoutes API credentials                                                                                                                                                                                                                                   | pestroutes-credentials       |
| WORLDPAY_CREDENTIALS_TABLE_DYNAMO_DB      | Dynamo DB table which store for WorldPay API credentials                                                                                                                                                                                                                                     | *null*                       |
| AWS_ACCESS_KEY_ID                         | Specifies an AWS access key associated with an IAM user or role. *Needed for local development only.*                                                                                                                                                                                        | *null*                       |
| AWS_SECRET_ACCESS_KEY                     | Specifies the secret key associated with the access key. This is essentially the "password" for the access key. *Needed for local development only.*                                                                                                                                         | *null*                       |
| AWS_SESSION_TOKEN                         | Specifies the session token value that is required if you are using temporary security credentials that you retrieved directly from AWS STS operations. *Needed for local development only.*                                                                                                 | *null*                       |
| AWS_DEFAULT_REGION                        | Specifies the AWS Region to send the request to. Example: us-east-1                                                                                                                                                                                                                          | us-east-1                    |
| AWS_BUCKET                                | The AWS bucket name for storing the Payment service account updater files                                                                                                                                                                                                                    | account-updater-files        |
| AWS_USE_PATH_STYLE_ENDPOINT               | Specifies whether the AWS SDK should use the path-style endpoint URL format. Set to false to use virtual hosted–style endpoints, more commonly used today                                                                                                                                    | false                        |
| WORLDPAY_APPLICATION_NAME                 | Application name retrieved from Worldpay                                                                                                                                                                                                                                                     | Aptive                       |
| QUEUE_CONNECTION                          | The connection to use for the Queue system. Defaults to "sync". Set to "sqs" to use AWS SQS Queues.                                                                                                                                                                                          | sqs                          |
| SQS_PREFIX                                | The full URL prefix for any SQS Queues. Example: https://sqs.us-east-1.amazonaws.com/600580905024/                                                                                                                                                                                           | *null*                       |
| SQS_COLLECT_METRICS_QUEUE                 | The Queue name for collecting feature based business metrics. Example: payment-service-collect-metrics-queue                                                                                                                                                                                 | collect-metrics-queue        |
| SQS_PROCESS_PAYMENTS_QUEUE                | The Queue name for payment processing. Example: process-payments                                                                                                                                                                                                                             | process-payments-queue       |
| SQS_PROCESS_FAILED_JOBS_QUEUE             | The Queue name for handling failed jobs Example: development-01-payment_service_process_failed_jobs                                                                                                                                                                                          | process-failed-jobs-queue    |
| SQS_NOTIFICATIONS_QUEUE                   | The Queue name for sending notifications about failed payments. Example: development-01-payment_service_notifications                                                                                                                                                                        | notifications-queue          |
| SQS_PAYMENT_ACCOUNT_UPDATER_QUEUE         | The Queue name for handling Tokenex Account updater result files. Example: development-01-payment_service_account_updater_queue                                                                                                                                                              | account-updater-queue        |
| PAYMENT_PROCESSING_API_KEY                | The API Key to use for authenticating to the Payment Processing Endpoint                                                                                                                                                                                                                     | *null*                       |
| FAILED_JOBS_HANDLING_API_KEY              | The API Key to use for authenticating to the Failed Jobs Handling Endpoint                                                                                                                                                                                                                   | *null*                       |
| INFLUXDB_HOST                             | The full URL of the host for the InfluxDB database. Example: https:\\\mydatabase.influxdb.com                                                                                                                                                                                                | *null*                       |
| INFLUXDB_ORGANIZATION                     | The name of the organization to connect to in the influxDB instance. Example: "My Organization"                                                                                                                                                                                              | Application Metrics          |
| INFLUXDB_BUCKET                           | The name of the influxDB bucket to connect to. Example: my_bucket                                                                                                                                                                                                                            | *null*                       |
| INFLUXDB_TOKEN                            | The token to use to authenticate to the InfluxDB database                                                                                                                                                                                                                                    | *null*                       |
| SLACK_NOTIFICATION_WEBHOOK                | The webhook that is used for sending Slack Notifications                                                                                                                                                                                                                                     | *null*                       |
| SLACK_DATA_SYNC_NOTIFICATION_WEBHOOK      | The webhook that is used for sending Slack Notifications related to data sync                                                                                                                                                                                                                | *null*                       |
| CONFIGCAT_SDK_KEY                         | The SDK Key used to authenticate to a specific ConfigCat Feature Flagging environment                                                                                                                                                                                                        | *null*                       |
| TOKENEX_URL                               | Tokenex URL that will be used for Transparent Gateway operations https://docs.tokenex.com/docs/select-a-tgapi-endpoint-1                                                                                                                                                                     | *null*                       |
| TOKENEX_ID                                | TokenEx ID used to generate Authentication Key (iframe)                                                                                                                                                                                                                                      | *null*                       |
| TOKENEX_CLIENT_SECRET_KEY                 | TokenEx Client Secret Key used to generate Authentication Key (iframe)                                                                                                                                                                                                                       | *null*                       |
| TOKENEX_SERVICE_TOKEN_ID                  | TokenEx ID used to generate Authentication Key (api service)                                                                                                                                                                                                                                 | *null*                       |
| TOKENEX_SERVICE_CLIENT_SECRET             | TokenEx Client Secret Key used to generate Authentication Key (api service)                                                                                                                                                                                                                  | *null*                       |
| SODIUM_ENCRYPTION_SECRET_KEY              | Key for encryption used by sodium extension                                                                                                                                                                                                                                                  | *null*                       |
| CACHE_DRIVER                              | The driver to use for caching. i.e. redis, file, etc... For Redis Clusters the value should be "redis_cluster"                                                                                                                                                                               | redis                        |
| REDIS_HOST                                | The host URL for the redis cache                                                                                                                                                                                                                                                             | localhost                    |
| REDIS_PASSWORD                            | The password used to authenticate to the redis cache. Leave blank if no password is required.                                                                                                                                                                                                | *null*                       |
| REDIS_PORT                                | The port used to connect to the redis cache                                                                                                                                                                                                                                                  | 6379                         |
| REDIS_CLIENT                              | The client to use to communicate with redis. Example: predis, phpredis                                                                                                                                                                                                                       | phpredis                     |
| BATCH_PAYMENT_PROCESSING_API_ACCOUNT_ID   | The Batch Payment Processing API Account ID from CRM database                                                                                                                                                                                                                                | *null*                       |
| AUTH_URL                                  | The URL to the service that handles authorization                                                                                                                                                                                                                                            | *null*                       |
| CRM_TARGET_ENTITY_ID                      | CRM application ID to specify the token should have an access to it                                                                                                                                                                                                                          | *null*                       |
| PAYMENT_SERVICE_API_ACCOUNT_CLIENT_ID     | The client ID of the Payment Service API account                                                                                                                                                                                                                                             | *null*                       |
| PAYMENT_SERVICE_API_ACCOUNT_CLIENT_SECRET | The secret of the Payment Service API account                                                                                                                                                                                                                                                | *null*                       |
| CRM_BASE_URL                              | The base URL for CRM service API                                                                                                                                                                                                                                                             | *null*                       |
| MARKETING_API_KEY                         | The API key for Marketing Service API                                                                                                                                                                                                                                                        | *null*                       |
| MARKETING_MESSAGING_API_URL               | The URL for new Marketing Messaging API which is responsible for sending messages and emails                                                                                                                                                                                                 | *null*                       |
| CUSTOMER_SUPPORT_EMAIL                    | The email address typically used as the from address when sending email to customers                                                                                                                                                                                                         | customersupport@goaptive.com |
| FAILED_REFUNDS_REPORT_RECEIVER            | The email of the receiver for the failed refund payments report                                                                                                                                                                                                                              | *null*                       |

## External Service Dependencies

Here you can find a list of all external services that this service is dependent on. External roughly meaning: "Communication occurs over a network". Examples: MySQL Database, Mongo Database, AWS S3, AWS SQS, Apache Kafka, Sendgrid, Twilio SMS, DocuSign, Google Maps, etc...

| Depends on Service           |
|------------------------------|
| PestRoutes                   |
| WorldPay                     |
| TokenEx                      |
| MySQL DB                     |
| PostgreSQL DB                |
| AWS SQS                      |
| AWS S3                       |
| InfluxDB                     |
| DynamoDB                     |
| Slack API                    |
| ConfigCat                    |
| Amazon ElastiCache for Redis |
| CRM API                      |
| Marketing Messaging API      |

## Local Development — First Time Setup Instructions

1. Ensure that you have docker desktop and docker compose tools installed on your host machine

    https://docs.docker.com/desktop/
    https://docs.docker.com/compose/install/linux/#install-the-plugin-manually

2. Copy the `compose.override.yml.example` into a new `compose.override.yml` file. Make any needed adjustments.
3. Copy these `pestroutes-credentials.json.example` and `worldpay-credentials.json.example` files from `.docker/localstack/dynamodb` to their respective names without the `.example` extension to the same folder and replace the actual credentials needed for your environment.
   1. In case there are more than 25 items in your JSON file, you will need to split it to different files. The names should be `pestroutes-credentials-1.json`, `pestroutes-credentials-2.json`, etc.
4. Add an auth.json file to the root directory to authenticate to Aptive Composer Packages Repository for local development
   1. Reference: https://aptive.atlassian.net/wiki/spaces/EN/pages/1524924437/Installing+a+Custom+Composer+Package+-+Guide#auth.json
5. Build and start up the containers in the stack using this command

```sh
    docker compose up --build
```

6. The local development environment can be accessed at this url: http://locahost:8080 (port should be exposed in `compose.override.yml`)
7. If needed you can also run any commands by execing into the terminal of the php container then executing the commands like shown.

```sh
    docker compose exec app bash
```
  1. Then once in the terminal of the container

```sh
    php artisan config:cache
```
```sh
    composer install
```
```sh
    php artisan test
```

8. Add/Update git hooks

```sh
    yes | cp -rf ./bin/pre-commit .git/hooks/pre-commit
    yes | cp -rf ./bin/commit-msg .git/hooks/commit-msg
    chmod ug+x .git/hooks/pre-commit .git/hooks/commit-msg
```

### LocalStack
LocalStack is a tool which allows moving away from using shared AWS resources during local development in favor of local containers. It will come up as part of the stack by default.

When the localstack container comes up it will auto create the needed AWS resources. If you need more AWS "mock" resources defined in localstack you can add them in the bash script `.docker/localstack/init.sh`

Documentation: https://docs.localstack.cloud/overview/

Steps to enable it locally:

1. Update/set these .env variables:
```
AWS_ENDPOINT_URL=http://localstack:4566
AWS_USE_PATH_STYLE_ENDPOINT=true
```

This will instruct the AWS SDK to send requests to the localstack container rather than the actual AWS cloud services.

> **Note:** You should not set either of the variables above in environments other than local development, thus they are not included in the ENV variables listed above for the service.

2. Make sure bash init script is executable by running `chmod +x .docker/localstack/init.sh`
3. Restart your containers

### Queues and Workers

- Please note that this service uses an AWS SQS Queueing system. To test and develop code with the AWS SQS Queues disabled just set the QUEUE_CONNECTION env variable to "sync", set it to "sqs" and be authenticated to AWS in order to use the SQS queues.
- There are Queue Worker containers that are spun up by docker compose to process queue jobs when the QUEUE_CONNECTION is set to "sqs". They will automatically listen for and process jobs on the respective queues.

### Docker Notes

- You must install new composer packages from INSIDE the respective docker containers (notifications-php). Use the following command to run commands from inside a container. You can then execute normal composer, artisan, or other commands inside the respective containers. (Container names can be found inside the docker-compose.yml file)

```sh
    docker compose exec [container-name] bash
```
```sh
    docker compose exec [container-name] sh
```

- You only need to rebuild the docker images when changes to the dockerfiles are made. You can rebuild the docker images with:

```sh
    docker compose build
```

- Use this command to bring up all the containers for your local docker environment. Bind mounts are used so that changes to code are made in real time inside the docker containers. (You should see code changes live)

```sh
    docker compose up
```

- You can bring down the containers and clear all volumes with this command. This will clear any of the "cached" docker volumes that are in use

```sh
    docker compose down -v
```
### Generate sodium secret key

```sh
    docker exec -it app bash
```
```sh
    php -r "print sodium_bin2hex(sodium_crypto_secretbox_keygen()) . PHP_EOL;"
```

### Grafana & InfluxDB

Grafana and influxDB are used for collecting application feature based and business KPI metrics. For convenience local grafana and influxdb containers have been setup in the docker compose stack and can be used for local development when writing code to instrument these metrics.

The grafana dashboard can be accessed at: `http://localhost:3000` and is pre configured to connect to the local influxDB as a datasource

The influxdb GUI can be accessed at: `http://localhost:8086` with username: `admin` and password: `password`

References:

- Feature Based Metrics and Business KPIs: https://aptive.atlassian.net/l/cp/MmbJGE4W
- InfluxDB: https://docs.influxdata.com/influxdb/v2.7/
- Grafana: https://grafana.com/docs/grafana/latest/

### Composer scripts

For convenience, the following composer scripts have been added to the project to help with common development tasks.
- `composer test` - Runs all tests in the project
- `composer test-coverage` - Runs all tests in the project and generates a coverage report
- `composer phpstan` - Runs PHPStan static code analysis
- `composer pint` - Runs Pint for code style checking and fix issues
- `composer pint-test` - Runs Pint for code style checking
- `composer infection` - Runs Infection for mutation testing

### Running Pint For Code style checking

This service uses the [Pint](https://laravel.com/docs/10.x/pint#running-pint) package for code style checking. 
You can run the pint command from inside the php container to check for code style issues and fix them.

```sh
    docker compose exec app vendor/bin/pint
```
```sh
    docker compose exec app composer pint
```

If you want to just validate your code style without fixing it, you can run the following command:

```sh
    docker compose exec app vendor/bin/pint --test
```
```sh
    docker compose exec app composer pint-test
```

The description of used rules can be found in the [here](https://aptive.atlassian.net/wiki/spaces/EN/pages/1948188679/PHP+Code+Style+Fixer+Rules).


#### Note on static_lambda rule
If Pint doesn't detect `$this->` usage within a callback it will automatically try to convert it to a static closure 
like `static fn () => {}`. This may lead to an unexpected behavior. Example `PaymentFactory.php`: 
```php
/**
* @return self
*/
public function cc(): self
{
    return $this->state(state: fn () => [
        'payment_type_id' => PaymentTypeEnum::CC->value,
    ]);
}
```
will be converted to
```php
/**
* @return self
*/
public function cc(): self
{
    return $this->state(state: static fn () => [
        'payment_type_id' => PaymentTypeEnum::CC->value,
    ]);
}
```
This leads to the `Cannot bind an instance to a static closure` exception when you use factory!
There is no way to disable Pint checks per file/line.

As a **temporary** workaround you need to add `$this->` call inside a callback:
```php
/**
* @return self
*/
public function cc(): self
{
    return $this->state(state: static fn () => [
        'payment_type_id' => PaymentTypeEnum::CC->value,
        'cc_expiration_month' => $this->faker->month(max: 12),
    ]);
}
```

### Running PHPStan for static code analysis

This service uses [PHPStan](https://phpstan.org/config-reference) for static code analysis. You can run the PHPStan 
command from inside the php container.

```sh
    docker compose exec app vendor/bin/phpstan analyse --memory-limit=512M
```
```sh
    docker compose exec app composer phpstan
```


### Infection

This service uses [Infection](https://infection.github.io/guide/) tool for mutation testing. 
To use it locally, just re-build your Docker container, copy [infection.json5.dist](infection.json5.dist) 
into [infection.json5](infection.json5) and change configuration if needed.
You can run the Infection with the following command:

```sh
    docker compose exec app php -d memory_limit=512M /usr/local/bin/infection --no-progress --threads=max
```
```sh
    docker compose exec app composer infection
```

To run the infection on specific file you could use the --filter option:

```sh
    docker compose exec app php -d memory_limit=512M /usr/local/bin/infection --no-progress --threads=max --filter=app/Infrastructure/PestRoutes/PestRoutesDataRetrieverService.php
```
```sh
    docker compose exec app composer infection -- --filter=app/Infrastructure/PestRoutes/PestRoutesDataRetrieverService.php
```

Also, Infection will be automatically run on CI for modified or added files.
You could use this tool to make sure you are writing good tests for your code.

### Working with database

This repository uses git submodules that reference the MySQL (db-schema-payment-service) and PostgreSQL (db-schema-crm) databases.

To update a git submodule you can simply use this command:

```sh
    git submodule update --init --recursive --remote
```

#### Managing DB schemas

For your convenience, we have created a script that will apply all the SQL files from the DBE repositories to the local databases.

To run the script, just execute the following command which will clean MySQL and Postgres databases and apply the latest SQL files from the DBE repositories:

```sh
    docker compose exec app composer refresh-db
```

In case you want to refresh the specific database, you can use the following command:

```sh
    docker compose exec app composer refresh-postgres-db
```
```sh
    docker compose exec app composer refresh-mysql-db
```

In case you want to clean all databases, you can use the following command:

```sh
    docker compose exec app composer clean-db
```

In case you want to clean the specific database, you can use the following command:

```sh
    docker compose exec app composer clean-postgres-db
```
```sh
    docker compose exec app composer clean-mysql-db
```

#### Unit tests must not touch the Database

All Unit tests must be extended from `Tests\Unit\UnitTestCase` to make sure it invalid the database connection before execute the tests. Then reset the database connection after it is done

## Documentation

### Confluence

Here you can find documentation for the service as a whole in our confluence pages.

https://aptive.atlassian.net/wiki/spaces/EN/pages/1643708452/Payment+Service

### Web API

Here you can find documentation for the exposed Web API of this service

https://apidocs.aptive.tech/

# Additional References

[CONTRIBUTING.md](/CONTRIBUTING.md) - Instructions on Developer Environment setup and how to contribute to this repository
